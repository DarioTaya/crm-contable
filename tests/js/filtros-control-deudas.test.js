import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import {
    fechaLegible,
    filtrarDeudas,
    filtrosDesdeParametros,
    opcionesFaceta,
    opcionCoincideBusqueda,
    parametrosDesdeFiltros,
    rangoInvertido,
    rangoPreset,
} from '../../resources/js/filtros-control-deudas.js';

const deudas = [
    deuda(1, 1, 'Alpha SAC', '20111111111', '10', '08-2026', '1011', 'IGV 18%', '2026-09-18'),
    deuda(2, 1, 'Alpha SAC', '20111111111', '10', '08-2026', '3121', 'Renta MYPE', '2026-09-18'),
    deuda(3, 1, 'Alpha SAC', '20111111111', '10', '09-2026', '1011', 'IGV 18%', '2026-10-20'),
    deuda(4, 2, 'Beta EIRL', '20222222222', '11', '08-2026', '1011', 'IGV 18%', '2026-09-22'),
    deuda(5, 2, 'Beta EIRL', '20222222222', '11', '07-2026', '5210', 'EsSalud', null),
    deuda(6, 3, 'José & Cía', '20333333333', '12', '12-2025', '1011', 'IGV 18%', '2026-01-15'),
];

function deuda(id, clienteId, razon, ruc, codigo, periodo, tributo, nombre, vencimiento) {
    return {
        id,
        cliente_id: clienteId,
        cliente_razon: razon,
        cliente_ruc: ruc,
        cliente_codigo: codigo,
        periodo,
        tributo_codigo: tributo,
        tributo_nombre_corto: nombre,
        tributo_nombre: nombre,
        vencimiento,
    };
}

function vacios(extra = {}) {
    return {
        clienteIds: null,
        denominaciones: [],
        periodos: [],
        tributos: [],
        venceDesde: '',
        venceHasta: '',
        ...extra,
    };
}

function ids(lista) {
    return lista.map((item) => item.id);
}

