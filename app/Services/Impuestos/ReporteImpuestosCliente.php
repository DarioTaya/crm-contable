<?php

namespace App\Services\Impuestos;

use App\Enums\RegimenTributario;
use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Libro anual de tributos de un cliente.
 *
 * Lee las mismas deudas y abonos de Control de Impuestos. No recalcula vencimientos
 * ni completa importes que el contador no registró: un periodo sin tributo queda
 * como "Sin determinar", no como S/ 0.00.
 */
class ReporteImpuestosCliente
{
    /**
     * @var array<int, array{corto: string, nombre: string}>
     */
    private const MESES = [
        1 => ['corto' => 'ENE', 'nombre' => 'Enero'],
        2 => ['corto' => 'FEB', 'nombre' => 'Febrero'],
        3 => ['corto' => 'MAR', 'nombre' => 'Marzo'],
        4 => ['corto' => 'ABR', 'nombre' => 'Abril'],
        5 => ['corto' => 'MAY', 'nombre' => 'Mayo'],
        6 => ['corto' => 'JUN', 'nombre' => 'Junio'],
        7 => ['corto' => 'JUL', 'nombre' => 'Julio'],
        8 => ['corto' => 'AGO', 'nombre' => 'Agosto'],
        9 => ['corto' => 'SET', 'nombre' => 'Setiembre'],
        10 => ['corto' => 'OCT', 'nombre' => 'Octubre'],
        11 => ['corto' => 'NOV', 'nombre' => 'Noviembre'],
        12 => ['corto' => 'DIC', 'nombre' => 'Diciembre'],
    ];

    public function __construct(private EvaluadorPagoTributo $evaluador) {}

    /**
     * @return array{
     *     anio: int,
     *     anios: list<int>,
     *     resumen: array{determinado_621: string, determinado_601: string, pagado: string, saldo: string, fuera_de_fecha: int, con_deuda: int},
     *     bloques: list<array<string, mixed>>
     * }
     */
    public function armar(Cliente $cliente, int $anio): array
    {
        $hoy = now('America/Lima')->toDateString();
        $mesBaja = $this->mesDeBaja($cliente);
        $operaciones = $this->operacionesDelAnio($cliente, $anio);
        $porCodigo = $this->agruparLineas($operaciones);

        $codigosConDatos = array_keys($porCodigo);
        $bloques = [];
        $resumen = [
            'determinado_621' => 0,
            'determinado_601' => 0,
            'pagado' => 0,
            'saldo' => 0,
            'fuera_de_fecha' => 0,
            'con_deuda' => 0,
        ];

        foreach (TipoTributoSunat::catalogoFormulario() as $bloque) {
            $codigos = $this->codigosVisibles($cliente, $bloque['id'], $codigosConDatos);
            $tributos = [];

            foreach ($codigos as $codigo) {
                $armado = $this->armarTributo($codigo, $anio, $porCodigo[$codigo] ?? [], $mesBaja, $hoy);
                $tributos[] = $armado['tributo'];
                $this->sumarResumen($resumen, $bloque['id'], $armado['centavos']);
            }

            $bloques[] = [
                'id' => $bloque['id'],
                'titulo' => $bloque['titulo'],
                'detalle' => $bloque['detalle'],
                'mensaje' => $tributos === [] ? $this->mensajeVacio($cliente, $bloque['id']) : null,
                'tributos' => $tributos,
            ];
        }

        $hayImportes = ($resumen['determinado_621'] + $resumen['determinado_601']) > 0;

        return [
            'anio' => $anio,
            'anios' => $this->aniosDisponibles($cliente, $anio),
            'resumen' => [
                'determinado_621' => $resumen['determinado_621'] > 0 ? $this->soles($resumen['determinado_621']) : '—',
                'determinado_601' => $resumen['determinado_601'] > 0 ? $this->soles($resumen['determinado_601']) : '—',
                'pagado' => $hayImportes ? $this->soles($resumen['pagado']) : '—',
                'saldo' => $hayImportes ? $this->soles($resumen['saldo']) : '—',
                'fuera_de_fecha' => $resumen['fuera_de_fecha'],
                'con_deuda' => $resumen['con_deuda'],
            ],
            'bloques' => $bloques,
        ];
    }

    /**
     * @return Collection<string, Operacion>
     */
    private function operacionesDelAnio(Cliente $cliente, int $anio): Collection
    {
        $periodos = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $periodos[] = sprintf('%02d-%d', $mes, $anio);
        }

