{{-- Carga opcional. No forma parte de las cajas flotantes ni del guardado celda por celda. --}}
<script>
    function cargaMasivaControl(config) {
        return {
            config,
            abierto: false,
            paso: 'elegir',
            pestana: 'declaraciones',
            mesDesde: 1,
            mesHasta: config.mesHastaSugerido || 12,
            estado: 'Declarado',
            obligaciones: ['sire', 'pdt', 'ple'],
            politica: 'conservar',
            seleccionados: (config.clientes || []).map((cliente) => cliente.id),
            filtro: '',
            archivo: null,
            nombreArchivo: '',
            pegado: '',
            revisando: false,
            aplicando: false,
            mensaje: '',
            resultado: null,

            abrir() {
                this.abierto = true;
                this.$nextTick(() => this.$refs.titulo?.focus());
            },

            cerrar() {
                if (this.aplicando) {
                    return;
                }
                this.abierto = false;
            },

            clientesVisibles() {
                const consulta = this.filtro.trim().toLowerCase();
                if (!consulta) {
                    return this.config.clientes || [];
                }

                return (this.config.clientes || []).filter((cliente) => {
                    return String(cliente.razon_social || '').toLowerCase().includes(consulta)
                        || String(cliente.ruc || '').includes(consulta)
                        || String(cliente.numero || '').includes(consulta);
                });
            },

            todosMarcados() {
                const clientes = this.config.clientes || [];
                return clientes.length > 0 && this.seleccionados.length === clientes.length;
            },

            alternarTodos(marcado) {
                this.seleccionados = marcado ? (this.config.clientes || []).map((cliente) => cliente.id) : [];
                this.limpiarRevision();
            },

            alternarCliente(id, marcado) {
                if (marcado) {
                    if (!this.seleccionados.includes(id)) {
                        this.seleccionados.push(id);
                    }
                } else {
                    this.seleccionados = this.seleccionados.filter((actual) => actual !== id);
                }
                this.limpiarRevision();
            },

            alternarObligacion(clave, marcado) {
                if (marcado) {
                    if (!this.obligaciones.includes(clave)) {
                        this.obligaciones.push(clave);
                    }
                } else {
                    this.obligaciones = this.obligaciones.filter((actual) => actual !== clave);
                }
                this.limpiarRevision();
            },

            textoPlantilla() {
                const total = (this.config.clientes || []).length;
                const marcas = this.seleccionados.length;
                if (marcas === 0) {
                    return 'Elija al menos una empresa. La plantilla solo trae las que marque.';
                }
                if (marcas === 1) {
                    const cliente = (this.config.clientes || []).find((item) => item.id === this.seleccionados[0]);
                    return 'La plantilla incluye solo ' + (cliente ? cliente.razon_social : 'la empresa marcada') + '.';
                }
                if (marcas === total) {
                    return 'La plantilla incluye las ' + total + ' empresas del ejercicio ' + this.config.anio + '.';
                }

                return 'La plantilla incluye ' + marcas + ' de ' + total + ' empresas.';
            },

            descargarPlantilla() {
                if (this.seleccionados.length === 0) {
                    this.mensaje = 'Elija al menos una empresa para armar la plantilla.';
                    return;
                }

                const url = new URL(this.config.plantillaUrl, window.location.origin);
                if (!this.todosMarcados()) {
                    this.seleccionados.forEach((id) => url.searchParams.append('cliente_ids[]', String(id)));
                }

                const enlace = document.createElement('a');
                enlace.href = url.toString();
                document.body.appendChild(enlace);
                enlace.click();
                enlace.remove();
            },

            elegirArchivo(evento) {
                const archivo = evento.target.files ? evento.target.files[0] : null;
                this.archivo = archivo;
                this.nombreArchivo = archivo ? archivo.name : '';
                this.limpiarRevision();
            },

            quitarArchivo() {
                this.archivo = null;
                this.nombreArchivo = '';
                if (this.$refs.archivo) {
                    this.$refs.archivo.value = '';
                }
                this.limpiarRevision();
            },

            limpiarRevision() {
                this.resultado = null;
                this.mensaje = '';
                if (this.paso === 'revision') {
                    this.paso = 'elegir';
                }
            },

            formulario() {
                const datos = new FormData();
                datos.append('anio', String(this.config.anio));
                datos.append('politica', this.politica);
                datos.append('modo', this.pestana === 'declaraciones' ? 'estados' : 'plantilla');

                if (this.pestana === 'declaraciones') {
                    datos.append('mes_desde', String(this.mesDesde));
                    datos.append('mes_hasta', String(this.mesHasta));
                    datos.append('estado', this.estado);
                    this.obligaciones.forEach((obligacion) => datos.append('obligaciones[]', obligacion));
                }

                if (this.todosMarcados()) {
                    datos.append('alcance', 'todos');
                } else {
                    datos.append('alcance', 'seleccion');
                    this.seleccionados.forEach((id) => datos.append('cliente_ids[]', String(id)));
                }

                if (this.archivo) {
                    datos.append('archivo', this.archivo);
                } else if (this.pegado.trim() !== '') {
                    datos.append('contenido', this.pegado);
                }

                return datos;
            },

            validarLocal() {
                if (this.pestana === 'declaraciones') {
                    if ((this.config.clientes || []).length === 0) {
                        return 'Este ejercicio no tiene clientes para cargar.';
                    }
                    if (this.seleccionados.length === 0) {
                        return 'Elija al menos un cliente.';
                    }
                    if (this.obligaciones.length === 0) {
                        return 'Elija al menos una declaración: SIRE, PDT 621 o PDT 601.';
                    }
                    if (Number(this.mesDesde) > Number(this.mesHasta)) {
                        return 'El mes final no puede ser anterior al mes inicial.';
                    }
                    return '';
                }

                if ((this.config.clientes || []).length === 0) {
                    return 'Este ejercicio no tiene clientes para cargar.';
                }
                if (this.seleccionados.length === 0) {
                    return 'Elija al menos una empresa.';
                }
                if (!this.archivo && this.pegado.trim() === '') {
                    return 'Suba la plantilla CSV o pegue el contenido de Excel.';
                }

                return '';
            },

            async revisar() {
                this.mensaje = this.validarLocal();
                if (this.mensaje) {
                    return;
                }

                this.revisando = true;
                this.mensaje = '';
                try {
                    const respuesta = await this.enviar(this.config.previsualizarUrl);
                    this.resultado = respuesta.datos;
                    if (!respuesta.ok && !respuesta.datos.errores && !respuesta.datos.resumen) {
                        this.mensaje = respuesta.mensaje;
                        return;
                    }
                    this.paso = 'revision';
                } catch (error) {
                    this.mensaje = 'No se pudo revisar la carga. Inténtelo de nuevo.';
                } finally {
                    this.revisando = false;
                }
            },

            async aplicar() {
                if (!this.resultado || !this.resultado.puede_aplicar || this.aplicando) {
                    return;
                }

                this.aplicando = true;
                this.mensaje = '';
                try {
                    const respuesta = await this.enviar(this.config.aplicarUrl);
                    this.resultado = respuesta.datos;
                    if (respuesta.ok && respuesta.datos.aplicada) {
                        this.paso = 'listo';
                        return;
                    }
                    this.paso = 'revision';
                    if (!respuesta.datos.errores) {
                        this.mensaje = respuesta.mensaje;
                    }
                } catch (error) {
                    this.mensaje = 'No se pudo guardar. No se modificó la matriz; puede reintentar.';
                } finally {
                    this.aplicando = false;
                }
            },

            async enviar(url) {
                const token = document.querySelector('meta[name="csrf-token"]');
                const respuesta = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    },
                    body: this.formulario(),
                });
                const datos = await respuesta.json().catch(() => ({}));
                let mensaje = datos.message || 'No se pudo completar la carga.';
                if (datos.errors) {
                    const primero = Object.values(datos.errors).flat()[0];
                    if (primero) {
                        mensaje = primero;
                    }
                }

                return { ok: respuesta.ok, datos, mensaje };
            },
        };
    }
