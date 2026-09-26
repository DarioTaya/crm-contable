/**
 * Filtros del tablero de Control de Deudas.
 *
 * Entre criterios distintos la deuda tiene que cumplir todos (denominación Y
 * periodo Y tributo Y vencimiento Y clientes marcados). Dentro de un mismo
 * criterio, las opciones marcadas se suman. Una lista vacía no restringe.
 * Las opciones de cada criterio se calculan con los demás ya aplicados, así
 * el orden en que se marcan no cambia el resultado.
 */

const FACETA_DENOMINACION = 'denominacion';
const FACETA_PERIODO = 'periodo';
const FACETA_TRIBUTO = 'tributo';
const FACETA_VENCIMIENTO = 'vencimiento';
const FACETA_CLIENTE = 'cliente';

/**
 * @param {unknown} valor
 * @returns {string}
 */
export function texto(valor) {
    if (valor === null || valor === undefined) {
        return '';
    }

    return String(valor);
}

/**
 * @param {unknown} valor
 * @returns {string}
 */
export function fechaIso(valor) {
    const fecha = texto(valor).trim();

    return /^\d{4}-\d{2}-\d{2}$/.test(fecha) ? fecha : '';
}

/**
 * @param {unknown} desde
 * @param {unknown} hasta
 * @returns {boolean}
 */
export function rangoInvertido(desde, hasta) {
    const inicio = fechaIso(desde);
    const fin = fechaIso(hasta);

    return inicio !== '' && fin !== '' && inicio > fin;
}

/**
 * @param {string} iso
 * @returns {string}
 */
export function fechaLegible(iso) {
    const partes = partesIso(iso);
    if (partes === null) {
        return '';
    }

    return `${rellenar(partes.dia)}/${rellenar(partes.mes)}/${partes.anio}`;
}

/**
 * @param {unknown} valor
 * @returns {string}
 */
export function normalizarTexto(valor) {
    return texto(valor)
        .normalize('NFD')
        .replace(/\p{M}/gu, '')
        .toLowerCase()
        .trim();
}

/**
 * @param {{ etiqueta?: string, detalle?: string, codigo?: string }} opcion
 * @param {unknown} consulta
 * @returns {boolean}
 */
export function opcionCoincideBusqueda(opcion, consulta) {
    const busqueda = normalizarTexto(consulta);
    if (busqueda === '') {
        return true;
    }

    return [opcion.etiqueta, opcion.detalle, opcion.codigo].some((campo) => normalizarTexto(campo).includes(busqueda));
}

/**
 * @param {string} hoyIso
 * @param {number} dias
 * @returns {string}
 */
function sumarDias(hoyIso, dias) {
    const partes = partesIso(hoyIso);
    if (partes === null) {
        return '';
    }

    const fecha = new Date(Date.UTC(partes.anio, partes.mes - 1, partes.dia));
    fecha.setUTCDate(fecha.getUTCDate() + dias);

    return `${fecha.getUTCFullYear()}-${rellenar(fecha.getUTCMonth() + 1)}-${rellenar(fecha.getUTCDate())}`;
}

/**
 * @param {string} preset
 * @param {string} hoyIso
 * @returns {{ venceDesde: string, venceHasta: string }|null}
 */
export function rangoPreset(preset, hoyIso) {
    const partes = partesIso(hoyIso);
    if (partes === null) {
        return null;
    }

    if (preset === 'vencidas') {
        return { venceDesde: '', venceHasta: sumarDias(hoyIso, -1) };
    }

    if (preset === 'proximos_7') {
        return { venceDesde: hoyIso, venceHasta: sumarDias(hoyIso, 6) };
    }

    if (preset === 'mes') {
        const ultimo = new Date(Date.UTC(partes.anio, partes.mes, 0)).getUTCDate();

        return {
            venceDesde: `${partes.anio}-${rellenar(partes.mes)}-01`,
            venceHasta: `${partes.anio}-${rellenar(partes.mes)}-${rellenar(ultimo)}`,
        };
    }

    return null;
}

/**
 * @param {Record<string, unknown>} deuda
 * @param {Record<string, unknown>} filtros
 * @param {string|null} excepto
 * @returns {boolean}
 */
export function cumpleFiltros(deuda, filtros, excepto = null) {
    const criterios = filtros || {};

    if (!cumpleClientes(deuda, criterios, excepto)) {
        return false;
    }

    if (excepto !== FACETA_DENOMINACION && !cumpleLista(deuda.cliente_id, criterios.denominaciones)) {
        return false;
    }

    if (excepto !== FACETA_PERIODO && !cumpleLista(deuda.periodo, criterios.periodos)) {
        return false;
    }

    if (excepto !== FACETA_TRIBUTO && !cumpleLista(deuda.tributo_codigo, criterios.tributos)) {
        return false;
    }

    if (excepto !== FACETA_VENCIMIENTO && !cumpleVencimiento(deuda, criterios)) {
        return false;
    }

    return true;
}