        return Operacion::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('periodo', $periodos)
            ->with(['deudasTributarias.abonos', 'abonosDeuda'])
            ->get()
            ->keyBy('periodo');
    }

    /**
     * @param  Collection<string, Operacion>  $operaciones
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function agruparLineas(Collection $operaciones): array
    {
        $porCodigo = [];

        foreach ($operaciones as $operacion) {
            foreach ($this->lineasDe($operacion) as $linea) {
                $porCodigo[$linea['codigo']][$operacion->periodo] = $linea;
            }
        }

        return $porCodigo;
    }

    /**
     * @return list<array{codigo: string, monto: float, vencimiento: ?string, es_igv_justo: bool, pagos: list<array<string, mixed>>}>
     */
    private function lineasDe(Operacion $operacion): array
    {
        $deudas = $operacion->deudasTributarias;

        if ($deudas->isEmpty()) {
            $monto = (float) ($operacion->monto_deuda_inicial ?? 0);
            if ($monto <= 0 && $operacion->abonosDeuda->isEmpty()) {
                return [];
            }

            $codigo = $operacion->tributo_codigo ?: TipoTributoSunat::Igv18->value;

            return [[
                'codigo' => $codigo,
                'monto' => $monto,
                'vencimiento' => $operacion->vencimiento_deuda?->format('Y-m-d'),
                'es_igv_justo' => $codigo === TipoTributoSunat::Igv18->value && (bool) $operacion->es_igv_justo,
                'pagos' => $this->pagosDe($operacion->abonosDeuda),
            ]];
        }

        $deudasLista = $deudas->values();
        $ids = $deudasLista->map(fn (DeudaTributaria $deuda): int => (int) $deuda->id)->all();
        $huerfanos = $operacion->abonosDeuda->filter(
            fn (AbonoDeuda $abono): bool => $abono->deuda_tributaria_id === null
                || ! in_array((int) $abono->deuda_tributaria_id, $ids, true)
        );

        $lineas = [];
        foreach ($deudasLista as $deuda) {
            $lineas[] = [
                'codigo' => (string) $deuda->tributo_codigo,
                'monto' => (float) $deuda->monto_deuda_inicial,
                'vencimiento' => $deuda->vencimiento_deuda?->format('Y-m-d'),
                'es_igv_justo' => (bool) $deuda->es_igv_justo,
                'pagos' => $this->pagosDe($deuda->abonos),
            ];
        }

        if ($huerfanos->isNotEmpty()) {
            $destino = $this->indiceDestino($lineas, $operacion->tributo_codigo);
            if ($destino !== null) {
                $lineas[$destino]['pagos'] = $this->pagosDe(
                    $deudasLista[$destino]->abonos->concat($huerfanos)->unique('id')
                );
            }
        }

        return $lineas;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private function indiceDestino(array $lineas, ?string $tributoCodigo): ?int
    {
        foreach ($lineas as $indice => $linea) {
            if ($tributoCodigo !== null && $linea['codigo'] === $tributoCodigo) {
                return $indice;
            }
        }

        return count($lineas) === 1 ? 0 : null;
    }

    /**
     * @param  iterable<int, AbonoDeuda>  $abonos
     * @return list<array{id: int, monto: float, fecha: string, medio: string, observacion: string}>
     */
    private function pagosDe(iterable $abonos): array
    {
        $pagos = [];

        foreach ($abonos as $abono) {
            $pagos[] = [
                'id' => (int) $abono->id,
                'monto' => (float) $abono->monto,
                'fecha' => $abono->fecha_pago?->format('Y-m-d') ?? '',
                'medio' => $abono->medio_pago?->etiqueta() ?? 'Sin clasificar',
                'observacion' => trim((string) ($abono->observacion ?? '')),
            ];
        }

        usort($pagos, fn (array $a, array $b): int => [$a['fecha'], $a['id']] <=> [$b['fecha'], $b['id']]);

        return $pagos;
    }

    /**
     * @param  list<string>  $codigosConDatos
     * @return list<string>
     */
    private function codigosVisibles(Cliente $cliente, string $declaracion, array $codigosConDatos): array
    {
        $catalogo = collect(TipoTributoSunat::catalogoFormulario())->firstWhere('id', $declaracion);
        $orden = array_column($catalogo['tributos'] ?? [], 'codigo');
        $fijos = [];

        if ($declaracion === 'pdt_621' && $cliente->regimen_tributario !== RegimenTributario::Rus) {
            $fijos[] = TipoTributoSunat::Igv18->value;
            $renta = TipoTributoSunat::rentaDelRegimen($cliente->regimen_tributario);
            if ($renta instanceof TipoTributoSunat) {
                $fijos[] = $renta->value;
            }
        }

        $conDatos = array_values(array_filter(
            $codigosConDatos,
            fn (string $codigo): bool => TipoTributoSunat::declaracionDe($codigo) === $declaracion,
        ));

        $codigos = array_values(array_unique([...$fijos, ...$conDatos]));

        usort($codigos, function (string $a, string $b) use ($orden): int {
            $indiceA = array_search($a, $orden, true);
            $indiceB = array_search($b, $orden, true);

            return ($indiceA === false ? 1000 : $indiceA) <=> ($indiceB === false ? 1000 : $indiceB);
        });

        return $codigos;
    }

    /**
     * @param  array<string, array<string, mixed>>  $lineas
     * @return array{tributo: array<string, mixed>, centavos: array{determinado: int, pagado: int, saldo: int, fuera_de_fecha: int, con_deuda: int}}
     */
    private function armarTributo(string $codigo, int $anio, array $lineas, ?string $mesBaja, string $hoy): array
    {
        $enum = TipoTributoSunat::resolver($codigo);
        $periodos = [];
        $determinado = 0;
        $pagado = 0;
        $saldo = 0;
        $fuera = 0;
        $conDeuda = 0;
        $cantidadPagos = 0;
        $registrados = 0;

        for ($mes = 1; $mes <= 12; $mes++) {
            $periodo = sprintf('%02d-%d', $mes, $anio);
            $comparable = sprintf('%04d-%02d', $anio, $mes);
            $linea = $lineas[$periodo] ?? null;
            $esBaja = $mesBaja !== null && $comparable >= $mesBaja;

            if ($linea === null) {
                $periodos[] = $this->filaVacia($periodo, $anio, $mes, $esBaja);

                continue;
            }

            if ($this->centavos($linea['monto']) <= 0) {
                $periodos[] = $this->filaVacia($periodo, $anio, $mes, $esBaja, $linea['pagos']);

                continue;
            }

            $resultado = $this->evaluador->evaluar(
                $linea['monto'],
                $linea['vencimiento'],
                $linea['pagos'],
                $hoy,
            );

            $registrados++;
            $determinado += $this->centavos($linea['monto']);
            $pagado += $resultado->pagadoCentavos;
            $saldo += $resultado->saldoCentavos;
            $cantidadPagos += $resultado->cantidadPagos;
            if ($resultado->cuentaComoFueraDeFecha()) {
                $fuera++;
            }
            if ($resultado->debe) {
                $conDeuda++;
            }

            $periodos[] = [
                'periodo' => $periodo,
                'etiqueta' => self::MESES[$mes]['corto'].'-'.substr((string) $anio, -2),
                'mes' => self::MESES[$mes]['nombre'],
                'registrado' => true,
                'situacion' => $resultado->situacion,
                'situacion_etiqueta' => $resultado->etiqueta,
                'debe' => $resultado->debe,
                'es_igv_justo' => (bool) $linea['es_igv_justo'],
                'determinado' => $this->soles($this->centavos($linea['monto'])),
                'vencimiento' => $this->dmy($linea['vencimiento']),
                'cantidad_pagos' => $resultado->cantidadPagos,
                'pagos' => array_map(fn (array $pago): array => [
                    'fecha' => $this->dmy($pago['fecha']) ?? '—',
                    'monto' => $this->soles($this->centavos($pago['monto'])),
                    'medio' => $pago['medio'],
                    'observacion' => $pago['observacion'],
                ], $linea['pagos']),
                'fecha_cancelacion' => $this->dmy($resultado->fechaCancelacion),
                'saldo' => $this->soles($resultado->saldoCentavos),
                'pagado' => $this->soles($resultado->pagadoCentavos),
            ];
        }

        return [
            'tributo' => [
                'codigo' => $codigo,
                'nombre' => $enum?->nombreCorto() ?? $codigo,
                'descripcion' => $enum?->descripcion() ?? $codigo,
                'periodos' => $periodos,
                'tiene_importes' => $registrados > 0,
                'totales' => [
                    'determinado' => $registrados > 0 ? $this->soles($determinado) : null,
                    'pagado' => $registrados > 0 ? $this->soles($pagado) : null,
                    'saldo' => $registrados > 0 ? $this->soles($saldo) : null,
                    'pagos' => $cantidadPagos,
                    'fuera_de_fecha' => $fuera,
                    'con_deuda' => $conDeuda,
                ],
            ],
            'centavos' => [
                'determinado' => $determinado,
                'pagado' => $pagado,
                'saldo' => $saldo,
                'fuera_de_fecha' => $fuera,
                'con_deuda' => $conDeuda,
            ],
        ];
    }

    /**
     * @param  list<array{id: int, monto: float, fecha: string, medio: string, observacion: string}>  $pagos
     * @return array<string, mixed>
     */
    private function filaVacia(string $periodo, int $anio, int $mes, bool $esBaja, array $pagos = []): array
    {
        $tienePagos = $pagos !== [];
        $marcarBaja = $esBaja && ! $tienePagos;

        return [
            'periodo' => $periodo,
            'etiqueta' => self::MESES[$mes]['corto'].'-'.substr((string) $anio, -2),
            'mes' => self::MESES[$mes]['nombre'],
            'registrado' => false,
            'situacion' => $marcarBaja ? 'baja' : EvaluadorPagoTributo::SIN_DETERMINAR,
            'situacion_etiqueta' => $marcarBaja ? 'Baja' : 'Sin determinar',
            'debe' => false,
            'es_igv_justo' => false,
            'determinado' => null,
            'vencimiento' => null,
            'cantidad_pagos' => count($pagos),
            'pagos' => array_map(fn (array $pago): array => [
                'fecha' => $this->dmy($pago['fecha']) ?? '—',
                'monto' => $this->soles($this->centavos($pago['monto'])),
                'medio' => $pago['medio'],
                'observacion' => $pago['observacion'],
            ], $pagos),
            'fecha_cancelacion' => null,
            'saldo' => null,
            'pagado' => $tienePagos ? $this->soles(array_sum(array_map(fn (array $pago): int => $this->centavos($pago['monto']), $pagos))) : null,
        ];
    }

    /**
     * @param  array{determinado_621: int, determinado_601: int, pagado: int, saldo: int, fuera_de_fecha: int, con_deuda: int}  $resumen
     * @param  array{determinado: int, pagado: int, saldo: int, fuera_de_fecha: int, con_deuda: int}  $centavos
     */
    private function sumarResumen(array &$resumen, string $declaracion, array $centavos): void
    {
        if ($declaracion === 'pdt_621') {
            $resumen['determinado_621'] += $centavos['determinado'];
        }
        if ($declaracion === 'pdt_601') {
            $resumen['determinado_601'] += $centavos['determinado'];
        }

        $resumen['pagado'] += $centavos['pagado'];
        $resumen['saldo'] += $centavos['saldo'];
        $resumen['fuera_de_fecha'] += $centavos['fuera_de_fecha'];
        $resumen['con_deuda'] += $centavos['con_deuda'];
    }

    private function mensajeVacio(Cliente $cliente, string $declaracion): string
    {
        if ($declaracion === 'pdt_621' && $cliente->regimen_tributario === RegimenTributario::Rus) {
            return 'NRUS no presenta PDT 621. Si en este ejercicio se registró un importe, aparece aquí.';
        }

        if ($declaracion === 'pdt_601') {
            return 'Este ejercicio no tiene tributos de PLAME. EsSalud, ONP, AFP y retenciones aparecen cuando se registran en Control de Impuestos.';
        }

        return 'Sin importes registrados en este ejercicio.';
    }

    /**
     * @return list<int>
     */
    private function aniosDisponibles(Cliente $cliente, int $anio): array
    {
        $anioActual = (int) now()->year;
        $desdeDatos = Operacion::query()
            ->where('cliente_id', $cliente->id)
            ->pluck('periodo')
            ->map(fn (mixed $periodo): int => (int) substr((string) $periodo, -4))
            ->filter(fn (int $valor): bool => $valor >= 2000 && $valor <= 2100);

        return $desdeDatos
            ->merge(range($anioActual - 2, $anioActual + 1))
            ->push($anio)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function mesDeBaja(Cliente $cliente): ?string
    {
        if (! $cliente->isRetirado()) {
            return null;
        }

        if (is_string($cliente->mes_baja) && $cliente->mes_baja !== '') {
            return $cliente->mes_baja;
        }

        return $cliente->fecha_baja?->format('Y-m');
    }

    private function centavos(float|int|string $monto): int
    {
        return (int) round(((float) $monto) * 100);
    }

    private function soles(int $centavos): string
    {
        return 'S/ '.number_format($centavos / 100, 2);
    }

    private function dmy(?string $iso): ?string
    {
        if ($iso === null || $iso === '') {
            return null;
        }

        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $iso);

        return $fecha instanceof DateTimeImmutable ? $fecha->format('d/m/Y') : null;
    }
}
