<?php

namespace App\Services\ControlImpuestos;

use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use App\Services\CronogramaSunatService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Carga opcional de declaraciones y tributos del ejercicio.
 *
 * No reemplaza el llenado manual. Cada tributo se graba con RegistrarTributoPeriodo,
 * el mismo registro de la caja de Tributos y pagos.
 */
class CargaMasivaControlImpuestos
{
    /** @var array<string, ?string> */
    private array $fechas = [];

    public function __construct(
        private RegistrarTributoPeriodo $registroTributo,
        private LectorPlantillaControlImpuestos $lector,
        private CronogramaSunatService $cronograma,
    ) {}

    /**
     * @param  list<int>  $clienteIds
     */
    public function plantilla(int $anio, array $clienteIds = []): string
    {
        $encabezados = $this->encabezados();
        $lineas = [$this->linea($encabezados)];
        $vacios = array_fill(0, count($encabezados) - 3, '');
        $clientes = $this->clientesDelEjercicio($anio);
        if ($clienteIds !== []) {
            $pedidos = array_fill_keys($clienteIds, true);
            $clientes = $clientes
                ->filter(fn (Cliente $cliente): bool => isset($pedidos[$cliente->id]))
                ->values();
        }

        foreach ($clientes as $cliente) {
            for ($mes = 1; $mes <= 12; $mes++) {
                $periodo = sprintf('%02d-%d', $mes, $anio);
                if (! $cliente->rindeEnPeriodo($periodo)) {
                    continue;
                }
                $lineas[] = $this->linea([
                    (string) $cliente->ruc,
                    (string) $cliente->razon_social,
                    $periodo,
                    ...$vacios,
                ]);
            }
        }

        $lineas[] = '';
        $lineas[] = 'PAGOS';
        $lineas[] = $this->linea($this->encabezadosPagos());

        return "\xEF\xBB\xBF".implode("\r\n", $lineas)."\r\n";
    }

    public function previsualizar(SolicitudCargaMasiva $solicitud): ResultadoCargaMasiva
    {
        return $this->resultado(false, $this->planificar($solicitud));
    }

    public function aplicar(SolicitudCargaMasiva $solicitud): ResultadoCargaMasiva
    {
        $plan = $this->planificar($solicitud);
        $revision = $this->resultado(false, $plan);
        if (! $revision->puedeAplicar) {
            return $revision;
        }

        try {
            DB::transaction(function () use ($plan): void {
                $this->escribir($plan['cambios']);
            });
        } catch (ValidationException $excepcion) {
            return $this->rechazo($this->mensajeValidacion($excepcion));
        } catch (QueryException) {
            return $this->rechazo('No se pudo guardar la carga. No se modificó ningún dato.');
        }

        return $this->resultado(true, $plan);
    }

    /**
     * @return array{
     *     cambios: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     omitidos: int,
     *     avisos: list<string>
     * }
     */
    private function planificar(SolicitudCargaMasiva $solicitud): array
    {
        return match ($solicitud->modo) {
            'plantilla' => $this->planPlantilla($solicitud),
            'honorarios' => $this->planHonorarios($solicitud),
            default => $this->planEstados($solicitud),
        };
    }

    /**
     * @return array{
     *     cambios: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     omitidos: int,
     *     avisos: list<string>
     * }
     */
    private function planEstados(SolicitudCargaMasiva $solicitud): array
    {
        $clientes = $this->clientesObjetivo($solicitud);
        if ($solicitud->alcance === 'seleccion' && $clientes->count() !== count(array_unique($solicitud->clienteIds))) {
            return $this->planVacio([[
                'fila' => null,
                'mensaje' => 'Alguno de los clientes elegidos no se declara en este ejercicio.',
            ]]);
        }

        $elegidas = array_values(array_intersect(['sire', 'pdt', 'ple'], $solicitud->obligaciones));
        $periodos = [];
        for ($mes = (int) $solicitud->mesDesde; $mes <= (int) $solicitud->mesHasta; $mes++) {
            $periodos[] = sprintf('%02d-%d', $mes, $solicitud->anio);
        }

        $reinicio = $solicitud->estado === 'Pendiente';
        $operaciones = $this->operacionesDe($clientes, $periodos, $reinicio);
        $cambios = [];
        $bajas = 0;
        $conservados = 0;
        $iguales = 0;

        foreach ($clientes as $cliente) {
            foreach ($periodos as $periodo) {
                if (! $cliente->rindeEnPeriodo($periodo)) {
                    $bajas++;

                    continue;
                }

                $operacion = $operaciones->get($cliente->id.'|'.$periodo);
                $datos = [];
                foreach ($elegidas as $clave) {
                    $actual = $operacion?->{$this->columnaEstado($clave)} ?? 'Pendiente';
                    if ($actual === $solicitud->estado) {
                        $iguales++;

                        continue;
                    }
                    if (! $reinicio && $solicitud->politica === 'conservar' && $actual !== 'Pendiente') {
                        $conservados++;

                        continue;
                    }
                    $datos[$clave] = $solicitud->estado;
                }

                if ($datos !== []) {
                    $cambios[] = $this->cambioEstado($cliente, $periodo, $datos, null);
                }

                if ($reinicio && $this->tieneTributosOPagos($operacion)) {
                    $cambios[] = [
                        'tipo' => 'limpiar',
                        'cliente_id' => $cliente->id,
                        'periodo' => $periodo,
                        'fila' => null,
                        'resumen' => "{$cliente->razon_social} · {$periodo} · se eliminan tributos y pagos",
                        'datos' => [],
                    ];
                }
            }
        }

        return [
            'cambios' => $cambios,
            'errores' => [],
            'omitidos' => $bajas + $conservados + $iguales,
            'avisos' => $this->avisos($bajas, $conservados + $iguales),
        ];
    }

