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
        return $solicitud->modo === 'plantilla'
            ? $this->planPlantilla($solicitud)
            : $this->planEstados($solicitud);
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

        $operaciones = $this->operacionesDe($clientes, $periodos, false);
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
                    if ($solicitud->politica === 'conservar' && $actual !== 'Pendiente') {
                        $conservados++;

                        continue;
                    }
                    $datos[$clave] = $solicitud->estado;
                }

                if ($datos === []) {
                    continue;
                }

                $cambios[] = $this->cambioEstado($cliente, $periodo, $datos, null);
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
                $this->planPagos($cliente, $periodo, $numero, $fila, $deudas, $solicitud->politica, $cambios, $iguales, $avisosPago, $errores);
            }
        }

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
            $fechaBase = Carbon::createFromFormat('!m-Y', $periodo);
            $fecha = $vencimiento ?? ($fechaBase === false ? null : $fechaBase->endOfMonth()->toDateString());
            if ($fecha === null) {
                $avisosPago[] = "Fila {$fila}: no se pudo fechar el pago de {$codigo} {$nombre}.";

                continue;
            }

            $cambios[] = [
                'tipo' => 'pago',
                'cliente_id' => $cliente->id,
                'periodo' => $periodo,
                'fila' => $fila,
                'resumen' => "{$cliente->razon_social} · {$periodo} · pago {$codigo} {$nombre} → {$this->soles($monto)}",
                'datos' => [
                    'codigo' => $codigo,
                    'monto' => $monto,
                    'fecha' => $fecha,
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

        foreach ($grupo as $cambio) {
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

        $tributos = $grupo->where('tipo', 'tributo')->values();
        if ($hayEstados || $tributos->isNotEmpty()) {
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

    /**
     * @param  array{codigo: string, monto: float, fecha: string}  $datos
     */
    private function registrarPago(Operacion $operacion, array $datos): void
    {
        $deuda = $operacion->deudasTributarias()->where('tributo_codigo', $datos['codigo'])->first();
        if (! $deuda instanceof DeudaTributaria) {
            throw ValidationException::withMessages([
                'pagado' => 'No se encontró el tributo para registrar el pago.',
            ]);
        }

        if ((float) $deuda->abonos()->sum('monto') > 0.001) {
            return;
        }

        if (! $this->mismoMonto((float) $deuda->monto_deuda_inicial, (float) $datos['monto'])) {
            throw ValidationException::withMessages([
                'pagado' => 'El pago no coincide con el importe guardado del tributo.',
            ]);
        }

        AbonoDeuda::create([
            'estudio_id' => $operacion->estudio_id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => $deuda->monto_deuda_inicial,
            'fecha_pago' => $datos['fecha'],
            'observacion' => 'Carga masiva',
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
            $consulta->with('deudasTributarias.abonos');
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
        $muestra = [];
        foreach ($plan['cambios'] as $cambio) {
            if ($cambio['tipo'] === 'tributo') {
                $tributos++;
            } elseif ($cambio['tipo'] === 'pago') {
                $pagos++;
            } else {
                $estados++;
            }
            if (count($muestra) < 8) {
                $muestra[] = (string) $cambio['resumen'];
            }
        }

        $hayErrores = $plan['errores'] !== [];
        $puedeAplicar = ! $hayErrores && ($estados + $tributos + $pagos) > 0;
        $errores = $this->limitarErrores($plan['errores']);

        return new ResultadoCargaMasiva(
            aplicada: $aplicada && $puedeAplicar,
            puedeAplicar: $puedeAplicar,
            estados: $estados,
            tributos: $tributos,
            pagos: $pagos,
            omitidos: $plan['omitidos'],
            resumen: $this->resumen($aplicada && $puedeAplicar, $estados, $tributos, $pagos, $hayErrores),
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

    private function resumen(bool $aplicada, int $estados, int $tributos, int $pagos, bool $hayErrores): string
    {
        if ($hayErrores) {
            return 'No se guardó nada. Revise los errores antes de continuar.';
        }

        if ($estados + $tributos + $pagos === 0) {
            return 'No hay datos nuevos para guardar. Lo que ya estaba cargado sigue igual.';
        }

        $partes = [];
        if ($estados > 0) {
            $partes[] = $estados === 1 ? '1 declaración' : "{$estados} declaraciones";
        }
        if ($tributos > 0) {
            $partes[] = $tributos === 1 ? '1 tributo' : "{$tributos} tributos";
        }
        if ($pagos > 0) {
            $partes[] = $pagos === 1 ? '1 pago' : "{$pagos} pagos";
        }
        $lista = $this->enumerar($partes);

        return $aplicada ? "Guardado: {$lista}." : "Listo para guardar: {$lista}.";
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
        $columnas = ['ruc', 'razon_social', 'periodo', 'sire', 'pdt_621', 'pdt_601', 'pagado'];
        foreach (TipoTributoSunat::catalogoFormulario() as $bloque) {
            foreach ($bloque['tributos'] as $tributo) {
                $columnas[] = $tributo['codigo'].' '.$tributo['nombre_corto'];
                if ($tributo['codigo'] === TipoTributoSunat::Igv18->value) {
                    $columnas[] = 'igv_justo';
                }
            }
        }

        return $columnas;
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