/**
 * @param {Array<Record<string, unknown>>} deudas
 * @param {Record<string, unknown>} filtros
 * @returns {Array<Record<string, unknown>>}
 */
export function filtrarDeudas(deudas, filtros) {
    return (Array.isArray(deudas) ? deudas : []).filter((deuda) => cumpleFiltros(deuda, filtros));
}

/**
 * @param {Array<Record<string, unknown>>} deudas
 * @param {Record<string, unknown>} filtros
 * @param {string} faceta
 * @returns {Array<{ valor: string, etiqueta: string, detalle: string, codigo: string, cantidad: number }>}
 */
export function opcionesFaceta(deudas, filtros, faceta) {
    const lista = Array.isArray(deudas) ? deudas : [];
    const universo = lista.filter((deuda) => cumpleFiltros(deuda, filtros, faceta));
    const campo = campoFaceta(faceta);
    if (campo === null) {
        return [];
    }

    const mapa = new Map();
    for (const deuda of universo) {
        const valor = campo.valor(deuda);
        if (valor === '') {
            continue;
        }

        if (!mapa.has(valor)) {
            mapa.set(valor, {
                valor,
                etiqueta: campo.etiqueta(deuda),
                detalle: campo.detalle(deuda),
                codigo: campo.codigo(deuda),
                cantidad: 0,
            });
        }

        mapa.get(valor).cantidad += 1;
    }

    for (const valor of comoLista(seleccionFaceta(filtros, faceta))) {
        if (mapa.has(valor)) {
            continue;
        }

        const muestra = lista.find((deuda) => campo.valor(deuda) === valor);
        if (!muestra) {
            continue;
        }

        mapa.set(valor, {
            valor,
            etiqueta: campo.etiqueta(muestra),
            detalle: campo.detalle(muestra),
            codigo: campo.codigo(muestra),
            cantidad: 0,
        });
    }

    return [...mapa.values()].sort(campo.orden);
}

/**
 * @param {URLSearchParams} params
 * @param {Array<Record<string, unknown>>} deudas
 * @returns {{ denominaciones: string[], periodos: string[], tributos: string[], venceDesde: string, venceHasta: string }}
 */
export function filtrosDesdeParametros(params, deudas = []) {
    const lista = Array.isArray(deudas) ? deudas : [];
    const ids = new Set(lista.map((deuda) => texto(deuda.cliente_id)).filter((valor) => valor !== ''));
    const periodos = new Set(lista.map((deuda) => texto(deuda.periodo)).filter((valor) => valor !== ''));
    const tributos = new Set(lista.map((deuda) => texto(deuda.tributo_codigo)).filter((valor) => valor !== ''));

    return {
        denominaciones: leerLista(params, 'denominacion').filter((valor) => ids.has(valor)),
        periodos: leerLista(params, 'periodo').filter((valor) => periodos.has(valor)),
        tributos: leerLista(params, 'tributo').filter((valor) => tributos.has(valor)),
        venceDesde: fechaIso(params.get('vence_desde')),
        venceHasta: fechaIso(params.get('vence_hasta')),
    };
}

/**
 * @param {Record<string, unknown>} filtros
 * @returns {URLSearchParams}
 */
export function parametrosDesdeFiltros(filtros) {
    const criterios = filtros || {};
    const params = new URLSearchParams();
    escribirLista(params, 'denominacion', criterios.denominaciones);
    escribirLista(params, 'periodo', criterios.periodos);
    escribirLista(params, 'tributo', criterios.tributos);

    const desde = fechaIso(criterios.venceDesde);
    const hasta = fechaIso(criterios.venceHasta);
    if (desde !== '') {
        params.set('vence_desde', desde);
    }
    if (hasta !== '') {
        params.set('vence_hasta', hasta);
    }

    return params;
}

/**
 * @param {Record<string, unknown>} deuda
 * @param {Record<string, unknown>} filtros
 * @param {string|null} excepto
 * @returns {boolean}
 */
function cumpleClientes(deuda, filtros, excepto) {
    if (excepto === FACETA_CLIENTE || !Array.isArray(filtros.clienteIds)) {
        return true;
    }

    return comoLista(filtros.clienteIds).includes(texto(deuda.cliente_id));
}

/**
 * @param {unknown} valorDeuda
 * @param {unknown} seleccion
 * @returns {boolean}
 */
function cumpleLista(valorDeuda, seleccion) {
    const marcada = comoLista(seleccion);
    if (marcada.length === 0) {
        return true;
    }

    return marcada.includes(texto(valorDeuda));
}

/**
 * @param {Record<string, unknown>} deuda
 * @param {Record<string, unknown>} filtros
 * @returns {boolean}
 */