    /**
     * @return array{
     *     cambios: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     omitidos: int,
     *     avisos: list<string>
     * }
     */
    private function planHonorarios(SolicitudCargaMasiva $solicitud): array
    {
        $clientes = $this->clientesObjetivo($solicitud);
        if ($solicitud->alcance === 'seleccion' && $clientes->count() !== count(array_unique($solicitud->clienteIds))) {
            return $this->planVacio([[
                'fila' => null,
                'mensaje' => 'Alguno de los clientes elegidos no se declara en este ejercicio.',
            ]]);
        }

        $periodos = [];
        for ($mes = (int) $solicitud->mesDesde; $mes <= (int) $solicitud->mesHasta; $mes++) {
            $periodos[] = sprintf('%02d-%d', $mes, $solicitud->anio);
        }

        $operaciones = $this->operacionesDe($clientes, $periodos, false);
        $cambios = [];
        $bajas = 0;
        $conservados = 0;
        $iguales = 0;
        $etiqueta = $solicitud->estado === 'Pagado' ? 'Pagó' : 'Debe';

        foreach ($clientes as $cliente) {
            foreach ($periodos as $periodo) {
                if (! $cliente->rindeEnPeriodo($periodo)) {
                    $bajas++;

                    continue;
                }

                $operacion = $operaciones->get($cliente->id.'|'.$periodo);
                $actual = $operacion?->estado_pago_honorarios ?? 'Pendiente';
                if ($actual === $solicitud->estado) {
                    $iguales++;

                    continue;
                }
                if ($solicitud->politica === 'conservar' && $actual !== 'Pendiente') {
                    $conservados++;

                    continue;
                }

                $cambios[] = [
                    'tipo' => 'honorario',
                    'cliente_id' => $cliente->id,
                    'periodo' => $periodo,
                    'fila' => null,
                    'resumen' => "{$cliente->razon_social} · {$periodo} · RH → {$etiqueta}",
                    'datos' => ['honorarios' => $solicitud->estado],
                ];
            }
        }

        return [
            'cambios' => $cambios,
            'errores' => [],
            'omitidos' => $bajas + $conservados + $iguales,
            'avisos' => $this->avisos($bajas, $conservados + $iguales),
        ];
    }

    /**
     * @return array{
     *     cambios: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     omitidos: int,
     *     avisos: list<string>
     * }
     */
    private function planPlantilla(SolicitudCargaMasiva $solicitud): array
    {
        $lectura = $this->lector->leer((string) $solicitud->contenido);
        $errores = $lectura['errores'];
        $clientes = $this->clientesDelEjercicio($solicitud->anio);
        if ($solicitud->alcance === 'seleccion') {
            $ids = array_values(array_unique($solicitud->clienteIds));
            $elegidos = $clientes->whereIn('id', $ids);
            if ($elegidos->count() !== count($ids)) {
                return $this->planVacio([[
                    'fila' => null,
                    'mensaje' => 'Alguno de los clientes elegidos no se declara en este ejercicio.',
                ]]);
            }
        }
        $porRuc = [];
        $repetidos = [];

        foreach ($clientes as $cliente) {
            $ruc = $this->ruc((string) $cliente->ruc);
            if ($ruc === '') {
                continue;
            }
            if (isset($porRuc[$ruc])) {
                $repetidos[$ruc] = true;
            }
            $porRuc[$ruc] = $cliente;
        }

        $permitidos = $solicitud->alcance === 'seleccion'
            ? array_fill_keys($solicitud->clienteIds, true)
            : null;
        $operaciones = $this->operacionesDe($clientes, $this->periodosDelAnio($solicitud->anio), true);
        $cambios = [];
        $bajas = 0;
        $conservados = 0;
        $iguales = 0;
        $avisosPago = [];
        $vistos = [];
        $importesArchivo = [];
        $pagosPropios = [];
        foreach ($lectura['pagos'] ?? [] as $pago) {
            $pagosPropios[$pago['ruc'].'|'.$pago['periodo'].'|'.$pago['codigo']] = true;
        }

        foreach ($lectura['filas'] as $fila) {
            $numero = (int) $fila['fila'];
            $ruc = (string) $fila['ruc'];
            $periodo = (string) $fila['periodo'];

            if (isset($repetidos[$ruc])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: hay más de un cliente con el RUC {$ruc} en este ejercicio.",
                ];

                continue;
            }

            $cliente = $porRuc[$ruc] ?? null;
            if (! $cliente instanceof Cliente) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el RUC {$ruc} no está en su cartera de este ejercicio.",
                ];