describe('filtros compuestos de control de deudas', () => {
    it('sin criterios devuelve todas las deudas', () => {
        assert.deepEqual(ids(filtrarDeudas(deudas, vacios())), [1, 2, 3, 4, 5, 6]);
    });

    it('una lista vacia de clientes no muestra deudas', () => {
        assert.deepEqual(ids(filtrarDeudas(deudas, vacios({ clienteIds: [] }))), []);
    });

    it('el filtro de clientes se cruza con la denominacion', () => {
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ clienteIds: ['2'], denominaciones: ['1'] }))),
            [],
        );
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ clienteIds: [1, 2], denominaciones: ['1'] }))),
            [1, 2, 3],
        );
    });

    it('encadena denominacion, periodo, tributo y vencimiento en cualquier orden', () => {
        const porDenominacion = filtrarDeudas(deudas, vacios({ denominaciones: ['1'] }));
        const porPeriodo = filtrarDeudas(porDenominacion, vacios({ periodos: ['08-2026'] }));
        const porTributo = filtrarDeudas(porPeriodo, vacios({ tributos: ['1011'] }));
        const porFecha = filtrarDeudas(porTributo, vacios({
            venceDesde: '2026-09-01',
            venceHasta: '2026-09-30',
        }));

        const deUnaVez = filtrarDeudas(deudas, vacios({
            denominaciones: [1],
            periodos: ['08-2026'],
            tributos: ['1011'],
            venceDesde: '2026-09-01',
            venceHasta: '2026-09-30',
        }));

        const primeroPeriodo = filtrarDeudas(deudas, vacios({
            periodos: ['08-2026'],
            tributos: ['1011'],
            venceDesde: '2026-09-01',
            venceHasta: '2026-09-30',
            denominaciones: ['1'],
        }));

        assert.deepEqual(ids(porDenominacion), [1, 2, 3]);
        assert.deepEqual(ids(porPeriodo), [1, 2]);
        assert.deepEqual(ids(porTributo), [1]);
        assert.deepEqual(ids(porFecha), [1]);
        assert.deepEqual(ids(deUnaVez), [1]);
        assert.deepEqual(ids(primeroPeriodo), [1]);
    });

    it('dentro de un criterio las opciones marcadas se suman', () => {
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ denominaciones: ['1', '3'] }))),
            [1, 2, 3, 6],
        );
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({
                denominaciones: ['1', '2'],
                periodos: ['08-2026', '07-2026'],
            }))),
            [1, 2, 4, 5],
        );
    });

    it('no confunde un codigo de tributo con otro que lo contiene', () => {
        const conCodigoLargo = [
            ...deudas,
            deuda(7, 1, 'Alpha SAC', '20111111111', '10', '08-2026', '10110', 'Otro', '2026-09-18'),
        ];

        assert.deepEqual(
            ids(filtrarDeudas(conCodigoLargo, vacios({ tributos: ['1011'] }))),
            [1, 3, 4, 6],
        );
    });

    it('acota el vencimiento por un solo extremo y excluye fechas vacias', () => {
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ venceDesde: '2026-10-01' }))),
            [3],
        );
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ venceHasta: '2026-01-31' }))),
            [6],
        );
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ venceDesde: '2026-09-01', venceHasta: '2026-09-30' }))),
            [1, 2, 4],
        );
        assert.equal(
            filtrarDeudas(deudas, vacios({ venceDesde: '2026-09-01' })).some((item) => item.id === 5),
            false,
        );
    });

    it('un rango invertido no deja pasar deudas', () => {
        assert.equal(rangoInvertido('2026-10-02', '2026-09-01'), true);
        assert.deepEqual(
            ids(filtrarDeudas(deudas, vacios({ venceDesde: '2026-10-02', venceHasta: '2026-09-01' }))),
            [],
        );
    });

    it('las opciones de cada filtro respetan los demas criterios', () => {
        const periodos = opcionesFaceta(deudas, vacios({ denominaciones: ['1'], periodos: ['08-2026'] }), 'periodo')
            .map((opcion) => [opcion.valor, opcion.cantidad]);
        const tributos = opcionesFaceta(deudas, vacios({ denominaciones: ['1'], periodos: ['08-2026'] }), 'tributo')
            .map((opcion) => [opcion.valor, opcion.cantidad]);
        const empresas = opcionesFaceta(deudas, vacios({ clienteIds: ['2'], periodos: ['08-2026'] }), 'denominacion')
            .map((opcion) => opcion.etiqueta);

        assert.deepEqual(periodos, [['09-2026', 1], ['08-2026', 2]]);
        assert.deepEqual(tributos, [['1011', 1], ['3121', 1]]);
        assert.deepEqual(empresas, ['Beta EIRL']);
    });

    it('conserva visible una opcion ya marcada aunque los otros filtros la dejen en cero', () => {
        const periodos = opcionesFaceta(deudas, vacios({
            denominaciones: ['3'],
            periodos: ['08-2026'],
        }), 'periodo');

        assert.deepEqual(
            periodos.map((opcion) => [opcion.valor, opcion.cantidad]),
            [['08-2026', 0], ['12-2025', 1]],
        );
    });

    it('ordena los periodos del mas reciente al mas antiguo', () => {
        assert.deepEqual(
            opcionesFaceta(deudas, vacios(), 'periodo').map((opcion) => opcion.valor),
            ['09-2026', '08-2026', '07-2026', '12-2025'],
        );
    });

    it('encuentra una denominacion aunque la busqueda no lleve tildes', () => {
        const opcion = opcionesFaceta(deudas, vacios(), 'denominacion').find((item) => item.valor === '3');

        assert.equal(opcionCoincideBusqueda(opcion, 'jose'), true);
        assert.equal(opcionCoincideBusqueda(opcion, '20333333333'), true);
        assert.equal(opcionCoincideBusqueda(opcion, 'alpha'), false);
    });

    it('restaura los filtros desde la url y descarta valores ajenos', () => {
        const params = parametrosDesdeFiltros(vacios({
            denominaciones: ['2', '1'],
            periodos: ['09-2026'],
            tributos: ['3121'],
            venceDesde: '2026-09-01',
            venceHasta: '2026-09-30',
            clienteIds: ['9'],
        }));

        assert.equal(params.get('denominacion'), '2,1');
        assert.equal(params.has('clientes'), false);
        assert.deepEqual(filtrosDesdeParametros(params, deudas), {
            denominaciones: ['2', '1'],
            periodos: ['09-2026'],
            tributos: ['3121'],
            venceDesde: '2026-09-01',
            venceHasta: '2026-09-30',
        });

        const ajenos = new URLSearchParams('denominacion=1,999&periodo=13-2026&tributo=1011,9999&vence_desde=18/09/2026&vence_hasta=2026-09-30');
        assert.deepEqual(filtrosDesdeParametros(ajenos, deudas), {
            denominaciones: ['1'],
            periodos: [],
            tributos: ['1011'],
            venceDesde: '',
            venceHasta: '2026-09-30',
        });
    });

    it('calcula presets de vencimiento con dias inclusivos', () => {
        assert.deepEqual(rangoPreset('vencidas', '2026-09-26'), {
            venceDesde: '',
            venceHasta: '2026-09-25',
        });
        assert.deepEqual(rangoPreset('proximos_7', '2026-09-26'), {
            venceDesde: '2026-09-26',
            venceHasta: '2026-10-02',
        });
        assert.deepEqual(rangoPreset('mes', '2026-02-10'), {
            venceDesde: '2026-02-01',
            venceHasta: '2026-02-28',
        });
        assert.deepEqual(rangoPreset('mes', '2024-02-10'), {
            venceDesde: '2024-02-01',
            venceHasta: '2024-02-29',
        });
        assert.equal(fechaLegible('2026-09-26'), '26/09/2026');
        assert.equal(rangoPreset('mes', 'ayer'), null);
    });
});