function cumpleVencimiento(deuda, filtros) {
    const desde = fechaIso(filtros.venceDesde);
    const hasta = fechaIso(filtros.venceHasta);
    if (desde === '' && hasta === '') {
        return true;
    }

    if (rangoInvertido(desde, hasta)) {
        return false;
    }

    const vencimiento = fechaIso(deuda.vencimiento);
    if (vencimiento === '') {
        return false;
    }

    if (desde !== '' && vencimiento < desde) {
        return false;
    }

    if (hasta !== '' && vencimiento > hasta) {
        return false;
    }

    return true;
}

/**
 * @param {unknown} valores
 * @returns {string[]}
 */
function comoLista(valores) {
    if (!Array.isArray(valores)) {
        return [];
    }

    return [...new Set(valores.map((valor) => texto(valor).trim()).filter((valor) => valor !== ''))];
}

/**
 * @param {URLSearchParams} params
 * @param {string} clave
 * @returns {string[]}
 */
function leerLista(params, clave) {
    return comoLista(texto(params.get(clave)).split(','));
}

/**
 * @param {URLSearchParams} params
 * @param {string} clave
 * @param {unknown} valores
 */
function escribirLista(params, clave, valores) {
    const lista = comoLista(valores);
    if (lista.length === 0) {
        return;
    }

    params.set(clave, lista.join(','));
}

/**
 * @param {Record<string, unknown>} filtros
 * @param {string} faceta
 * @returns {unknown}
 */
function seleccionFaceta(filtros, faceta) {
    const criterios = filtros || {};
    if (faceta === FACETA_DENOMINACION) {
        return criterios.denominaciones;
    }
    if (faceta === FACETA_PERIODO) {
        return criterios.periodos;
    }
    if (faceta === FACETA_TRIBUTO) {
        return criterios.tributos;
    }

    return [];
}

/**
 * @param {string} faceta
 * @returns {null|{ valor: (deuda: Record<string, unknown>) => string, etiqueta: (deuda: Record<string, unknown>) => string, detalle: (deuda: Record<string, unknown>) => string, codigo: (deuda: Record<string, unknown>) => string, orden: (a: { valor: string, etiqueta: string }, b: { valor: string, etiqueta: string }) => number }}
 */
function campoFaceta(faceta) {
    if (faceta === FACETA_DENOMINACION) {
        return {
            valor: (deuda) => texto(deuda.cliente_id),
            etiqueta: (deuda) => texto(deuda.cliente_razon) || 'Empresa sin nombre',
            detalle: (deuda) => texto(deuda.cliente_ruc),
            codigo: (deuda) => texto(deuda.cliente_codigo),
            orden: (a, b) => a.etiqueta.localeCompare(b.etiqueta, 'es'),
        };
    }

    if (faceta === FACETA_PERIODO) {
        return {
            valor: (deuda) => texto(deuda.periodo),
            etiqueta: (deuda) => texto(deuda.periodo),
            detalle: () => '',
            codigo: (deuda) => texto(deuda.periodo),
            orden: (a, b) => clavePeriodo(b.valor) - clavePeriodo(a.valor),
        };
    }

    if (faceta === FACETA_TRIBUTO) {
        return {
            valor: (deuda) => texto(deuda.tributo_codigo),
            etiqueta: etiquetaTributo,
            detalle: (deuda) => [deuda.tributo_nombre_corto, deuda.tributo_nombre].map((campo) => texto(campo)).filter((campo) => campo !== '').join(' '),
            codigo: (deuda) => texto(deuda.tributo_codigo),
            orden: (a, b) => a.valor.localeCompare(b.valor, 'es', { numeric: true }),
        };
    }

    return null;
}

/**
 * @param {Record<string, unknown>} deuda
 * @returns {string}
 */
function etiquetaTributo(deuda) {
    const codigo = texto(deuda.tributo_codigo);
    const corto = texto(deuda.tributo_nombre_corto) || texto(deuda.tributo_nombre) || 'Tributo';

    return codigo === '' ? corto : `${codigo} - ${corto}`;
}

/**
 * @param {string} periodo
 * @returns {number}
 */
function clavePeriodo(periodo) {
    const coincidencia = /^(0[1-9]|1[0-2])-(\d{4})$/.exec(periodo);
    if (coincidencia === null) {
        return -1;
    }

    return (Number(coincidencia[2]) * 100) + Number(coincidencia[1]);
}

/**
 * @param {string} iso
 * @returns {{ anio: number, mes: number, dia: number }|null}
 */
function partesIso(iso) {
    const coincidencia = /^(\d{4})-(\d{2})-(\d{2})$/.exec(fechaIso(iso));
    if (coincidencia === null) {
        return null;
    }

    return {
        anio: Number(coincidencia[1]),
        mes: Number(coincidencia[2]),
        dia: Number(coincidencia[3]),
    };
}

/**
 * @param {number} valor
 * @returns {string}
 */
function rellenar(valor) {
    return String(valor).padStart(2, '0');
}
