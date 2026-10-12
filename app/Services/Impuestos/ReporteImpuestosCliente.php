<?php

namespace App\Services\Impuestos;

use App\Enums\RegimenTributario;
use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use App\Services\Catalogos\CatalogoSunat;
use App\Services\CronogramaSunatService;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Libro anual de tributos de un cliente.
 *
 * Lee las mismas deudas y abonos de Control de Impuestos. No recalcula vencimientos.
 * Un tributo del PDT 621 o del PDT 601 sin importe cargado se muestra en S/ 0.00.
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

    public function __construct(
        private EvaluadorPagoTributo $evaluador,
        private ?CronogramaSunatService $cronogramaService = null,
    ) {
        $this->cronogramaService = $cronogramaService ?? app(CronogramaSunatService::class);
    }

    /**
     * @return array{
     *     anio: int,
     *     anios: list<int>,
     *     resumen: array{determinado_621: string, determinado_601: string, pagado: string, saldo: string, fuera_de_fecha: int, con_deuda: int},
     *     analisis_regimen: array<string, mixed>,
     *     bloques: list<array<string, mixed>>
     * }
     */
    public function armar(Cliente $cliente, int $anio): array
    {
        $hoy = now('America/Lima')->toDateString();
        $mesBaja = $this->mesDeBaja($cliente);
        $operaciones = $this->operacionesDelAnio($cliente, $anio);
        $porCodigo = $this->agruparLineas($operaciones);
        $analisisRegimen = $this->analizarRegimenesDelEjercicio($cliente, $anio, $operaciones, $porCodigo);

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

        foreach (app(CatalogoSunat::class)->catalogoFormulario($cliente->estudio_id) as $bloque) {
            $codigos = $this->codigosVisibles($cliente, $bloque['id'], $codigosConDatos);
            $tributos = [];

            foreach ($codigos as $codigo) {
                $armado = $this->armarTributo($codigo, $anio, $porCodigo[$codigo] ?? [], $mesBaja, $hoy);
                $tributos[] = $armado['tributo'];
                $this->sumarResumen($resumen, $bloque['id'], $armado['centavos']);
            }

            $filasPeriodos = $this->armarFilasPeriodos($bloque['id'], $anio, $tributos, $operaciones, $mesBaja, $hoy, $cliente, $analisisRegimen);
            $totalesBloque = $this->calcularTotalesBloque($filasPeriodos, $bloque['id']);

            $bloques[] = [
                'id' => $bloque['id'],
                'titulo' => $bloque['titulo'],
                'detalle' => $bloque['detalle'],
                'mensaje' => $tributos === [] ? $this->mensajeVacio($cliente, $bloque['id']) : null,
                'tributos' => $tributos,
                'filas_periodos' => $filasPeriodos,
                'totales' => $totalesBloque,
                'tiene_datos' => $totalesBloque['determinado_centavos'] > 0 || $totalesBloque['pagado_centavos'] > 0 || $totalesBloque['cantidad_pagos'] > 0,
            ];
        }

        return [
            'anio' => $anio,
            'anios' => $this->aniosDisponibles($cliente, $anio),
            'resumen' => [
                'determinado_621' => $this->soles($resumen['determinado_621']),
                'determinado_601' => $this->soles($resumen['determinado_601']),
                'pagado' => $this->soles($resumen['pagado']),
                'saldo' => $this->soles($resumen['saldo']),
                'fuera_de_fecha' => $resumen['fuera_de_fecha'],
                'con_deuda' => $resumen['con_deuda'],
            ],
            'analisis_regimen' => $analisisRegimen,
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
            ->with(['deudasTributarias.abonos', 'abonosDeuda', 'liquidacionPeriodo'])
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
     * @return list<array{codigo: string, monto: float, vencimiento: ?string, es_igv_justo: bool, pagos: list<array<string, mixed>>, es_declarado?: bool, es_zona_selva?: bool, saldo_favor?: float}>
     */
    private function lineasDe(Operacion $operacion): array
    {
        $deudas = $operacion->deudasTributarias;
        $liq = $operacion->liquidacionPeriodo;

        $lineas = [];

        if ($deudas->isEmpty()) {
            $monto = (float) ($operacion->monto_deuda_inicial ?? 0);
            if ($monto > 0 || $operacion->abonosDeuda->isNotEmpty()) {
                $codigo = $operacion->tributo_codigo ?: TipoTributoSunat::Igv18->value;
                $lineas[] = [
                    'codigo' => $codigo,
                    'monto' => $monto,
                    'vencimiento' => $operacion->vencimiento_deuda?->format('Y-m-d'),
                    'es_igv_justo' => $codigo === TipoTributoSunat::Igv18->value && (bool) $operacion->es_igv_justo,
                    'pagos' => $this->pagosDe($operacion->abonosDeuda),
                ];
            }
        } else {
            $deudasLista = $deudas->values();
            $ids = $deudasLista->map(fn (DeudaTributaria $deuda): int => (int) $deuda->id)->all();
            $huerfanos = $operacion->abonosDeuda->filter(
                fn (AbonoDeuda $abono): bool => $abono->deuda_tributaria_id === null
                    || ! in_array((int) $abono->deuda_tributaria_id, $ids, true)
            );

            foreach ($deudasLista as $deuda) {
                $codigo = (string) $deuda->tributo_codigo;
                $tasa = null;
                if (in_array($codigo, ['3111', '3121', '3031'], true) && $liq) {
                    $tasa = (float) $liq->renta_tasa;
                }
                $lineas[] = [
                    'codigo' => $codigo,
                    'monto' => (float) $deuda->monto_deuda_inicial,
                    'vencimiento' => $deuda->vencimiento_deuda?->format('Y-m-d'),
                    'es_igv_justo' => (bool) $deuda->es_igv_justo,
                    'pagos' => $this->pagosDe($deuda->abonos),
                    'tasa' => $tasa,
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
        }

        // Si existe liquidación guardada, incorporar informativamente los tributos determinados con saldo a favor o en cero
        if ($liq) {
            $codigosExistentes = array_column($lineas, 'codigo');

            // IGV 1011
            if (! in_array(TipoTributoSunat::Igv18->value, $codigosExistentes, true) && (float) $liq->igv_resultante_pagar <= 0) {
                $lineas[] = [
                    'codigo' => TipoTributoSunat::Igv18->value,
                    'monto' => 0.0,
                    'vencimiento' => null,
                    'es_igv_justo' => false,
                    'pagos' => [],
                    'es_declarado' => true,
                    'es_zona_selva' => (bool) $liq->es_zona_selva,
                    'saldo_favor' => (float) $liq->igv_saldo_favor_siguiente,
                ];
            }

            $rentaCodigo = $liq->renta_tributo_codigo
                ?: app(CatalogoSunat::class)->codigoRentaDelRegimen($operacion->cliente?->regimen_tributario, $operacion->estudio_id);
            if ($rentaCodigo && ! in_array($rentaCodigo, $codigosExistentes, true) && (float) $liq->renta_resultante_pagar <= 0) {
                $lineas[] = [
                    'codigo' => $rentaCodigo,
                    'monto' => 0.0,
                    'vencimiento' => null,
                    'es_igv_justo' => false,
                    'pagos' => [],
                    'es_declarado' => true,
                    'es_zona_selva' => false,
                    'saldo_favor' => (float) $liq->renta_saldo_favor_siguiente,
                    'tasa' => (float) $liq->renta_tasa,
                ];
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
            $etiquetaMedio = $abono->etiquetaMedioPago();
            $pagos[] = [
                'id' => (int) $abono->id,
                'monto' => (float) $abono->monto,
                'fecha' => $abono->fecha_pago?->format('Y-m-d') ?? '',
                'medio' => $etiquetaMedio !== '' ? $etiquetaMedio : 'Sin clasificar',
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
        $catalogo = app(CatalogoSunat::class);
        $bloque = collect($catalogo->catalogoFormulario($cliente->estudio_id))->firstWhere('id', $declaracion);
        $orden = array_column($bloque['tributos'] ?? [], 'codigo');

        $conDatos = array_values(array_filter(
            $codigosConDatos,
            fn (string $codigo): bool => $catalogo->declaracionDe($codigo, $cliente->estudio_id) === $declaracion,
        ));

        $regimen = $cliente->regimen_tributario instanceof RegimenTributario
            ? $cliente->regimen_tributario->siglaSunat()
            : strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario ?? ''));

        if ($declaracion === 'pdt_621') {
            $baseRegimen = match ($regimen) {
                'RER' => ['1011', '3111'],
                'RMT' => ['1011', '3121'],
                'RG', 'GENERAL' => ['1011', '3031', '3081'],
                'RUS', 'NRUS' => [],
                default => ['1011', '3121'],
            };

            // Inteligente: combina los tributos base del régimen actual con cualquier tributo
            // que la empresa haya utilizado en el ejercicio (ej. si inició como RER y ascendió a RMT,
            // figurarán tanto 3111 como 3121)
            $codigos = array_values(array_unique([...$baseRegimen, ...$conDatos]));
        } else {
            // PDT 601 PLAME:
            $codigos = in_array($regimen, ['RUS', 'NRUS'], true)
                ? $conDatos
                : array_values(array_unique([...$orden, ...$conDatos]));
        }

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
        $catalogo = app(CatalogoSunat::class);
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
                if (! empty($linea['es_declarado'])) {
                    $etiquetaSituacion = 'Declarado';
                    if (! empty($linea['es_zona_selva'])) {
                        $etiquetaSituacion = 'Exonerado (Selva)';
                    } elseif (! empty($linea['saldo_favor']) && $linea['saldo_favor'] > 0) {
                        $etiquetaSituacion = 'Saldo a favor (S/ '.number_format($linea['saldo_favor'], 2).')';
                    }

                    $periodos[] = [
                        'periodo' => $periodo,
                        'etiqueta' => self::MESES[$mes]['corto'].'-'.substr((string) $anio, -2),
                        'mes' => self::MESES[$mes]['nombre'],
                        'registrado' => true,
                        'situacion' => 'al_dia',
                        'situacion_etiqueta' => $etiquetaSituacion,
                        'debe' => false,
                        'es_igv_justo' => false,
                        'determinado' => $this->soles(0),
                        'determinado_centavos' => 0,
                        'vencimiento' => null,
                        'cantidad_pagos' => 0,
                        'pagos' => [],
                        'fecha_cancelacion' => null,
                        'saldo' => $this->soles(0),
                        'saldo_centavos' => 0,
                        'pagado' => $this->soles(0),
                        'pagado_centavos' => 0,
                        'tasa' => isset($linea['tasa']) && $linea['tasa'] > 0 ? (float) $linea['tasa'] : null,
                    ];

                    continue;
                }

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
                'determinado_centavos' => $this->centavos($linea['monto']),
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
                'saldo_centavos' => $resultado->saldoCentavos,
                'pagado' => $this->soles($resultado->pagadoCentavos),
                'pagado_centavos' => $resultado->pagadoCentavos,
                'tasa' => isset($linea['tasa']) && $linea['tasa'] > 0 ? (float) $linea['tasa'] : null,
            ];
        }

        return [
            'tributo' => [
                'codigo' => $codigo,
                'nombre' => $catalogo->nombreCorto($codigo),
                'descripcion' => $catalogo->nombre($codigo),
                'periodos' => $periodos,
                'tiene_importes' => $registrados > 0,
                'totales' => [
                    'determinado' => $this->soles($determinado),
                    'pagado' => $this->soles($pagado),
                    'saldo' => $this->soles($saldo),
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
        $pagadoCentavos = array_sum(array_map(fn (array $pago): int => $this->centavos($pago['monto']), $pagos));

        return [
            'periodo' => $periodo,
            'etiqueta' => self::MESES[$mes]['corto'].'-'.substr((string) $anio, -2),
            'mes' => self::MESES[$mes]['nombre'],
            'registrado' => false,
            'situacion' => $marcarBaja ? 'baja' : EvaluadorPagoTributo::SIN_DETERMINAR,
            'situacion_etiqueta' => $marcarBaja ? 'Baja' : 'Sin registrar',
            'debe' => false,
            'es_igv_justo' => false,
            'determinado' => $marcarBaja ? null : $this->soles(0),
            'determinado_centavos' => 0,
            'vencimiento' => null,
            'cantidad_pagos' => count($pagos),
            'pagos' => array_map(fn (array $pago): array => [
                'fecha' => $this->dmy($pago['fecha']) ?? '—',
                'monto' => $this->soles($this->centavos($pago['monto'])),
                'medio' => $pago['medio'],
                'observacion' => $pago['observacion'],
            ], $pagos),
            'fecha_cancelacion' => null,
            'saldo' => $marcarBaja ? null : $this->soles(0),
            'saldo_centavos' => 0,
            'pagado' => $marcarBaja ? null : $this->soles($pagadoCentavos),
            'pagado_centavos' => $pagadoCentavos,
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

    private function centavos(float|int|string|null $monto): int
    {
        if ($monto === null || $monto === '') {
            return 0;
        }

        if (is_string($monto)) {
            $limpio = preg_replace('/[^\d.-]/', '', str_replace(',', '', $monto));
            $monto = $limpio === '' ? 0.0 : (float) $limpio;
        }

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

    /**
     * @param  Collection<string, Operacion>  $operaciones
     * @param  array<string, array<string, array<string, mixed>>>  $porCodigo
     * @return array{
     *     regimen_actual: string,
     *     regimen_actual_nombre: string,
     *     hubo_transicion: bool,
     *     periodo_transicion: ?string,
     *     mes_transicion_nombre: ?string,
     *     mensaje_transicion: ?string,
     *     ventas_acumuladas_transicion: ?float,
     *     regimenes_por_mes: array<int, array{sigla: string, nombre: string, tasa: ?float, codigo: ?string, es_transicion: bool}>
     * }
     */
    private function analizarRegimenesDelEjercicio(Cliente $cliente, int $anio, Collection $operaciones, array $porCodigo): array
    {
        $regimenCliente = $cliente->regimen_tributario instanceof RegimenTributario
            ? $cliente->regimen_tributario->siglaSunat()
            : strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario ?? 'RMT'));

        if ($regimenCliente === 'NRUS') {
            $regimenCliente = 'RUS';
        }

        // Detectar los códigos de renta usados mes a mes
        $codigosRentaPorMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $periodo = sprintf('%02d-%d', $m, $anio);
            $codigoDetectado = null;

            // 1. Verificar liquidación del periodo
            $op = $operaciones[$periodo] ?? null;
            if ($op?->liquidacionPeriodo?->renta_tributo_codigo) {
                $codigoDetectado = (string) $op->liquidacionPeriodo->renta_tributo_codigo;
            }

            // 2. Verificar deudas tributarias de renta
            if (! $codigoDetectado && $op) {
                foreach ($op->deudasTributarias as $deuda) {
                    if (in_array((string) $deuda->tributo_codigo, ['3111', '3121', '3031'], true)) {
                        $codigoDetectado = (string) $deuda->tributo_codigo;
                        break;
                    }
                }
            }

            // 3. Verificar por código en operaciones agrupadas
            if (! $codigoDetectado) {
                foreach (['3111', '3121', '3031'] as $cRenta) {
                    if (isset($porCodigo[$cRenta][$periodo])) {
                        $codigoDetectado = $cRenta;
                        break;
                    }
                }
            }

            if (! $codigoDetectado && $op && in_array((string) $op->tributo_codigo, ['3111', '3121', '3031'], true)) {
                $codigoDetectado = (string) $op->tributo_codigo;
            }

            $codigosRentaPorMes[$m] = $codigoDetectado;
        }

        // Inferir transición (ej. comenzó con RER 3111 y pasó a RMT 3121 o RG 3031)
        $primerMes3121 = null;
        $primerMes3031 = null;
        $tiene3111 = false;
        foreach ($codigosRentaPorMes as $m => $cod) {
            if ($cod === '3111') {
                $tiene3111 = true;
            } elseif ($cod === '3121' && $primerMes3121 === null) {
                $primerMes3121 = $m;
            } elseif ($cod === '3031' && $primerMes3031 === null) {
                $primerMes3031 = $m;
            }
        }

        $huboTransicion = false;
        $mesCorteTransicion = null;
        $siglaDestino = null;

        if ($tiene3111 && $primerMes3121 !== null) {
            $huboTransicion = true;
            $mesCorteTransicion = $primerMes3121;
            $siglaDestino = 'RMT';
        } elseif ($tiene3111 && $primerMes3031 !== null) {
            $huboTransicion = true;
            $mesCorteTransicion = $primerMes3031;
            $siglaDestino = 'RG';
        }

        $regimenesPorMes = [];
        for ($m = 1; $m <= 12; $m++) {
            $cod = $codigosRentaPorMes[$m];
            $esTransicion = false;

            if ($huboTransicion) {
                if ($m < $mesCorteTransicion) {
                    $sigla = 'RER';
                    $nombre = 'RER 1.5%';
                    $tasa = 1.5;
                    $codFinal = '3111';
                } else {
                    $sigla = $siglaDestino;
                    $nombre = $siglaDestino === 'RMT' ? 'RMT 1%' : 'RG 1.5%';
                    $tasa = $siglaDestino === 'RMT' ? 1.0 : 1.5;
                    $codFinal = $siglaDestino === 'RMT' ? '3121' : '3031';
                    $esTransicion = ($m === $mesCorteTransicion);
                }
            } else {
                if ($cod === '3111') {
                    $sigla = 'RER';
                    $nombre = 'RER 1.5%';
                    $tasa = 1.5;
                    $codFinal = '3111';
                } elseif ($cod === '3121') {
                    $sigla = 'RMT';
                    $nombre = 'RMT 1%';
                    $tasa = 1.0;
                    $codFinal = '3121';
                } elseif ($cod === '3031') {
                    $sigla = 'RG';
                    $nombre = 'RG 1.5%';
                    $tasa = 1.5;
                    $codFinal = '3031';
                } else {
                    $sigla = match ($regimenCliente) {
                        'RER' => 'RER',
                        'RG', 'GENERAL' => 'RG',
                        'RUS', 'NRUS' => 'NRUS',
                        default => 'RMT',
                    };
                    $nombre = match ($sigla) {
                        'RER' => 'RER 1.5%',
                        'RG' => 'RG 1.5%',
                        'NRUS' => 'NRUS',
                        default => 'RMT 1%',
                    };
                    $tasa = match ($sigla) {
                        'RER' => 1.5,
                        'RG' => 1.5,
                        'NRUS' => 0.0,
                        default => 1.0,
                    };
                    $codFinal = match ($sigla) {
                        'RER' => '3111',
                        'RG' => '3031',
                        'NRUS' => null,
                        default => '3121',
                    };
                }
            }

            $regimenesPorMes[$m] = [
                'sigla' => $sigla,
                'nombre' => $nombre,
                'tasa' => $tasa,
                'codigo' => $codFinal,
                'es_transicion' => $esTransicion,
            ];
        }

        $periodoTransicion = null;
        $mesTransicionNombre = null;
        $mensajeTransicion = null;
        $ventasAcumuladas = null;

        if ($huboTransicion && $mesCorteTransicion !== null) {
            $periodoTransicion = sprintf('%02d-%d', $mesCorteTransicion, $anio);
            $mesTransicionNombre = self::MESES[$mesCorteTransicion]['nombre'];

            // Sumar ventas acumuladas hasta el mes de transición si hay liquidaciones
            $sumaVentas = 0.0;
            $hayVentas = false;
            for ($m = 1; $m <= $mesCorteTransicion; $m++) {
                $p = sprintf('%02d-%d', $m, $anio);
                if (isset($operaciones[$p]?->liquidacionPeriodo)) {
                    $vb = (float) $operaciones[$p]->liquidacionPeriodo->ventas_base;
                    $sumaVentas += $vb;
                    if ($vb > 0) {
                        $hayVentas = true;
                    }
                }
            }

            if ($hayVentas && $sumaVentas > 0) {
                $ventasAcumuladas = round($sumaVentas, 2);
                $mensajeTransicion = sprintf(
                    'Transición de Régimen en %s %d (%s): El cliente inició el ejercicio en Régimen Especial (RER - 1.5%%) y migró al Régimen MYPE Tributario (%s) tras acumular S/ %s en ventas netas (límite anual RER S/ 525,000.00 según Art. 121 LIR / D. Leg. 1269).',
                    $mesTransicionNombre,
                    $anio,
                    $periodoTransicion,
                    $siglaDestino,
                    number_format($ventasAcumuladas, 2)
                );
            } else {
                $mensajeTransicion = sprintf(
                    'Transición de Régimen en %s %d (%s): El cliente tributó bajo Régimen Especial (RER - 1.5%%) en periodos anteriores y ascendió al Régimen MYPE Tributario (%s) por superar el límite legal de ingresos anuales de S/ 525,000.00 (Art. 121 LIR / D. Leg. 1269).',
                    $mesTransicionNombre,
                    $anio,
                    $periodoTransicion,
                    $siglaDestino
                );
            }
        }

        $regimenActualNombre = match ($regimenCliente) {
            'RER' => 'Régimen Especial de Renta (RER)',
            'RG', 'GENERAL' => 'Régimen General (RG)',
            'RUS', 'NRUS' => 'Nuevo Régimen Único Simplificado (NRUS)',
            default => 'Régimen MYPE Tributario (RMT)',
        };

        return [
            'regimen_actual' => $regimenCliente,
            'regimen_actual_nombre' => $regimenActualNombre,
            'hubo_transicion' => $huboTransicion,
            'periodo_transicion' => $periodoTransicion,
            'mes_transicion_nombre' => $mesTransicionNombre,
            'mensaje_transicion' => $mensajeTransicion,
            'ventas_acumuladas_transicion' => $ventasAcumuladas,
            'regimenes_por_mes' => $regimenesPorMes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tributos
     * @param  Collection<string, Operacion>  $operaciones
     * @param  array<string, mixed>  $analisisRegimen
     * @return list<array<string, mixed>>
     */
    private function armarFilasPeriodos(
        string $bloqueId,
        int $anio,
        array $tributos,
        Collection $operaciones,
        ?string $mesBaja,
        string $hoy,
        Cliente $cliente,
        array $analisisRegimen
    ): array {
        $filas = [];
        $ventasAcumuladasAnio = 0.0;

        for ($mes = 1; $mes <= 12; $mes++) {
            $periodo = sprintf('%02d-%d', $mes, $anio);
            $comparable = sprintf('%04d-%02d', $anio, $mes);
            $esBaja = $mesBaja !== null && $comparable >= $mesBaja;
            $operacion = $operaciones[$periodo] ?? null;
            $liq = $operacion?->liquidacionPeriodo;

            if ($liq) {
                $ventasAcumuladasAnio += (float) $liq->ventas_base;
            }

            // Vencimiento oficial de cronograma SUNAT
            $fechaVencOficial = $this->cronogramaService->obtenerFechaVencimiento($bloqueId, $periodo, $cliente)
                ?? $this->cronogramaService->obtenerFechaVencimiento('pdt_621', $periodo, $cliente);
            $vencimientoOficialDmy = $fechaVencOficial?->format('d/m/Y');
            $vencimientoOficialIso = $fechaVencOficial?->format('Y-m-d');

            $tributosMes = [];
            $pagosConsolidados = [];
            $determinadoCentavos = 0;
            $pagadoCentavos = 0;
            $saldoCentavos = 0;
            $esIgvJusto = false;
            $vencimientoEfectivo = $vencimientoOficialDmy;
            $vencimientoEfectivoIso = $vencimientoOficialIso;
            $fechaCancelacion = null;
            $tieneMovimiento = false;

            $mapaTributosPorCodigo = [];
            foreach ($tributos as $tributo) {
                $filaTributo = $tributo['periodos'][$mes - 1] ?? null;
                if (! $filaTributo) {
                    continue;
                }

                $mapaTributosPorCodigo[$tributo['codigo']] = $filaTributo;

                $detCent = (int) ($filaTributo['determinado_centavos'] ?? $this->centavos($filaTributo['determinado'] ?? 0));
                $pagCent = (int) ($filaTributo['pagado_centavos'] ?? $this->centavos($filaTributo['pagado'] ?? 0));
                $salCent = (int) ($filaTributo['saldo_centavos'] ?? $this->centavos($filaTributo['saldo'] ?? 0));

                if ($detCent > 0 || $pagCent > 0 || ! empty($filaTributo['registrado']) || ! empty($filaTributo['pagos'])) {
                    $tieneMovimiento = true;
                }

                if (! empty($filaTributo['es_igv_justo'])) {
                    $esIgvJusto = true;
                }

                if (! empty($filaTributo['vencimiento'])) {
                    $vencimientoEfectivo = $filaTributo['vencimiento'];
                    $vencimientoEfectivoIso = $this->isoDesdeDmy($filaTributo['vencimiento']);
                }

                if (! empty($filaTributo['fecha_cancelacion'])) {
                    $fechaCancelacion = $filaTributo['fecha_cancelacion'];
                }

                $determinadoCentavos += $detCent;
                $pagadoCentavos += $pagCent;
                $saldoCentavos += $salCent;

                // Agregar los pagos consolidados
                foreach ($filaTributo['pagos'] ?? [] as $pago) {
                    $pagosConsolidados[] = [
                        ...$pago,
                        'tributo_codigo' => $tributo['codigo'],
                        'tributo_nombre' => $tributo['nombre'],
                    ];
                }

                $tributosMes[] = [
                    'codigo' => $tributo['codigo'],
                    'nombre' => $tributo['nombre'],
                    'determinado' => $filaTributo['determinado'] ?? 'S/ 0.00',
                    'determinado_centavos' => $detCent,
                    'pagado' => $filaTributo['pagado'] ?? 'S/ 0.00',
                    'pagado_centavos' => $pagCent,
                    'saldo' => $filaTributo['saldo'] ?? 'S/ 0.00',
                    'saldo_centavos' => $salCent,
                    'situacion' => $filaTributo['situacion'] ?? 'sin_determinar',
                    'situacion_etiqueta' => $filaTributo['situacion_etiqueta'] ?? 'Sin registrar',
                    'vencimiento' => $filaTributo['vencimiento'] ?? $vencimientoOficialDmy,
                    'es_igv_justo' => (bool) ($filaTributo['es_igv_justo'] ?? false),
                    'tasa' => $filaTributo['tasa'] ?? null,
                ];
            }

            // Mapeo contable específico por formulario para vista paralela (PDT vs Extracto/Pagos)
            $igvFila = null;
            $rentaFila = null;
            $plameFila = null;

            if ($bloqueId === 'pdt_621') {
                $igvItem = $mapaTributosPorCodigo['1011'] ?? $mapaTributosPorCodigo['1016'] ?? null;
                $igvDet = (int) ($igvItem['determinado_centavos'] ?? ($igvItem ? $this->centavos($igvItem['determinado'] ?? 0) : 0));
                $igvPag = (int) ($igvItem['pagado_centavos'] ?? ($igvItem ? $this->centavos($igvItem['pagado'] ?? 0) : 0));
                $igvSal = (int) ($igvItem['saldo_centavos'] ?? ($igvItem ? $this->centavos($igvItem['saldo'] ?? 0) : 0));
                $igvFila = [
                    'codigo' => '1011',
                    'nombre' => 'IGV Cuenta Propia (18%)',
                    'determinado' => $this->soles($igvDet),
                    'determinado_centavos' => $igvDet,
                    'pagado' => $this->soles($igvPag),
                    'pagado_centavos' => $igvPag,
                    'saldo' => $this->soles($igvSal),
                    'saldo_centavos' => $igvSal,
                    'situacion' => $igvItem['situacion'] ?? 'sin_determinar',
                    'situacion_etiqueta' => $igvItem['situacion_etiqueta'] ?? 'Sin registrar',
                ];

                $rentaDet = 0;
                $rentaPag = 0;
                $rentaSal = 0;
                $rentaCodigoUsado = null;
                $rentaSituacion = 'sin_determinar';
                $rentaSituacionEtiqueta = 'Sin registrar';
                foreach (['3111', '3121', '3031', '3081'] as $cRenta) {
                    if (isset($mapaTributosPorCodigo[$cRenta])) {
                        $rItem = $mapaTributosPorCodigo[$cRenta];
                        $rDet = (int) ($rItem['determinado_centavos'] ?? $this->centavos($rItem['determinado'] ?? 0));
                        $rPag = (int) ($rItem['pagado_centavos'] ?? $this->centavos($rItem['pagado'] ?? 0));
                        $rSal = (int) ($rItem['saldo_centavos'] ?? $this->centavos($rItem['saldo'] ?? 0));
                        $rentaDet += $rDet;
                        $rentaPag += $rPag;
                        $rentaSal += $rSal;
                        if ($rDet > 0 || $rPag > 0 || ! empty($rItem['registrado'])) {
                            $rentaCodigoUsado = $cRenta;
                            $rentaSituacion = $rItem['situacion'] ?? $rentaSituacion;
                            $rentaSituacionEtiqueta = $rItem['situacion_etiqueta'] ?? $rentaSituacionEtiqueta;
                        }
                    }
                }
                $rentaFila = [
                    'codigo' => $rentaCodigoUsado ?? $regimenMes['codigo'] ?? '3121',
                    'nombre' => 'Renta Mensual ('.($regimenMes['sigla'] ?? 'RMT').')',
                    'determinado' => $this->soles($rentaDet),
                    'determinado_centavos' => $rentaDet,
                    'pagado' => $this->soles($rentaPag),
                    'pagado_centavos' => $rentaPag,
                    'saldo' => $this->soles($rentaSal),
                    'saldo_centavos' => $rentaSal,
                    'situacion' => $rentaSituacion,
                    'situacion_etiqueta' => $rentaSituacionEtiqueta,
                ];
            } elseif ($bloqueId === 'pdt_601') {
                $plameFila = [];
                $mapeoPlame = [
                    'essalud' => ['codigo' => '5210', 'nombre' => 'EsSalud (5210)'],
                    'onp' => ['codigo' => '5310', 'nombre' => 'ONP (5310)'],
                    'afp' => ['codigo' => '6098', 'nombre' => 'AFP (6098)'],
                    'renta_4ta' => ['codigo' => '3042', 'nombre' => 'Rta 4ta (3042)'],
                    'renta_5ta' => ['codigo' => '3052', 'nombre' => 'Rta 5ta (3052)'],
                ];
                foreach ($mapeoPlame as $clave => $infoPlame) {
                    $codPlame = $infoPlame['codigo'];
                    $pItem = $mapaTributosPorCodigo[$codPlame] ?? null;
                    $pDet = (int) ($pItem['determinado_centavos'] ?? ($pItem ? $this->centavos($pItem['determinado'] ?? 0) : 0));
                    $pPag = (int) ($pItem['pagado_centavos'] ?? ($pItem ? $this->centavos($pItem['pagado'] ?? 0) : 0));
                    $pSal = (int) ($pItem['saldo_centavos'] ?? ($pItem ? $this->centavos($pItem['saldo'] ?? 0) : 0));
                    $plameFila[$clave] = [
                        'codigo' => $codPlame,
                        'nombre' => $infoPlame['nombre'],
                        'determinado' => $this->soles($pDet),
                        'determinado_centavos' => $pDet,
                        'pagado' => $this->soles($pPag),
                        'pagado_centavos' => $pPag,
                        'saldo' => $this->soles($pSal),
                        'saldo_centavos' => $pSal,
                        'situacion' => $pItem['situacion'] ?? 'sin_determinar',
                        'situacion_etiqueta' => $pItem['situacion_etiqueta'] ?? 'Sin registrar',
                    ];
                }
            }

            // Ordenar pagos por fecha
            usort($pagosConsolidados, function (array $a, array $b): int {
                $fA = $this->isoDesdeDmy($a['fecha'] ?? '') ?? '';
                $fB = $this->isoDesdeDmy($b['fecha'] ?? '') ?? '';

                return strcmp($fA, $fB);
            });

            // Determinar situación del mes consolidado
            $cantidadPagos = count($pagosConsolidados);
            $debe = $saldoCentavos > 0;

            if ($esBaja && $cantidadPagos === 0 && $determinadoCentavos === 0) {
                $situacion = 'baja';
                $situacionEtiqueta = 'Baja';
            } elseif ($determinadoCentavos > 0) {
                if ($saldoCentavos === 0) {
                    // Verificar si pagó a tiempo o fuera de fecha
                    $algunaFuera = false;
                    foreach ($tributosMes as $tm) {
                        if ($tm['situacion'] === EvaluadorPagoTributo::PAGADO_FUERA_DE_FECHA) {
                            $algunaFuera = true;
                            break;
                        }
                    }
                    $situacion = $algunaFuera ? EvaluadorPagoTributo::PAGADO_FUERA_DE_FECHA : EvaluadorPagoTributo::PAGADO_A_TIEMPO;
                    $situacionEtiqueta = $algunaFuera ? 'Pagado fuera de fecha' : 'Pagado a tiempo';
                } else {
                    $vencido = ($vencimientoEfectivoIso !== null && $hoy > $vencimientoEfectivoIso);
                    if ($vencido) {
                        $situacion = $pagadoCentavos > 0 ? EvaluadorPagoTributo::DEBE_PARCIAL_VENCIDO : EvaluadorPagoTributo::DEBE_VENCIDO;
                        $situacionEtiqueta = $pagadoCentavos > 0 ? 'Debe · parcial vencido' : 'Debe · vencido';
                    } else {
                        $situacion = $pagadoCentavos > 0 ? EvaluadorPagoTributo::DEBE_PARCIAL : EvaluadorPagoTributo::DEBE_EN_PLAZO;
                        $situacionEtiqueta = $pagadoCentavos > 0 ? 'Debe · parcial en plazo' : 'Debe · en plazo';
                    }
                }
            } elseif ($pagadoCentavos > 0) {
                $situacion = EvaluadorPagoTributo::PAGADO_A_TIEMPO;
                $situacionEtiqueta = 'Pagado';
            } elseif ($tieneMovimiento) {
                $situacion = 'al_dia';
                $situacionEtiqueta = 'Al día / S/ 0';
            } else {
                $situacion = EvaluadorPagoTributo::SIN_DETERMINAR;
                $situacionEtiqueta = 'Sin registrar';
            }

            // Datos de liquidación si existen
            $datosLiquidacion = null;
            if ($liq) {
                $datosLiquidacion = [
                    'ventas_base' => (float) $liq->ventas_base,
                    'ventas_base_formateado' => $this->soles($this->centavos($liq->ventas_base)),
                    'igv_ventas' => (float) $liq->igv_ventas,
                    'ventas_no_gravadas' => (float) $liq->ventas_no_gravadas,
                    'compras_base' => (float) $liq->compras_base,
                    'compras_base_formateado' => $this->soles($this->centavos($liq->compras_base)),
                    'igv_compras' => (float) $liq->igv_compras,
                    'igv_saldo_favor_anterior' => (float) $liq->igv_saldo_favor_anterior,
                    'igv_saldo_favor_siguiente' => (float) $liq->igv_saldo_favor_siguiente,
                    'igv_retenciones' => (float) $liq->igv_retenciones,
                    'igv_percepciones' => (float) $liq->igv_percepciones,
                    'igv_resultante_pagar' => (float) $liq->igv_resultante_pagar,
                    'renta_base_ingresos' => (float) $liq->renta_base_ingresos,
                    'renta_tasa' => (float) $liq->renta_tasa,
                    'renta_calculada' => (float) $liq->renta_calculada,
                    'renta_saldo_favor_anterior' => (float) $liq->renta_saldo_favor_anterior,
                    'renta_saldo_favor_siguiente' => (float) $liq->renta_saldo_favor_siguiente,
                    'renta_compensacion_itan' => (float) $liq->renta_compensacion_itan,
                    'renta_resultante_pagar' => (float) $liq->renta_resultante_pagar,
                    'es_zona_selva' => (bool) $liq->es_zona_selva,
                    // PLAME
                    'plame_essalud_base' => (float) $liq->plame_essalud_base,
                    'plame_essalud_a_pagar' => (float) $liq->plame_essalud_a_pagar,
                    'plame_onp_a_pagar' => (float) $liq->plame_onp_a_pagar,
                    'plame_renta_4ta_base' => (float) $liq->plame_renta_4ta_base,
                    'plame_renta_4ta_a_pagar' => (float) $liq->plame_renta_4ta_a_pagar,
                    'plame_renta_5ta_a_pagar' => (float) $liq->plame_renta_5ta_a_pagar,
                    'plame_afp_a_pagar' => (float) $liq->plame_afp_a_pagar,
                    'notas_liquidacion' => trim((string) ($liq->notas_liquidacion ?? '')),
                ];
            }

            $regimenMes = $analisisRegimen['regimenes_por_mes'][$mes] ?? [
                'sigla' => 'RMT',
                'nombre' => 'RMT 1%',
                'tasa' => 1.0,
                'codigo' => '3121',
                'es_transicion' => false,
            ];

            // Texto descriptivo de fecha de pago
            $fechaPagoTexto = '—';
            if ($cantidadPagos === 1) {
                $fechaPagoTexto = $pagosConsolidados[0]['fecha'] ?? '—';
            } elseif ($cantidadPagos > 1) {
                $ultimoPago = end($pagosConsolidados);
                $fechaPagoTexto = "{$cantidadPagos} pagos (último {$ultimoPago['fecha']})";
            }

            $filas[] = [
                'periodo' => $periodo,
                'mes_num' => $mes,
                'mes_etiqueta' => self::MESES[$mes]['corto'].'-'.substr((string) $anio, -2),
                'mes_nombre' => self::MESES[$mes]['nombre'],
                'es_baja' => $esBaja,
                'tiene_movimiento' => $tieneMovimiento,
                'regimen' => $regimenMes,
                'vencimiento_oficial' => $vencimientoOficialDmy ?? '—',
                'vencimiento' => $vencimientoEfectivo ?? $vencimientoOficialDmy ?? '—',
                'es_igv_justo' => $esIgvJusto,
                'total_determinado' => $this->soles($determinadoCentavos),
                'total_determinado_centavos' => $determinadoCentavos,
                'total_pagado' => $this->soles($pagadoCentavos),
                'total_pagado_centavos' => $pagadoCentavos,
                'total_saldo' => $this->soles($saldoCentavos),
                'total_saldo_centavos' => $saldoCentavos,
                'debe' => $debe,
                'situacion' => $situacion,
                'situacion_etiqueta' => $situacionEtiqueta,
                'fecha_pago' => $fechaPagoTexto,
                'fecha_cancelacion' => $fechaCancelacion,
                'cantidad_pagos' => $cantidadPagos,
                'pagos' => $pagosConsolidados,
                'tributos' => $tributosMes,
                'igv' => $igvFila,
                'renta' => $rentaFila,
                'plame' => $plameFila,
                'datos_liquidacion' => $datosLiquidacion,
                'ventas_acumuladas' => $ventasAcumuladasAnio > 0 ? $this->soles($this->centavos($ventasAcumuladasAnio)) : null,
            ];
        }

        return $filas;
    }

    /**
     * @param  list<array<string, mixed>>  $filasPeriodos
     * @return array{
     *     determinado_centavos: int,
     *     determinado: string,
     *     pagado_centavos: int,
     *     pagado: string,
     *     saldo_centavos: int,
     *     saldo: string,
     *     al_dia: int,
     *     con_deuda: int,
     *     fuera_de_fecha: int,
     *     cantidad_pagos: int,
     *     totales_columnas: array<string, array{determinado: string, determinado_centavos: int, pagado: string, pagado_centavos: int, saldo: string, saldo_centavos: int}>,
     *     diferencias: array<string, mixed>
     * }
     */
    private function calcularTotalesBloque(array $filasPeriodos, string $bloqueId = 'pdt_621'): array
    {
        $determinado = 0;
        $pagado = 0;
        $saldo = 0;
        $alDia = 0;
        $conDeuda = 0;
        $fuera = 0;
        $pagos = 0;

        $totalesColumnas = [];
        $diferencias = [];

        if ($bloqueId === 'pdt_621') {
            $igvDet = 0;
            $igvPag = 0;
            $igvSal = 0;
            $rentaDet = 0;
            $rentaPag = 0;
            $rentaSal = 0;

            foreach ($filasPeriodos as $fila) {
                $igvDet += (int) ($fila['igv']['determinado_centavos'] ?? 0);
                $igvPag += (int) ($fila['igv']['pagado_centavos'] ?? 0);
                $igvSal += (int) ($fila['igv']['saldo_centavos'] ?? 0);

                $rentaDet += (int) ($fila['renta']['determinado_centavos'] ?? 0);
                $rentaPag += (int) ($fila['renta']['pagado_centavos'] ?? 0);
                $rentaSal += (int) ($fila['renta']['saldo_centavos'] ?? 0);
            }

            $totalesColumnas = [
                'igv' => [
                    'determinado' => $this->soles($igvDet),
                    'determinado_centavos' => $igvDet,
                    'pagado' => $this->soles($igvPag),
                    'pagado_centavos' => $igvPag,
                    'saldo' => $this->soles($igvSal),
                    'saldo_centavos' => $igvSal,
                ],
                'renta' => [
                    'determinado' => $this->soles($rentaDet),
                    'determinado_centavos' => $rentaDet,
                    'pagado' => $this->soles($rentaPag),
                    'pagado_centavos' => $rentaPag,
                    'saldo' => $this->soles($rentaSal),
                    'saldo_centavos' => $rentaSal,
                ],
            ];

            $diferencias = [
                'igv' => $this->soles($igvSal),
                'igv_centavos' => $igvSal,
                'renta' => $this->soles($rentaSal),
                'renta_centavos' => $rentaSal,
                'total' => $this->soles($igvSal + $rentaSal),
                'total_centavos' => $igvSal + $rentaSal,
            ];
        } elseif ($bloqueId === 'pdt_601') {
            $clavesPlame = ['essalud', 'onp', 'afp', 'renta_4ta', 'renta_5ta'];
            $sumas = [];
            foreach ($clavesPlame as $clave) {
                $sumas[$clave] = ['det' => 0, 'pag' => 0, 'sal' => 0];
            }

            foreach ($filasPeriodos as $fila) {
                foreach ($clavesPlame as $clave) {
                    $sumas[$clave]['det'] += (int) ($fila['plame'][$clave]['determinado_centavos'] ?? 0);
                    $sumas[$clave]['pag'] += (int) ($fila['plame'][$clave]['pagado_centavos'] ?? 0);
                    $sumas[$clave]['sal'] += (int) ($fila['plame'][$clave]['saldo_centavos'] ?? 0);
                }
            }

            $totalPlameSal = 0;
            foreach ($clavesPlame as $clave) {
                $totalesColumnas[$clave] = [
                    'determinado' => $this->soles($sumas[$clave]['det']),
                    'determinado_centavos' => $sumas[$clave]['det'],
                    'pagado' => $this->soles($sumas[$clave]['pag']),
                    'pagado_centavos' => $sumas[$clave]['pag'],
                    'saldo' => $this->soles($sumas[$clave]['sal']),
                    'saldo_centavos' => $sumas[$clave]['sal'],
                ];
                $diferencias[$clave] = $this->soles($sumas[$clave]['sal']);
                $diferencias[$clave.'_centavos'] = $sumas[$clave]['sal'];
                $totalPlameSal += $sumas[$clave]['sal'];
            }
            $diferencias['total'] = $this->soles($totalPlameSal);
            $diferencias['total_centavos'] = $totalPlameSal;
        }

        foreach ($filasPeriodos as $fila) {
            $determinado += $fila['total_determinado_centavos'] ?? 0;
            $pagado += $fila['total_pagado_centavos'] ?? 0;
            $saldo += $fila['total_saldo_centavos'] ?? 0;
            $pagos += $fila['cantidad_pagos'] ?? 0;

            if (! empty($fila['debe'])) {
                $conDeuda++;
            } elseif (($fila['total_determinado_centavos'] ?? 0) > 0 || ($fila['total_pagado_centavos'] ?? 0) > 0) {
                $alDia++;
            }

            if (($fila['situacion'] ?? '') === EvaluadorPagoTributo::PAGADO_FUERA_DE_FECHA) {
                $fuera++;
            }
        }

        return [
            'determinado_centavos' => $determinado,
            'determinado' => $this->soles($determinado),
            'pagado_centavos' => $pagado,
            'pagado' => $this->soles($pagado),
            'saldo_centavos' => $saldo,
            'saldo' => $this->soles($saldo),
            'al_dia' => $alDia,
            'con_deuda' => $conDeuda,
            'fuera_de_fecha' => $fuera,
            'cantidad_pagos' => $pagos,
            'totales_columnas' => $totalesColumnas,
            'diferencias' => $diferencias,
        ];
    }

    private function isoDesdeDmy(?string $dmy): ?string
    {
        if ($dmy === null || $dmy === '' || $dmy === '—') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dmy)) {
            return $dmy;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dmy, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }
}