</script>

<div x-data="cargaMasivaControl(@js($cargaMasiva))"
     x-on:abrir-carga-masiva.window="abrir()"
     x-on:keydown.escape.window="cerrar()"
     x-cloak>
    <div x-show="abierto"
         class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="carga-masiva-titulo">
        <div x-show="abierto" x-transition.opacity class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs" @click="cerrar()" aria-hidden="true"></div>

        <div class="relative z-10 my-8 w-full max-w-3xl rounded-overlay bg-surface shadow-overlay">
            <div class="flex items-start justify-between gap-4 border-b border-hairline px-6 py-4">
                <div>
                    <h2 id="carga-masiva-titulo" tabindex="-1" x-ref="titulo" class="text-base font-semibold text-neutral-950 outline-none">Carga masiva</h2>
                    <p class="mt-1 text-sm text-neutral-600">Para poner al día un ejercicio de una sola vez. El llenado celda por celda sigue igual.</p>
                </div>
                <button type="button" @click="cerrar()" class="rounded-control p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800" aria-label="Cerrar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-6 py-5">
                <div x-show="paso === 'elegir'" class="space-y-5">
                    <div class="grid grid-cols-2 gap-2 rounded-panel border border-hairline bg-neutral-100 p-1" role="tablist" aria-label="Tipo de carga">
                        <button type="button" role="tab" :aria-selected="pestana === 'declaraciones'" @click="pestana = 'declaraciones'; limpiarRevision()"
                                class="rounded-control border px-3 py-2 text-left shadow-xs transition"
                                :class="pestana === 'declaraciones' ? 'border-brand-600 bg-brand-600 text-white' : 'border-hairline bg-white text-neutral-800 hover:border-neutral-300'">
                            <span class="block text-sm font-semibold">Declaraciones</span>
                            <span class="mt-0.5 block text-[11px] font-medium" :class="pestana === 'declaraciones' ? 'text-white/80' : 'text-neutral-500'">SIRE, PDT 621 y PDT 601</span>
                        </button>
                        <button type="button" role="tab" :aria-selected="pestana === 'tributos'" @click="pestana = 'tributos'; limpiarRevision()"
                                class="rounded-control border px-3 py-2 text-left shadow-xs transition"
                                :class="pestana === 'tributos' ? 'border-brand-600 bg-brand-600 text-white' : 'border-hairline bg-white text-neutral-800 hover:border-neutral-300'">
                            <span class="block text-sm font-semibold">Tributos y pagos</span>
                            <span class="mt-0.5 block text-[11px] font-medium" :class="pestana === 'tributos' ? 'text-white/80' : 'text-neutral-500'">Importes del PDT y lo ya pagado</span>
                        </button>
                    </div>

                    <div x-show="pestana === 'declaraciones'" class="space-y-4">
                        <p class="text-sm text-neutral-600">Marca SIRE, PDT 621 y PDT 601 en un tramo de meses. Sirve, por ejemplo, para dejar en Declarado lo ya presentado hasta agosto.</p>

                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="block text-xs font-semibold text-neutral-700">
                                Desde
                                <select x-model.number="mesDesde" @change="limpiarRevision()" class="mt-1 w-full rounded-control border border-hairline py-1.5 text-sm">
                                    @foreach ($cargaMasiva['meses'] as $mes)
                                        <option value="{{ $mes['numero'] }}">{{ $mes['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-neutral-700">
                                Hasta
                                <select x-model.number="mesHasta" @change="limpiarRevision()" class="mt-1 w-full rounded-control border border-hairline py-1.5 text-sm">
                                    @foreach ($cargaMasiva['meses'] as $mes)
                                        <option value="{{ $mes['numero'] }}">{{ $mes['nombre'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-xs font-semibold text-neutral-700">
                                Dejar en
                                <select x-model="estado" @change="limpiarRevision()" class="mt-1 w-full rounded-control border border-hairline py-1.5 text-sm">
                                    <option value="Declarado">Declarado</option>
                                    <option value="Procesando">En proceso</option>
                                    <option value="Pendiente">Pendiente</option>
                                </select>
                            </label>
                        </div>

                        <fieldset>
                            <legend class="text-xs font-semibold text-neutral-700">Declaraciones</legend>
                            <div class="mt-2 flex flex-wrap gap-4 text-sm text-neutral-800">
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="obligaciones.includes('sire')" @change="alternarObligacion('sire', $event.target.checked)">
                                    SIRE
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="obligaciones.includes('pdt')" @change="alternarObligacion('pdt', $event.target.checked)">
                                    PDT 621
                                </label>
                                <label class="inline-flex items-center gap-2">
                                    <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="obligaciones.includes('ple')" @change="alternarObligacion('ple', $event.target.checked)">
                                    PDT 601 · PLAME
                                </label>
                            </div>
                        </fieldset>

                        <fieldset>
                            <legend class="text-xs font-semibold text-neutral-700">
                                Clientes
                                <span class="font-medium text-neutral-500" x-text="'(' + seleccionados.length + ' de ' + (config.clientes || []).length + ')'"></span>
                            </legend>
                            <div class="mt-2 rounded-panel border border-hairline">
                                <div class="flex items-center gap-3 border-b border-hairline px-3 py-2">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
                                        <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="todosMarcados()" @change="alternarTodos($event.target.checked)">
                                        Todos
                                    </label>
                                    <input type="search" x-model="filtro" placeholder="Buscar RUC o razón social" class="ml-auto w-48 rounded-control border border-hairline py-1 text-xs" aria-label="Filtrar clientes">
                                </div>
                                <div class="max-h-40 overflow-y-auto px-3 py-2">
                                    <p x-show="(config.clientes || []).length === 0" class="py-3 text-sm text-neutral-500">Este ejercicio no tiene clientes.</p>
                                    <template x-for="cliente in clientesVisibles()" :key="cliente.id">
                                        <label class="flex items-center gap-2 py-1 text-sm text-neutral-800">
                                            <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="seleccionados.includes(cliente.id)" @change="alternarCliente(cliente.id, $event.target.checked)">
                                            <span class="font-mono text-xs text-neutral-500" x-text="cliente.numero"></span>
                                            <span class="truncate" x-text="cliente.razon_social"></span>
                                            <span class="ml-auto font-mono text-xs text-neutral-500" x-text="cliente.ruc"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </fieldset>
                    </div>

                    <div x-show="pestana === 'tributos'" class="space-y-4">
                        <p class="text-sm text-neutral-600">Marque las empresas, descargue su plantilla y complete solo los meses que ya tiene. Una celda vacía, o un cero, no crea ni borra un tributo.</p>

                        <fieldset>
                            <legend class="text-xs font-semibold text-neutral-700">
                                Empresas de la plantilla
                                <span class="font-medium text-neutral-500" x-text="'(' + seleccionados.length + ' de ' + (config.clientes || []).length + ')'"></span>
                            </legend>
                            <div class="mt-2 rounded-panel border border-hairline">
                                <div class="flex items-center gap-3 border-b border-hairline px-3 py-2">
                                    <label class="inline-flex items-center gap-2 text-sm font-medium text-neutral-800">
                                        <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="todosMarcados()" @change="alternarTodos($event.target.checked)">
                                        Todas
                                    </label>
                                    <input type="search" x-model="filtro" placeholder="Buscar RUC o razón social" class="ml-auto w-48 rounded-control border border-hairline py-1 text-xs" aria-label="Filtrar empresas de la plantilla">
                                </div>
                                <div class="max-h-40 overflow-y-auto px-3 py-2">
                                    <p x-show="(config.clientes || []).length === 0" class="py-3 text-sm text-neutral-500">Este ejercicio no tiene clientes.</p>
                                    <template x-for="cliente in clientesVisibles()" :key="'plantilla-' + cliente.id">
                                        <label class="flex items-center gap-2 py-1 text-sm text-neutral-800">
                                            <input type="checkbox" class="rounded border-hairline text-brand-600" :checked="seleccionados.includes(cliente.id)" @change="alternarCliente(cliente.id, $event.target.checked)">
                                            <span class="font-mono text-xs text-neutral-500" x-text="cliente.numero"></span>
                                            <span class="truncate" x-text="cliente.razon_social"></span>
                                            <span class="ml-auto font-mono text-xs text-neutral-500" x-text="cliente.ruc"></span>
                                        </label>
                                    </template>
                                </div>
                            </div>
                        </fieldset>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="button" @click="descargarPlantilla()" :disabled="seleccionados.length === 0"
                                    class="inline-flex items-center gap-1.5 rounded-control border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100 disabled:cursor-not-allowed disabled:opacity-50">
                                Descargar plantilla {{ $cargaMasiva['anio'] }}
                            </button>
                            <p class="text-xs text-neutral-600" x-text="textoPlantilla()"></p>
                        </div>

                        <details class="rounded-panel border border-hairline bg-neutral-50 px-3 py-2 text-sm text-neutral-700">
                            <summary class="cursor-pointer font-semibold">Cómo llenar la plantilla</summary>
                            <div class="mt-2 space-y-2 text-xs leading-relaxed">
                                <p>Cada fila es una empresa y un mes. El periodo va como <span class="font-mono">01-2026</span>. Los meses de baja no vienen. No borre el RUC ni el periodo.</p>
                                <p><span class="font-mono">sire</span>, <span class="font-mono">pdt_621</span> y <span class="font-mono">pdt_601</span> son el estado de cada declaración: Pendiente, En proceso o Declarado. Si las deja vacías, el botón de la matriz no cambia. <span class="font-mono">pdt_601</span> es el PDT 601 · PLAME. No hay columna PLE: desde 2026 el PLE lo cubre el SIRE.</p>
                                <p><span class="font-mono">pagado</span> en SI registra, por cada importe de esa fila, un pago por el total, con fecha del vencimiento. Vacío o NO no toca los pagos. El medio y el número de operación se siguen anotando en la caja cuando haga falta.</p>
                                <p><span class="font-mono">igv_justo</span> es SI o NO y solo aplica al IGV 1011. SI usa la prórroga de tres meses.</p>
                                <p>El importe se escribe como <span class="font-mono">550</span>, <span class="font-mono">1500.50</span> o <span class="font-mono">1.500,50</span>. Es el mismo “importe a pagar” de la caja. Guarde el libro como CSV UTF-8, o copie la hoja y péguela abajo.</p>
                            </div>
                            @foreach ($catalogoTributos as $bloque)
                                <p class="mt-3 text-xs font-semibold text-neutral-800">{{ $bloque['titulo'] }} · {{ $bloque['detalle'] }}</p>
                                <div class="mt-1 grid gap-1 sm:grid-cols-2">
                                    @foreach ($bloque['tributos'] as $tributo)
                                        <p class="font-mono text-[11px] text-neutral-600">{{ $tributo['codigo'] }} · {{ $tributo['nombre_corto'] }}</p>
                                    @endforeach
                                </div>
                            @endforeach
                        </details>

                        <div class="rounded-panel border border-dashed border-neutral-300 bg-neutral-50 px-4 py-5 text-center">
                            <p class="text-sm font-semibold text-neutral-900">Subir la plantilla completa</p>
                            <p class="mt-1 text-xs text-neutral-600">Archivo CSV UTF-8, el que guardó desde Excel.</p>
                            <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-control border border-hairline bg-white px-3 py-2 text-xs font-semibold text-neutral-800 shadow-xs hover:bg-neutral-100">
                                <input x-ref="archivo" type="file" accept=".csv,.txt,text/csv,text/plain" @change="elegirArchivo($event)" class="sr-only">
                                Seleccionar archivo
                            </label>
                            <p x-show="nombreArchivo" x-cloak class="mt-3 flex items-center justify-center gap-2 text-xs text-neutral-700">
                                <span class="font-medium" x-text="nombreArchivo"></span>
                                <button type="button" @click="quitarArchivo()" class="font-semibold text-brand-700 hover:text-brand-800">Quitar</button>
                            </p>
                        </div>
                        <label class="block text-xs font-semibold text-neutral-700">
                            O pegue aquí desde Excel
                            <textarea x-model="pegado" @input="limpiarRevision()" :disabled="archivo !== null" rows="5" class="mt-1 w-full rounded-control border border-hairline font-mono text-xs disabled:bg-neutral-100" placeholder="ruc;periodo;1011 IGV 18%;pagado&#10;20123456789;01-2026;550;SI"></textarea>
                        </label>
                    </div>

                    <fieldset>
                        <legend class="text-xs font-semibold text-neutral-700">Si el dato ya existe</legend>
                        <div class="mt-2 space-y-2">
                            <label class="flex gap-2 rounded-panel border border-hairline px-3 py-2 text-sm">
                                <input type="radio" name="politica-carga" value="conservar" x-model="politica" @change="limpiarRevision()" class="mt-0.5 text-brand-600">
                                <span><span class="font-semibold text-neutral-900">Conservar lo ya trabajado.</span> <span class="text-neutral-600">Solo llena lo que sigue en Pendiente y no pisa tributos ya guardados.</span></span>
                            </label>
                            <label class="flex gap-2 rounded-panel border border-hairline px-3 py-2 text-sm">
                                <input type="radio" name="politica-carga" value="reemplazar" x-model="politica" @change="limpiarRevision()" class="mt-0.5 text-brand-600">
                                <span><span class="font-semibold text-neutral-900">Reemplazar.</span> <span class="text-neutral-600">Actualiza estados e importes. No deja un importe por debajo de los pagos ya registrados y no borra tributos.</span></span>
                            </label>
                        </div>
                    </fieldset>
                </div>

                <div x-show="paso === 'revision' || paso === 'listo'" class="space-y-4">
                    <p class="text-sm font-semibold text-neutral-900" x-text="resultado ? resultado.resumen : ''" role="status"></p>
                    <template x-if="resultado && resultado.avisos && resultado.avisos.length">
                        <ul class="space-y-1 text-sm text-neutral-600">
                            <template x-for="(aviso, indice) in resultado.avisos" :key="'aviso-' + indice">
                                <li x-text="aviso"></li>
                            </template>
                        </ul>
                    </template>
                    <template x-if="resultado && resultado.muestra && resultado.muestra.length">
                        <ul class="max-h-48 space-y-1 overflow-y-auto rounded-panel border border-hairline bg-neutral-50 px-3 py-2 text-xs text-neutral-700">
                            <template x-for="(linea, indice) in resultado.muestra" :key="'muestra-' + indice">
                                <li x-text="linea"></li>
                            </template>
                        </ul>
                    </template>
                    <template x-if="resultado && resultado.errores && resultado.errores.length">
                        <ul class="space-y-1 rounded-panel border border-danger-200 bg-danger-50 px-3 py-2 text-sm text-danger-700" role="alert">
                            <template x-for="(error, indice) in resultado.errores" :key="'error-' + indice">
                                <li x-text="error.mensaje"></li>
                            </template>
                        </ul>
                    </template>
                    <p x-show="paso === 'listo'" class="text-sm text-neutral-600">Quedó registrado con el mismo guardado de la matriz. Al actualizar verá los estados, Control de deudas y el libro de impuestos de cada cliente.</p>
                </div>

                <p x-show="mensaje" class="mt-4 text-sm text-danger-700" role="alert" x-text="mensaje"></p>
            </div>

            <div class="flex items-center justify-end gap-2 border-t border-hairline bg-neutral-50 px-6 py-4">
                <button type="button" x-show="paso !== 'elegir' && paso !== 'listo'" @click="paso = 'elegir'" class="rounded-control px-3 py-1.5 text-xs font-semibold text-neutral-700 hover:bg-neutral-200" :disabled="aplicando">Volver</button>
                <button type="button" x-show="paso === 'elegir'" @click="revisar()" class="rounded-control bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50" :disabled="revisando" x-text="revisando ? 'Revisando...' : 'Revisar antes de guardar'"></button>
                <button type="button" x-show="paso === 'revision' && resultado && resultado.puede_aplicar" @click="aplicar()" class="rounded-control bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700 disabled:opacity-50" :disabled="aplicando" x-text="aplicando ? 'Guardando...' : 'Guardar carga'"></button>
                <button type="button" x-show="paso === 'listo'" @click="window.location.reload()" class="rounded-control bg-brand-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-brand-700">Actualizar la matriz</button>
            </div>
        </div>
    </div>
</div>