                continue;
            }

            if ($permitidos !== null && ! isset($permitidos[$cliente->id])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: {$cliente->razon_social} no está entre las empresas elegidas para esta carga.",
                ];

                continue;
            }

            $anioPeriodo = (int) explode('-', $periodo)[1];
            if ($anioPeriodo !== $solicitud->anio) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el periodo {$periodo} no pertenece al ejercicio {$solicitud->anio}.",
                ];

                continue;
            }

            $clave = $cliente->id.'|'.$periodo;
            if (isset($vistos[$clave])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el periodo {$periodo} del RUC {$ruc} ya aparece en la fila {$vistos[$clave]}.",
                ];

                continue;
            }
            $vistos[$clave] = $numero;

            if (! $cliente->rindeEnPeriodo($periodo)) {
                $bajas++;

                continue;
            }

            $operacion = $operaciones->get($clave);
            $datosEstado = [];
            foreach (['sire', 'pdt', 'ple'] as $campo) {
                $destino = $fila[$campo];
                if (! is_string($destino)) {
                    continue;
                }
                $actual = $operacion?->{$this->columnaEstado($campo)} ?? 'Pendiente';
                if ($actual === $destino) {
                    $iguales++;

                    continue;
                }
                if ($solicitud->politica === 'conservar' && $actual !== 'Pendiente') {
                    $conservados++;

                    continue;
                }
                $datosEstado[$campo] = $destino;
            }

            if ($datosEstado !== []) {
                $cambios[] = $this->cambioEstado($cliente, $periodo, $datosEstado, $numero);
            }

            $deudas = $operacion?->deudasTributarias?->keyBy('tributo_codigo') ?? collect();
            $erroresDeLaFila = count($errores);
            foreach ($fila['tributos'] as $tributo) {
                $importesArchivo[$cliente->id.'|'.$periodo.'|'.(string) $tributo['codigo']] = (float) $tributo['monto'];
                $evaluado = $this->evaluarTributo(
                    $cliente,
                    $periodo,
                    $numero,
                    (string) $tributo['codigo'],
                    (float) $tributo['monto'],
                    $fila['igv_justo'],
                    $deudas->get($tributo['codigo']),
                    $solicitud->politica,
                );
                if ($evaluado['error'] !== null) {
                    $errores[] = ['fila' => $numero, 'mensaje' => $evaluado['error']];

                    continue;
                }
                if ($evaluado['cambio'] === null) {
                    if ($evaluado['motivo'] === 'conservado') {
                        $conservados++;
                    } else {
                        $iguales++;
                    }

                    continue;
                }
                $cambios[] = $evaluado['cambio'];
            }

            if (($fila['pagado'] ?? null) === true && count($errores) === $erroresDeLaFila) {
                $filaGeneral = $fila;
                $filaGeneral['tributos'] = array_values(array_filter(
                    $fila['tributos'],
                    fn (array $tributo): bool => ! isset($pagosPropios[$ruc.'|'.$periodo.'|'.$tributo['codigo']]),
                ));
                if ($filaGeneral['tributos'] !== []) {
                    $this->planPagos($cliente, $periodo, $numero, $filaGeneral, $deudas, $solicitud->politica, $cambios, $iguales, $avisosPago, $errores);
                }
            }
        }

        $this->planPagosDelArchivo(
            $lectura['pagos'] ?? [],
            $porRuc,
            $permitidos,
            $repetidos,
            $operaciones,
            $importesArchivo,
            $solicitud,
            $cambios,
            $errores,
            $iguales,
        );

        if (count($cambios) > 8000) {
            $errores[] = [
                'fila' => null,
                'mensaje' => 'La carga supera los 8000 cambios. Divídala en dos archivos.',
            ];
            $cambios = [];
        }

        return [
            'cambios' => $cambios,
            'errores' => $errores,
            'omitidos' => $bajas + $conservados + $iguales,
            'avisos' => array_merge($this->avisos($bajas, $conservados + $iguales), $this->limitarAvisos($avisosPago)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filaDatos
     * @param  SupportCollection<string, DeudaTributaria>  $deudas
     * @param  list<array<string, mixed>>  $cambios
     * @param  list<string>  $avisosPago
     * @param  list<array{fila: int|null, mensaje: string}>  $errores
     */
    private function planPagos(
        Cliente $cliente,
        string $periodo,
        int $fila,
        array $filaDatos,
        SupportCollection $deudas,
        string $politica,
        array &$cambios,
        int &$iguales,
        array &$avisosPago,
        array &$errores,
    ): void {
        if ($filaDatos['tributos'] === []) {
            $errores[] = [
                'fila' => $fila,
                'mensaje' => "Fila {$fila}: para marcar pagado escriba el importe del tributo en esa misma fila.",
            ];

            return;
        }

        foreach ($filaDatos['tributos'] as $tributo) {
            $codigo = (string) $tributo['codigo'];
            $monto = (float) $tributo['monto'];
            $existente = $deudas->get($codigo);
            $deuda = $existente instanceof DeudaTributaria ? $existente : null;
            $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;
            $abonado = $deuda ? round((float) $deuda->abonos->sum('monto'), 2) : 0.0;

            if ($abonado > 0.001 && $abonado + 0.001 < $monto) {
                $avisosPago[] = "Fila {$fila}: {$codigo} {$nombre} ya tiene un pago parcial. Complete ese pago en la caja.";

                continue;
            }

            if ($abonado + 0.001 >= $monto && $abonado > 0.001) {
                $iguales++;

                continue;
            }

            if ($deuda && $politica === 'conservar' && ! $this->mismoMonto((float) $deuda->monto_deuda_inicial, $monto)) {
                $avisosPago[] = "Fila {$fila}: {$codigo} {$nombre} ya tiene otro importe. No se registró el pago para no dejar un saldo distinto.";

                continue;
            }

            $vencimiento = $this->vencimientoDe($cliente, $periodo, $codigo, (bool) $filaDatos['igv_justo'], $deuda);
            $fechaIndicada = is_string($filaDatos['fecha_pago'] ?? null) ? $filaDatos['fecha_pago'] : '';
            $fechaBase = Carbon::createFromFormat('!m-Y', $periodo);
            $fecha = $fechaIndicada !== ''
                ? $fechaIndicada
                : ($vencimiento ?? ($fechaBase === false ? null : $fechaBase->endOfMonth()->toDateString()));
            if ($fecha === null) {
                $avisosPago[] = "Fila {$fila}: no se pudo fechar el pago de {$codigo} {$nombre}.";

                continue;
            }

            $medio = is_string($filaDatos['medio_pago'] ?? null) ? $filaDatos['medio_pago'] : '';
            $numero = trim((string) ($filaDatos['numero_operacion'] ?? ''));
            $resumen = "{$cliente->razon_social} · {$periodo} · pago {$codigo} {$nombre} → {$this->soles($monto)}";
            if ($medio !== '') {
                $resumen .= ' · '.str_replace('_', ' ', $medio);
            }

            $cambios[] = [
                'tipo' => 'pago',
                'cliente_id' => $cliente->id,
                'periodo' => $periodo,
                'fila' => $fila,
                'resumen' => $resumen,
                'datos' => [
                    'codigo' => $codigo,
                    'monto' => $monto,
                    'fecha' => $fecha,
                    'medio' => $medio !== '' ? $medio : null,
                    'numero' => $numero,
                ],
            ];
        }
    }

    /**
     * Cada fila de la sección PAGOS es un abono de un tributo. Un mismo código puede repetirse.
     *
     * @param  list<array<string, mixed>>  $pagos
     * @param  array<string, Cliente>  $porRuc
     * @param  array<int, true>|null  $permitidos
     * @param  array<string, true>  $repetidos
     * @param  Collection<string, Operacion>  $operaciones
     * @param  array<string, float>  $importesArchivo
     * @param  list<array<string, mixed>>  $cambios
     * @param  list<array{fila: int|null, mensaje: string}>  $errores
     */
    private function planPagosDelArchivo(
        array $pagos,
        array $porRuc,
        ?array $permitidos,
        array $repetidos,
        Collection $operaciones,
        array $importesArchivo,
        SolicitudCargaMasiva $solicitud,
        array &$cambios,
        array &$errores,
        int &$iguales,
    ): void {
        $firmas = [];
        $acumulado = [];

        foreach ($pagos as $pago) {
            $numero = (int) $pago['fila'];
            $ruc = (string) $pago['ruc'];
            $periodo = (string) $pago['periodo'];
            $codigo = (string) $pago['codigo'];
            $monto = (float) $pago['monto'];
            $fecha = (string) $pago['fecha'];
            $medio = (string) $pago['medio'];
            $operacionNumero = trim((string) ($pago['numero'] ?? ''));

            if (isset($repetidos[$ruc])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: hay más de un cliente con el RUC {$ruc} en este ejercicio.",
                ];

                continue;
            }

            $cliente = $porRuc[$ruc] ?? null;
            if (! $cliente instanceof Cliente) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el RUC {$ruc} no está en su cartera de este ejercicio.",
                ];

                continue;
            }

            if ($permitidos !== null && ! isset($permitidos[$cliente->id])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: {$cliente->razon_social} no está entre las empresas elegidas para esta carga.",
                ];

                continue;
            }

            $anioPeriodo = (int) explode('-', $periodo)[1];
            if ($anioPeriodo !== $solicitud->anio) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el periodo {$periodo} no pertenece al ejercicio {$solicitud->anio}.",
                ];

                continue;
            }

            if (! $cliente->rindeEnPeriodo($periodo)) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el periodo {$periodo} es de baja y no admite pagos.",
                ];

                continue;
            }

            $clave = $cliente->id.'|'.$periodo;
            $claveImporte = $clave.'|'.$codigo;
            $operacion = $operaciones->get($clave);
            $deuda = $operacion?->deudasTributarias?->first(
                fn (DeudaTributaria $candidata): bool => (string) $candidata->tributo_codigo === $codigo,
            );
            $enArchivo = $importesArchivo[$claveImporte] ?? null;
            if ($deuda instanceof DeudaTributaria && $solicitud->politica === 'conservar' && $enArchivo !== null && ! $this->mismoMonto((float) $deuda->monto_deuda_inicial, $enArchivo)) {
                $montoTributo = (float) $deuda->monto_deuda_inicial;
            } elseif ($enArchivo !== null) {
                $montoTributo = $enArchivo;
            } elseif ($deuda instanceof DeudaTributaria) {
                $montoTributo = (float) $deuda->monto_deuda_inicial;
            } else {
                $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: no hay importe de {$codigo} {$nombre} en {$periodo}. Escríbalo en la fila del mes.",
                ];

                continue;
            }

            $observacion = $operacionNumero !== '' ? $operacionNumero : 'Carga masiva';
            $firma = $claveImporte.'|'.number_format($monto, 2, '.', '').'|'.$fecha.'|'.$medio.'|'.$observacion;
            if (isset($firmas[$firma])) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: ese pago de {$codigo} ya está en el archivo.",
                ];

                continue;
            }
            $firmas[$firma] = true;

            $abonado = $deuda instanceof DeudaTributaria ? round((float) $deuda->abonos->sum('monto'), 2) : 0.0;
            $yaRegistrado = $deuda instanceof DeudaTributaria && $deuda->abonos->contains(
                function (AbonoDeuda $abono) use ($monto, $fecha, $medio, $observacion): bool {
                    return $this->mismoMonto((float) $abono->monto, $monto)
                        && $abono->fecha_pago?->toDateString() === $fecha
                        && ($abono->medio_pago?->value ?? '') === $medio
                        && (string) $abono->observacion === $observacion;
                },
            );
            if ($yaRegistrado) {
                $iguales++;

                continue;
            }

            $acumulado[$claveImporte] = round(($acumulado[$claveImporte] ?? 0) + $monto, 2);
            if ($abonado + $acumulado[$claveImporte] > $montoTributo + 0.001) {
                $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: los pagos de {$codigo} {$nombre} superan el importe de {$this->soles($montoTributo)}.",
                ];

                continue;
            }

            $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;
            $resumen = "{$cliente->razon_social} · {$periodo} · pago {$codigo} {$nombre} → {$this->soles($monto)}";
            if ($medio !== '') {
                $resumen .= ' · '.str_replace('_', ' ', $medio);
            }

            $cambios[] = [
                'tipo' => 'pago',
                'cliente_id' => $cliente->id,
                'periodo' => $periodo,
                'fila' => $numero,
                'resumen' => $resumen,
                'datos' => [
                    'codigo' => $codigo,
                    'monto' => $monto,
                    'fecha' => $fecha,
                    'medio' => $medio !== '' ? $medio : null,
                    'numero' => $operacionNumero,
                ],
            ];
        }
    }

    /**
     * @param  list<string>  $avisos
     * @return list<string>
     */
    private function limitarAvisos(array $avisos): array
    {
        if (count($avisos) <= 8) {
            return $avisos;
        }

        $resto = count($avisos) - 8;
        $visibles = array_slice($avisos, 0, 8);
        $visibles[] = "Hay {$resto} avisos más sobre pagos. Revise esas filas en la caja.";

        return $visibles;
    }

    /**
     * @param  Collection<string, DeudaTributaria>|Collection<int, DeudaTributaria>  $deudas
     * @return array{cambio: ?array<string, mixed>, error: ?string, motivo: ?string}
     */
    private function evaluarTributo(
        Cliente $cliente,
        string $periodo,
        int $fila,
        string $codigo,
        float $monto,
        mixed $igvCelda,
        mixed $existente,
        string $politica,
    ): array {
        $deuda = $existente instanceof DeudaTributaria ? $existente : null;
        if ($deuda && $politica === 'conservar') {
            return ['cambio' => null, 'error' => null, 'motivo' => 'conservado'];
        }

        $igv = is_bool($igvCelda) ? $igvCelda : (bool) ($deuda?->es_igv_justo ?? false);
        if ($codigo !== TipoTributoSunat::Igv18->value) {
            $igv = false;
        }

        if ($deuda && $this->mismoMonto((float) $deuda->monto_deuda_inicial, $monto) && (bool) $deuda->es_igv_justo === $igv) {
            return ['cambio' => null, 'error' => null, 'motivo' => 'igual'];
        }

        $abonado = $deuda ? round((float) $deuda->abonos->sum('monto'), 2) : 0.0;
        if ($monto + 0.001 < $abonado) {
            $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;

            return [
                'cambio' => null,
                'error' => "Fila {$fila}: el importe de {$codigo} {$nombre} en {$periodo} ({$this->soles($monto)}) es menor que los pagos ya registrados ({$this->soles($abonado)}).",
                'motivo' => null,
            ];
        }

        $nombre = TipoTributoSunat::tryFrom($codigo)?->nombreCorto() ?? $codigo;
        $resumen = "{$cliente->razon_social} · {$periodo} · {$codigo} {$nombre} → {$this->soles($monto)}";
        if ($igv) {
            $resumen .= ' · IGV Justo';
        }

        return [
            'cambio' => [
                'tipo' => 'tributo',
                'cliente_id' => $cliente->id,
                'periodo' => $periodo,
                'fila' => $fila,
                'resumen' => $resumen,
                'datos' => [
                    'codigo' => $codigo,
                    'monto' => $monto,
                    'igv_justo' => $igv,
                    'vencimiento' => $this->vencimientoDe($cliente, $periodo, $codigo, $igv, $deuda),
                    'deuda_id' => $deuda?->id,
                ],
            ],
            'error' => null,
            'motivo' => null,
        ];
    }

    /**
     * @param  array<string, string>  $datos
     * @return array<string, mixed>
     */
    private function cambioEstado(Cliente $cliente, string $periodo, array $datos, ?int $fila): array
    {
        $valores = array_values($datos);
        if (count(array_unique($valores)) === 1) {
            $detalle = $this->etiquetaDeclaraciones($datos).' → '.$valores[0];
        } else {
            $partes = [];
            foreach (['sire' => 'SIRE', 'pdt' => 'PDT 621', 'ple' => 'PDT 601'] as $campo => $nombre) {
                if (array_key_exists($campo, $datos)) {
                    $partes[] = $nombre.' '.$datos[$campo];
                }
            }
            $detalle = implode(', ', $partes);
        }

        return [
            'tipo' => 'estado',
            'cliente_id' => $cliente->id,
            'periodo' => $periodo,
            'fila' => $fila,
            'resumen' => "{$cliente->razon_social} · {$periodo} · {$detalle}",
            'datos' => $datos,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $cambios
     */
    private function escribir(array $cambios): void
    {
        collect($cambios)
            ->groupBy(fn (array $cambio): string => $cambio['cliente_id'].'|'.$cambio['periodo'])
            ->each(function (SupportCollection $grupo): void {
                $this->escribirGrupo($grupo);
            });
    }

    /**
     * @param  SupportCollection<int, array<string, mixed>>  $grupo
     */
    private function escribirGrupo(SupportCollection $grupo): void
    {
        /** @var array<string, mixed> $primero */
        $primero = $grupo->first();
        $operacion = $this->operacionPara((int) $primero['cliente_id'], (string) $primero['periodo']);
        $hayEstados = false;
        $hayHonorario = false;
        $limpia = false;

        foreach ($grupo as $cambio) {
            if ($cambio['tipo'] === 'honorario') {
                $operacion->estado_pago_honorarios = $cambio['datos']['honorarios'];
                $hayHonorario = true;

                continue;
            }
            if ($cambio['tipo'] === 'limpiar') {
                $limpia = true;

                continue;
            }
            if ($cambio['tipo'] !== 'estado') {
                continue;
            }
            foreach (['sire', 'pdt', 'ple'] as $campo) {
                if (array_key_exists($campo, $cambio['datos'])) {
                    $operacion->{$this->columnaEstado($campo)} = $cambio['datos'][$campo];
                    $hayEstados = true;
                }
            }
        }

        if ($limpia) {
            $this->limpiarTributos($operacion);

            return;
        }

        $tributos = $grupo->where('tipo', 'tributo')->values();
        if ($hayEstados || $hayHonorario || $tributos->isNotEmpty()) {
            if (! $operacion->exists && $tributos->isNotEmpty()) {
                $operacion->tiene_deuda = true;
            }
            $operacion->save();
        }

        foreach ($tributos as $cambio) {
            $this->registroTributo->registrar($operacion, [
                'tributo_codigo' => $cambio['datos']['codigo'],
                'monto_deuda_inicial' => $cambio['datos']['monto'],
                'es_igv_justo' => $cambio['datos']['igv_justo'],
                'vencimiento_deuda' => $cambio['datos']['vencimiento'],
                'deuda_id' => $cambio['datos']['deuda_id'],
            ]);
        }

        if ($tributos->isNotEmpty()) {
            $operacion->save();
        }

        foreach ($grupo->where('tipo', 'pago') as $cambio) {
            $this->registrarPago($operacion, $cambio['datos']);
        }
    }

    private function limpiarTributos(Operacion $operacion): void
    {
        if (! $operacion->exists) {
            $operacion->tiene_deuda = false;
            $operacion->save();

            return;
        }

        $operacion->deudasTributarias()->get()->each(function (DeudaTributaria $deuda): void {
            $deuda->abonos()->delete();
            $deuda->delete();
        });
        $operacion->abonosDeuda()->delete();
        $operacion->unsetRelation('deudasTributarias');
        $operacion->unsetRelation('abonosDeuda');
        $operacion->refrescarResumenDeudas();
        $operacion->tiene_deuda = false;
        $operacion->save();
    }

    private function tieneTributosOPagos(?Operacion $operacion): bool
    {
        if (! $operacion instanceof Operacion) {
            return false;
        }

        if ($operacion->relationLoaded('deudasTributarias') && $operacion->deudasTributarias->isNotEmpty()) {
            return true;
        }

        if ($operacion->relationLoaded('abonosDeuda') && $operacion->abonosDeuda->isNotEmpty()) {
            return true;
        }

        return (bool) $operacion->tiene_deuda && (float) $operacion->monto_deuda_inicial > 0;
    }

    /**
     * @param  array{codigo: string, monto: float, fecha: string, medio?: ?string, numero?: string}  $datos
     */
    private function registrarPago(Operacion $operacion, array $datos): void
    {
        $deuda = $operacion->deudasTributarias()->where('tributo_codigo', $datos['codigo'])->first();
        if (! $deuda instanceof DeudaTributaria) {
            throw ValidationException::withMessages([
                'pagado' => 'No se encontró el tributo para registrar el pago.',
            ]);
        }

        $monto = round((float) $datos['monto'], 2);
        $numero = trim((string) ($datos['numero'] ?? ''));
        $medio = ($datos['medio'] ?? null) !== '' ? ($datos['medio'] ?? null) : null;
        $abonado = round((float) $deuda->abonos()->sum('monto'), 2);
        if ($abonado + $monto > (float) $deuda->monto_deuda_inicial + 0.001) {
            throw ValidationException::withMessages([
                'pagado' => 'Los pagos superan el importe del tributo.',
            ]);
        }

        $yaRegistrado = $deuda->abonos()
            ->where('monto', $monto)
            ->whereDate('fecha_pago', $datos['fecha'])
            ->when(
                $medio === null,
                fn ($consulta) => $consulta->whereNull('medio_pago'),
                fn ($consulta) => $consulta->where('medio_pago', $medio),
            )
            ->where('observacion', $numero !== '' ? $numero : 'Carga masiva')
            ->exists();
        if ($yaRegistrado) {
            return;
        }

        AbonoDeuda::create([
            'estudio_id' => $operacion->estudio_id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => $monto,
            'fecha_pago' => $datos['fecha'],
            'medio_pago' => $medio,
            'observacion' => $numero !== '' ? $numero : 'Carga masiva',
        ]);
    }

    private function operacionPara(int $clienteId, string $periodo): Operacion
    {
        $operacion = Operacion::query()->firstOrNew([
            'cliente_id' => $clienteId,
            'periodo' => $periodo,
        ]);

        if (! $operacion->exists) {
            $operacion->estado_compras_ventas = 'Pendiente';
            $operacion->estado_pdt621 = 'Pendiente';
            $operacion->estado_ple = 'Pendiente';
        }

        return $operacion;
    }

    /**
     * @param  Collection<int, Cliente>  $clientes
     * @param  list<string>  $periodos
     * @return Collection<string, Operacion>
     */
    private function operacionesDe(Collection $clientes, array $periodos, bool $conTributos): Collection
    {
        if ($clientes->isEmpty() || $periodos === []) {
            return new Collection;
        }

        $consulta = Operacion::query()
            ->whereIn('cliente_id', $clientes->modelKeys())
            ->whereIn('periodo', $periodos);

        if ($conTributos) {
            $consulta->with(['deudasTributarias.abonos', 'abonosDeuda']);
        }

        return $consulta->get()->keyBy(fn (Operacion $operacion): string => $operacion->cliente_id.'|'.$operacion->periodo);
    }

    /**
     * @return Collection<int, Cliente>
     */
    private function clientesObjetivo(SolicitudCargaMasiva $solicitud): Collection
    {
        $consulta = Cliente::query()
            ->paraUsuario()
            ->vigentesEnEjercicio($solicitud->anio)
            ->orderBy('numero');

        if ($solicitud->alcance === 'seleccion') {
            $consulta->whereIn('id', $solicitud->clienteIds);
        }

        return $consulta->get();
    }

    /**
     * @return Collection<int, Cliente>
     */
    private function clientesDelEjercicio(int $anio): Collection
    {
        return Cliente::query()
            ->paraUsuario()
            ->vigentesEnEjercicio($anio)
            ->orderBy('numero')
            ->get();
    }

    private function vencimientoDe(Cliente $cliente, string $periodo, string $codigo, bool $igvJusto, ?DeudaTributaria $deuda): ?string
    {
        $seraIgv = $codigo === TipoTributoSunat::Igv18->value && $igvJusto;
        $eraIgv = (bool) ($deuda?->es_igv_justo ?? false);

        if ($deuda && $eraIgv === $seraIgv) {
            return $deuda->vencimiento_deuda?->format('Y-m-d');
        }

        if ($seraIgv) {
            return $this->vencimientoIgvJusto($cliente, $periodo);
        }

        return $this->vencimientoOficial($cliente, $periodo);
    }

    private function vencimientoOficial(Cliente $cliente, string $periodo): ?string
    {
        $clave = $cliente->id.'|'.$periodo.'|oficial';
        if (! array_key_exists($clave, $this->fechas)) {
            $fecha = $this->cronograma->obtenerFechaVencimiento('pdt_621', $periodo, $cliente);
            $this->fechas[$clave] = $fecha?->format('Y-m-d');
        }

        return $this->fechas[$clave];
    }

    private function vencimientoIgvJusto(Cliente $cliente, string $periodo): string
    {
        $clave = $cliente->id.'|'.$periodo.'|igv';
        if (! array_key_exists($clave, $this->fechas)) {
            [$mes, $anio] = explode('-', $periodo);
            $destino = Carbon::createFromDate((int) $anio, (int) $mes, 1)->addMonthsNoOverflow(3);
            $fecha = $this->cronograma->obtenerFechaVencimiento('pdt_621', $destino->format('m-Y'), $cliente);
            $this->fechas[$clave] = ($fecha ?? $destino->copy()->endOfMonth())->format('Y-m-d');
        }

        return (string) $this->fechas[$clave];
    }

    /**
     * @param  array{
     *     cambios: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     omitidos: int,
     *     avisos: list<string>
     * }  $plan
     */
    private function resultado(bool $aplicada, array $plan): ResultadoCargaMasiva
    {
        $estados = 0;
        $tributos = 0;
        $pagos = 0;
        $honorarios = 0;
        $limpiezas = 0;
        $muestra = [];
        foreach ($plan['cambios'] as $cambio) {
            if ($cambio['tipo'] === 'tributo') {
                $tributos++;
            } elseif ($cambio['tipo'] === 'pago') {
                $pagos++;
            } elseif ($cambio['tipo'] === 'honorario') {
                $honorarios++;
            } elseif ($cambio['tipo'] === 'limpiar') {
                $limpiezas++;
            } else {
                $estados++;
            }
            if (count($muestra) < 8) {
                $muestra[] = (string) $cambio['resumen'];
            }
        }

        $hayErrores = $plan['errores'] !== [];
        $puedeAplicar = ! $hayErrores && ($estados + $tributos + $pagos + $honorarios + $limpiezas) > 0;
        $errores = $this->limitarErrores($plan['errores']);

        return new ResultadoCargaMasiva(
            aplicada: $aplicada && $puedeAplicar,
            puedeAplicar: $puedeAplicar,
            estados: $estados,
            tributos: $tributos,
            pagos: $pagos,
            honorarios: $honorarios,
            limpiezas: $limpiezas,
            omitidos: $plan['omitidos'],
            resumen: $this->resumen($aplicada && $puedeAplicar, $estados, $tributos, $pagos, $honorarios, $limpiezas, $hayErrores),
            errores: $errores,
            muestra: $hayErrores ? [] : $muestra,
            avisos: $plan['avisos'],
        );
    }

    /**
     * @param  list<array{fila: int|null, mensaje: string}>  $errores
     * @return list<array{fila: int|null, mensaje: string}>
     */
    private function limitarErrores(array $errores): array
    {
        if (count($errores) <= 40) {
            return $errores;
        }

        $resto = count($errores) - 40;
        $visibles = array_slice($errores, 0, 40);
        $visibles[] = [
            'fila' => null,
            'mensaje' => "Hay {$resto} errores más. Corrija los indicados y vuelva a revisar.",
        ];

        return $visibles;
    }

    private function resumen(bool $aplicada, int $estados, int $tributos, int $pagos, int $honorarios, int $limpiezas, bool $hayErrores): string
    {
        if ($hayErrores) {
            return 'No se guardó nada. Revise los errores antes de continuar.';
        }

        if ($estados + $tributos + $pagos + $honorarios + $limpiezas === 0) {
            return 'No hay datos nuevos para guardar. Lo que ya estaba cargado sigue igual.';
        }

        $partes = [];
        if ($estados > 0) {
            $partes[] = $estados === 1 ? '1 declaración' : "{$estados} declaraciones";
        }
        if ($honorarios > 0) {
            $partes[] = $honorarios === 1 ? '1 honorario' : "{$honorarios} honorarios";
        }
        if ($tributos > 0) {
            $partes[] = $tributos === 1 ? '1 tributo' : "{$tributos} tributos";
        }
        if ($pagos > 0) {
            $partes[] = $pagos === 1 ? '1 pago' : "{$pagos} pagos";
        }
        if ($partes === []) {
            $texto = $limpiezas === 1
                ? 'se eliminan tributos y pagos de 1 mes'
                : "se eliminan tributos y pagos de {$limpiezas} meses";

            return $aplicada ? "Guardado: {$texto}." : "Listo para guardar: {$texto}.";
        }

        $lista = $this->enumerar($partes);
        $cierre = $aplicada ? "Guardado: {$lista}." : "Listo para guardar: {$lista}.";
        if ($limpiezas > 0) {
            $meses = $limpiezas === 1 ? '1 mes' : "{$limpiezas} meses";
            $cierre = rtrim($cierre, '.').". Se eliminan tributos y pagos de {$meses}.";
        }

        return $cierre;
    }

    /**
     * @param  list<string>  $partes
     */
    private function enumerar(array $partes): string
    {
        if (count($partes) <= 1) {
            return $partes[0] ?? '';
        }

        $ultima = array_pop($partes);

        return implode(', ', $partes).' y '.$ultima;
    }

    /**
     * @return list<string>
     */
    private function avisos(int $bajas, int $celdas): array
    {
        $avisos = [];
        if ($bajas > 0) {
            $avisos[] = $bajas === 1
                ? 'Se omitió 1 periodo en el que el cliente ya no pertenece al estudio.'
                : "Se omitieron {$bajas} periodos en los que el cliente ya no pertenece al estudio.";
        }
        if ($celdas > 0) {
            $avisos[] = $celdas === 1
                ? 'Se omitió 1 celda que ya estaba cargada o que ya tenía el valor indicado.'
                : "Se omitieron {$celdas} celdas que ya estaban cargadas o que ya tenían el valor indicado.";
        }

        return $avisos;
    }

    /**
     * @param  list<array{fila: int|null, mensaje: string}>  $errores
     * @return array{cambios: list<array<string, mixed>>, errores: list<array{fila: int|null, mensaje: string}>, omitidos: int, avisos: list<string>}
     */
    private function planVacio(array $errores): array
    {
        return [
            'cambios' => [],
            'errores' => $errores,
            'omitidos' => 0,
            'avisos' => [],
        ];
    }

    private function rechazo(string $mensaje): ResultadoCargaMasiva
    {
        return new ResultadoCargaMasiva(
            aplicada: false,
            puedeAplicar: false,
            estados: 0,
            tributos: 0,
            pagos: 0,
            honorarios: 0,
            limpiezas: 0,
            omitidos: 0,
            resumen: 'No se guardó nada. Revise los errores antes de continuar.',
            errores: [['fila' => null, 'mensaje' => $mensaje]],
            muestra: [],
            avisos: [],
        );
    }

    private function mensajeValidacion(ValidationException $excepcion): string
    {
        $mensaje = collect($excepcion->errors())->flatten()->first();

        if (is_string($mensaje) && $mensaje !== '') {
            return $mensaje.' No se modificó ningún dato.';
        }

        return 'No se pudo guardar la carga. No se modificó ningún dato.';
    }

    /**
     * @param  array<string, string>  $datos
     */
    private function etiquetaDeclaraciones(array $datos): string
    {
        $nombres = [];
        foreach (['sire' => 'SIRE', 'pdt' => 'PDT 621', 'ple' => 'PDT 601'] as $campo => $nombre) {
            if (array_key_exists($campo, $datos)) {
                $nombres[] = $nombre;
            }
        }

        return implode(', ', $nombres);
    }

    private function columnaEstado(string $campo): string
    {
        return match ($campo) {
            'sire' => 'estado_compras_ventas',
            'pdt' => 'estado_pdt621',
            'ple' => 'estado_ple',
        };
    }

    /**
     * @return list<string>
     */
    private function encabezados(): array
    {
        $columnas = [
            'RUC',
            'Razon social',
            'Periodo (MM-AAAA, ejemplo 01-2026)',
            'Declaracion DJ (Pendiente, En proceso o Declarado; un solo valor para SIRE, PDT 621 y PDT 601; vacio no cambia)',
        ];
        foreach (TipoTributoSunat::catalogoFormulario() as $bloque) {
            $grupo = $bloque['titulo'].' '.$bloque['detalle'];
            foreach ($bloque['tributos'] as $tributo) {
                $columnas[] = $tributo['codigo'].' '.$tributo['nombre_corto'].' (importe a pagar '.$grupo.')';
                if ($tributo['codigo'] === TipoTributoSunat::Igv18->value) {
                    $columnas[] = 'IGV Justo (SI o NO; solo aplica al 1011 y usa prorroga de 3 meses)';
                }
            }
        }

        return $columnas;
    }

    /**
     * @return list<string>
     */
    private function encabezadosPagos(): array
    {
        return [
            'RUC',
            'Razon social',
            'Periodo (MM-AAAA)',
            'Codigo del tributo (el mismo codigo de la fila del mes: 1011, 5210, AFP u otro)',
            'Monto del pago',
            'Fecha de pago (DD/MM/AAAA)',
            'Medio de pago (Detracciones, Caja, Bancos, Compensacion renta anual o Compensacion ITAN)',
            'Numero de operacion (opcional)',
        ];
    }

    /**
     * @return list<string>
     */
    private function periodosDelAnio(int $anio): array
    {
        $periodos = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $periodos[] = sprintf('%02d-%d', $mes, $anio);
        }

        return $periodos;
    }

    private function ruc(string $ruc): string
    {
        return preg_replace('/\D/', '', $ruc) ?? '';
    }

    private function soles(float $monto): string
    {
        return 'S/ '.number_format($monto, 2);
    }

    private function mismoMonto(float $actual, float $nuevo): bool
    {
        return abs($actual - $nuevo) < 0.001;
    }

    /**
     * @param  list<string>  $celdas
     */
    private function linea(array $celdas): string
    {
        return implode(';', array_map(fn (string $celda): string => $this->celda($celda), $celdas));
    }

    private function celda(string $valor): string
    {
        if (preg_match('/^[=+\-@]/', $valor) === 1) {
            $valor = "'".$valor;
        }

        if (strpbrk($valor, ";\"\n\r") !== false) {
            return '"'.str_replace('"', '""', $valor).'"';
        }

        return $valor;
    }
}
