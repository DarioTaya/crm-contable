<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Models\DeudaTributaria;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Services\ControlImpuestos\RegistrarTributoPeriodo;
use App\Services\CronogramaSunatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlMensualController extends Controller
{
    public function __construct(private RegistrarTributoPeriodo $registroTributo) {}

    /**
     * Muestra la Matriz Anual estilo Spreadsheet (Sábana de datos Excel).
     */
    public function index(Request $request): View
    {
        $anioActual = (int) now()->year;
        $anio = (int) $request->query('anio', $anioActual);

        if ($anio < 2000 || $anio > 2100) {
            $anio = $anioActual;
        }

        // Rango de años para selector (2 años antes, 2 años después)
        $aniosDisponibles = range($anioActual - 2, $anioActual + 2);

        $mesesEncabezado = [
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

        $kpis = $this->calcularKpis($anio);

        // Cargar clientes vigentes en el año seleccionado ordenados por número.
        // Se excluyen clientes que se retiraron ANTES del año en consulta.
        $clientes = Cliente::query()
            ->vigentesEnEjercicio($anio)
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $this->periodosDelAnio($anio))->with(['abonosDeuda', 'deudasTributarias.abonos', 'liquidacionPeriodo'])])
            ->orderBy('numero')
            ->get();

        $periodosAnio = $this->periodosDelAnio($anio);
        $cronogramasPdt = CronogramaSunat::query()
            ->where('tipo', 'pdt_621')
            ->whereIn('periodo', $periodosAnio)
            ->get()
            ->keyBy('periodo');

        $matrizClientes = [];

        foreach ($clientes as $cliente) {
            $opsMap = $cliente->operaciones->keyBy('periodo');
            $meses = [];
            $cDeclaradas = 0;
            $cExigibles = 0;

            for ($m = 1; $m <= 12; $m++) {
                $periodo = sprintf('%02d-%d', $m, $anio);
                $op = $opsMap->get($periodo);

                $cronogramaPdt = $cronogramasPdt->get($periodo);
                $fechaOficialPdt = $cronogramaPdt
                    ? $cronogramaPdt->getFechaPorDigito($cliente->ruc ?? $cliente->numero_documento, (bool) $cliente->es_buen_contribuyente)
                    : null;
                $vencimientoOficialPdt = $fechaOficialPdt?->format('Y-m-d') ?? '';
                $vencimientoOficialPdtDmy = $fechaOficialPdt?->format('d/m/Y') ?? '';

                $esBajaMes = ! $cliente->rindeEnPeriodo($periodo);

                if ($esBajaMes) {
                    $meses[] = [
                        'numero' => $m,
                        'nombre' => $mesesEncabezado[$m]['nombre'],
                        'corto' => $mesesEncabezado[$m]['corto'],
                        'periodo' => $periodo,
                        'estado' => 'Baja',
                        'es_baja' => true,
                        'motivo_baja' => $cliente->motivo_baja ?: 'Retirado / Baja del Estudio',
                        'honorarios' => '—',
                        'sire' => '—',
                        'pdt' => '—',
                        'ple' => '—',
                        'notas' => 'Cliente no activo en este periodo (Baja del Estudio Contable)',
                        'vencimiento_oficial_pdt' => $vencimientoOficialPdt,
                        'vencimiento_oficial_pdt_dmy' => $vencimientoOficialPdtDmy,
                    ];

                    continue;
                }

                $cExigibles++;

                $sire = $op->estado_compras_ventas ?? 'Pendiente';
                $pdt = $op->estado_pdt621 ?? 'Pendiente';
                $ple = $op->estado_ple ?? 'Pendiente';
                $notasMes = $op->notas ?? '';
                $honorariosMes = $op->estado_pago_honorarios ?? 'Pendiente';

                $estadoMaestro = $this->determinarEstadoMaestro($sire, $pdt, $ple);

                if ($estadoMaestro === 'Declarado') {
                    $cDeclaradas++;
                }

                $periodoAnterior = $m > 1 ? sprintf('%02d-%d', $m - 1, $anio) : sprintf('12-%d', $anio - 1);
                $opAnterior = $opsMap->get($periodoAnterior);
                $saldoFavorSugerido = (float) ($opAnterior?->liquidacionPeriodo?->igv_saldo_favor_siguiente ?? 0);

                $liq = $op?->liquidacionPeriodo;
                $liqData = $liq ? [
                    'id' => $liq->id,
                    'es_zona_selva' => (bool) $liq->es_zona_selva,
                    'ventas_base' => (float) $liq->ventas_base,
                    'igv_ventas' => (float) $liq->igv_ventas,
                    'ventas_no_gravadas' => (float) $liq->ventas_no_gravadas,
                    'compras_base' => (float) $liq->compras_base,
                    'igv_compras' => (float) $liq->igv_compras,
                    'igv_saldo_favor_anterior' => (float) $liq->igv_saldo_favor_anterior,
                    'igv_retenciones' => (float) $liq->igv_retenciones,
                    'igv_percepciones' => (float) $liq->igv_percepciones,
                    'igv_saldo_favor_siguiente' => (float) $liq->igv_saldo_favor_siguiente,
                    'igv_resultante_pagar' => (float) $liq->igv_resultante_pagar,
                    'renta_base_ingresos' => (float) $liq->renta_base_ingresos,
                    'renta_tasa' => (float) $liq->renta_tasa,
                    'renta_calculada' => (float) $liq->renta_calculada,
                    'renta_saldo_favor_anterior' => (float) $liq->renta_saldo_favor_anterior,
                    'renta_compensacion_itan' => (float) $liq->renta_compensacion_itan,
                    'renta_retenciones' => (float) $liq->renta_retenciones,
                    'renta_resultante_pagar' => (float) $liq->renta_resultante_pagar,
                    'notas_liquidacion' => (string) ($liq->notas_liquidacion ?? ''),
                ] : null;

                $meses[] = [
                    'numero' => $m,
                    'nombre' => $mesesEncabezado[$m]['nombre'],
                    'corto' => $mesesEncabezado[$m]['corto'],
                    'periodo' => $periodo,
                    'estado' => $estadoMaestro,
                    'es_baja' => false,
                    'motivo_baja' => null,
                    'honorarios' => $honorariosMes,
                    'sire' => $sire,
                    'pdt' => $pdt,
                    'ple' => $ple,
                    'notas' => $notasMes,
                    'tiene_deuda' => (bool) ($op->tiene_deuda ?? false),
                    'tributo_codigo' => $op?->tributo_codigo ?? '1011',
                    'tributo_nombre' => $op?->tributo_nombre ?? 'IGV Cuenta Propia 18%',
                    'tributo_nombre_corto' => $op?->tributo_nombre_corto ?? 'IGV 18%',
                    'tributo_etiqueta' => $op?->tributo_etiqueta ?? '1011 - IGV Cuenta Propia 18%',
                    'es_igv_justo' => (bool) ($op?->es_igv_justo ?? false),
                    'monto_deuda_inicial' => $op ? $this->montoDeudaMes($op) : 0,
                    'vencimiento_deuda' => $op?->vencimiento_deuda ? $op->vencimiento_deuda->format('Y-m-d') : ($op?->es_igv_justo ? '' : $vencimientoOficialPdt),
                    'vencimiento_deuda_dmy' => $op?->vencimiento_deuda ? $op->vencimiento_deuda->format('d/m/Y') : ($op?->es_igv_justo ? '' : $vencimientoOficialPdtDmy),
                    'vencimiento_oficial_pdt' => $vencimientoOficialPdt,
                    'vencimiento_oficial_pdt_dmy' => $vencimientoOficialPdtDmy,
                    'saldo_pendiente' => $op ? $this->saldoDeudaMes($op) : 0,
                    'total_abonado' => $op ? $this->abonadoDeudaMes($op) : 0,
                    'operacion_id' => $op?->id,
                    'saldo_favor_anterior_sugerido' => $saldoFavorSugerido,
                    'liquidacion' => $liqData,
                    'deudas' => $op ? $this->arregloDeudas($op, $vencimientoOficialPdt, $vencimientoOficialPdtDmy) : [],
                    'abonos' => $op?->abonosDeuda ? $op->abonosDeuda->sortByDesc('fecha_pago')->map(fn ($abono) => [
                        'id' => $abono->id,
                        'deuda_tributaria_id' => $abono->deuda_tributaria_id,
                        'monto' => (float) $abono->monto,
                        'monto_formateado' => $abono->monto_formateado,
                        'fecha_pago' => $abono->fecha_pago?->format('d/m/Y') ?? '',
                        'observacion' => $abono->observacion ?? '',
                    ])->values()->all() : [],
                ];
            }

            $cPorcentaje = $cExigibles > 0 ? (int) round(($cDeclaradas / $cExigibles) * 100) : 100;

            $matrizClientes[] = [
                'numero' => $cliente->numero,
                'cliente' => $cliente,
                'meses' => $meses,
                'declaradas' => $cDeclaradas,
                'porcentaje' => $cPorcentaje,
            ];
        }

        $catalogoTributos = TipoTributoSunat::catalogoFormulario();
        $cargaMasiva = $this->configuracionCargaMasiva($anio, $mesesEncabezado);

        return view('control.index', array_merge(compact(
            'anio',
            'aniosDisponibles',
            'mesesEncabezado',
            'matrizClientes',
            'catalogoTributos',
            'cargaMasiva',
        ), $kpis));
    }

    /**
     * Datos del panel opcional de carga masiva. No interviene en el llenado manual.
     *
     * @param  array<int, array{corto: string, nombre: string}>  $mesesEncabezado
     * @return array<string, mixed>
     */
    private function configuracionCargaMasiva(int $anio, array $mesesEncabezado): array
    {
        $mesHasta = $anio === (int) now()->year ? max(1, (int) now()->month - 1) : 12;

        return [
            'anio' => $anio,
            'mesHastaSugerido' => $mesHasta,
            'meses' => collect($mesesEncabezado)->map(fn (array $meta, int $numero): array => [
                'numero' => $numero,
                'nombre' => $meta['nombre'],
            ])->values()->all(),
            'clientes' => Cliente::query()
                ->paraUsuario()
                ->vigentesEnEjercicio($anio)
                ->orderBy('numero')
                ->get(['id', 'numero', 'razon_social', 'ruc', 'estado', 'mes_baja', 'regimen_tributario'])
                ->map(fn (Cliente $cliente): array => [
                    'id' => $cliente->id,
                    'numero' => $cliente->numero,
                    'razon_social' => $cliente->razon_social,
                    'ruc' => $cliente->ruc,
                    'estado' => $cliente->estado instanceof EstadoCliente ? $cliente->estado->value : (string) $cliente->estado,
                    'mes_baja' => $cliente->mes_baja,
                    'regimen' => $cliente->regimen_tributario instanceof RegimenTributario ? $cliente->regimen_tributario->value : (string) $cliente->regimen_tributario,
                ])->values()->all(),
            'plantillaUrl' => route('control.carga_masiva.plantilla', ['anio' => $anio]),
            'previsualizarUrl' => route('control.carga_masiva.previsualizar'),
            'aplicarUrl' => route('control.carga_masiva.aplicar'),
        ];
    }

    /**
     * Calcula los KPIs unificados para el año seleccionado.
     */
    public function calcularKpis(int $anio): array
    {
        $clientes = Cliente::query()
            ->vigentesEnEjercicio($anio)
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $this->periodosDelAnio($anio))])
            ->get();

        $totalClientes = $clientes->count();
        $totalObligacionesAnio = 0;
        $declaradasTotal = 0;
        $procesandoTotal = 0;
        $pendientesTotal = 0;
        $honorariosPagadosTotal = 0;
        $honorariosPendientesTotal = 0;

        foreach ($clientes as $cliente) {
            $opsMap = $cliente->operaciones->keyBy('periodo');

            for ($m = 1; $m <= 12; $m++) {
                $periodo = sprintf('%02d-%d', $m, $anio);

                if (! $cliente->rindeEnPeriodo($periodo)) {
                    continue;
                }

                $totalObligacionesAnio++;
                $op = $opsMap->get($periodo);

                $sire = $op->estado_compras_ventas ?? 'Pendiente';
                $pdt = $op->estado_pdt621 ?? 'Pendiente';
                $ple = $op->estado_ple ?? 'Pendiente';
                $honorariosMes = $op->estado_pago_honorarios ?? 'Pendiente';

                $estadoMaestro = $this->determinarEstadoMaestro($sire, $pdt, $ple);

                match ($estadoMaestro) {
                    'Declarado' => $declaradasTotal++,
                    'Procesando' => $procesandoTotal++,
                    default => $pendientesTotal++,
                };

                if ($honorariosMes === 'Pagado') {
                    $honorariosPagadosTotal++;
                } else {
                    $honorariosPendientesTotal++;
                }
            }
        }

        $porcentajeAvanceAnual = $totalObligacionesAnio > 0
            ? (int) round(($declaradasTotal / $totalObligacionesAnio) * 100)
            : 0;

        return [
            'totalClientes' => $totalClientes,
            'totalObligacionesAnio' => $totalObligacionesAnio,
            'declaradasTotal' => $declaradasTotal,
            'procesandoTotal' => $procesandoTotal,
            'pendientesTotal' => $pendientesTotal,
            'porcentajeAvanceAnual' => $porcentajeAvanceAnual,
            'honorariosPagadosTotal' => $honorariosPagadosTotal,
            'honorariosPendientesTotal' => $honorariosPendientesTotal,
        ];
    }

    /**
     * Determina el estado maestro unificado de un mes a partir de sus 3 sub-estados.
     */
    private function determinarEstadoMaestro(string $sire, string $pdt, string $ple): string
    {
        if ($sire === 'Declarado' && $pdt === 'Declarado' && $ple === 'Declarado') {
            return 'Declarado';
        }

        if ($sire === 'Procesando' || $pdt === 'Procesando' || $ple === 'Procesando'
            || $sire === 'Declarado' || $pdt === 'Declarado' || $ple === 'Declarado') {
            return 'Procesando';
        }

        return 'Pendiente';
    }

    /**
     * Guarda o actualiza los datos de operaciones vía AJAX (soporta guardado de interruptor maestro, honorarios, notas o fila anual).
     */
    public function update(Request $request): JsonResponse
    {
        // 1. Guardado por lote anual de la fila completa del cliente
        if ($request->has('meses') && is_array($request->input('meses'))) {
            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
                'meses' => ['required', 'array'],
                'meses.*.periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
                'meses.*.sire' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'meses.*.pdt' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'meses.*.ple' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'El cliente no existe.',
                'meses.required' => 'Los meses son obligatorios.',
            ]);

            $clienteId = $validated['cliente_id'];
            $notas = $validated['notas'] ?? null;

            foreach ($validated['meses'] as $m) {
                Operacion::updateOrCreate(
                    [
                        'cliente_id' => $clienteId,
                        'periodo' => $m['periodo'],
                    ],
                    [
                        'estado_compras_ventas' => $m['sire'],
                        'estado_pdt621' => $m['pdt'],
                        'estado_ple' => $m['ple'],
                        'notas' => $notas,
                    ]
                );
            }

            $anio = (int) explode('-', $validated['meses'][0]['periodo'])[1];

            return response()->json([
                'success' => true,
                'message' => 'Fila anual guardada exitosamente.',
                'kpis' => $this->calcularKpis($anio),
            ]);
        }

        // Normalizar periodo si viene YYYY-MM
        if ($request->has('periodo') && preg_match('/^(\d{4})-(\d{2})$/', (string) $request->input('periodo'), $m)) {
            $request->merge(['periodo' => "{$m[2]}-{$m[1]}"]);
        }

        // 2. Guardado del interruptor maestro mensual, honorarios, deuda o nota mensual
        if ($request->has('estado') || $request->has('honorarios') || $request->has('estado_pago_honorarios') || $request->has('tiene_deuda') || (! $request->has('estado_compras_ventas') && $request->has('notas'))) {
            if ($request->has('monto_deuda_inicial') && is_string($request->input('monto_deuda_inicial'))) {
                $request->merge([
                    'monto_deuda_inicial' => (float) str_replace(',', '', $request->input('monto_deuda_inicial')),
                ]);
            }

            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
                'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
                'estado' => ['nullable', 'string', 'in:Pendiente,Procesando,Declarado'],
                'estado_compras_ventas' => ['nullable', 'string', 'in:Pendiente,Procesando,Declarado'],
                'estado_pdt621' => ['nullable', 'string', 'in:Pendiente,Procesando,Declarado'],
                'estado_ple' => ['nullable', 'string', 'in:Pendiente,Procesando,Declarado'],
                'honorarios' => ['nullable', 'string', 'in:Pendiente,Pagado'],
                'estado_pago_honorarios' => ['nullable', 'string', 'in:Pendiente,Pagado'],
                'tiene_deuda' => ['nullable', 'boolean'],
                'descartar_deuda' => ['nullable', 'boolean'],
                'guardar_tributo' => ['nullable', 'boolean'],
                'deuda_id' => ['nullable', 'integer'],
                'eliminar_deuda_id' => ['nullable', 'integer'],
                'tributo_codigo' => ['nullable', 'string', 'max:20'],
                'es_igv_justo' => ['nullable', 'boolean'],
                'monto_deuda_inicial' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
                'vencimiento_deuda' => ['nullable', 'date'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'El cliente no existe.',
                'periodo.required' => 'El periodo contable es obligatorio.',
                'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
                'estado.in' => 'El estado maestro debe ser Pendiente, Procesando o Declarado.',
                'honorarios.in' => 'El estado de honorarios debe ser Pendiente o Pagado.',
            ]);

            $operacion = Operacion::firstOrNew([
                'cliente_id' => $validated['cliente_id'],
                'periodo' => $validated['periodo'],
            ]);

            if (isset($validated['estado'])) {
                $operacion->estado_compras_ventas = $validated['estado'];
                $operacion->estado_pdt621 = $validated['estado'];
                $operacion->estado_ple = $validated['estado'];
            } else {
                if (isset($validated['estado_compras_ventas'])) {
                    $operacion->estado_compras_ventas = $validated['estado_compras_ventas'];
                } elseif (! $operacion->exists) {
                    $operacion->estado_compras_ventas = 'Pendiente';
                }

                if (isset($validated['estado_pdt621'])) {
                    $operacion->estado_pdt621 = $validated['estado_pdt621'];
                } elseif (! $operacion->exists) {
                    $operacion->estado_pdt621 = 'Pendiente';
                }

                if (isset($validated['estado_ple'])) {
                    $operacion->estado_ple = $validated['estado_ple'];
                } elseif (! $operacion->exists) {
                    $operacion->estado_ple = 'Pendiente';
                }
            }

            if ($request->has('honorarios')) {
                $operacion->estado_pago_honorarios = $validated['honorarios'];
            } elseif ($request->has('estado_pago_honorarios')) {
                $operacion->estado_pago_honorarios = $validated['estado_pago_honorarios'];
            }

            if ($request->has('notas')) {
                $operacion->notas = $validated['notas'];
            }

            if ($request->filled('eliminar_deuda_id')) {
                if (! $operacion->exists) {
                    $operacion->save();
                }
                $deuda = $operacion->deudasTributarias()->whereKey($validated['eliminar_deuda_id'])->first();
                if ($deuda) {
                    $deuda->abonos()->delete();
                    $deuda->delete();
                }
                $operacion->refrescarResumenDeudas();
                $operacion->tiene_deuda = $operacion->deudasTributarias()->exists()
                    ? true
                    : (bool) ($validated['tiene_deuda'] ?? false);
            } elseif ($request->boolean('guardar_tributo')) {
                if (! $operacion->exists) {
                    $operacion->tiene_deuda = true;
                    $operacion->save();
                }
                $this->registroTributo->registrar($operacion, $validated);
            } elseif ($request->has('tiene_deuda')) {
                $operacion->tiene_deuda = $validated['tiene_deuda'];
                $conservarDetalle = $operacion->exists && $operacion->deudasTributarias()->exists();
                if ($validated['tiene_deuda'] && ! $conservarDetalle) {
                    $operacion->tributo_codigo = $validated['tributo_codigo'] ?? ($operacion->tributo_codigo ?: '1011');
                    $operacion->es_igv_justo = $request->boolean('es_igv_justo');
                    if (array_key_exists('monto_deuda_inicial', $validated) && $validated['monto_deuda_inicial'] !== null) {
                        $operacion->monto_deuda_inicial = $validated['monto_deuda_inicial'];
                    }
                    if (array_key_exists('vencimiento_deuda', $validated) && $validated['vencimiento_deuda'] !== null) {
                        $operacion->vencimiento_deuda = $validated['vencimiento_deuda'];
                    }
                }
                if (! $validated['tiene_deuda'] && $request->boolean('descartar_deuda')) {
                    $operacion->deudasTributarias()->get()->each(function (DeudaTributaria $deuda): void {
                        $deuda->abonos()->delete();
                        $deuda->delete();
                    });
                    $operacion->tributo_codigo = null;
                    $operacion->es_igv_justo = false;
                    $operacion->monto_deuda_inicial = null;
                    $operacion->vencimiento_deuda = null;
                    $operacion->abonosDeuda()->delete();
                }
            } elseif ($request->has('tributo_codigo')) {
                $operacion->tributo_codigo = $validated['tributo_codigo'];
            }

            $operacion->save();
            $operacion->load(['abonosDeuda', 'deudasTributarias.abonos']);

            $cliente = $operacion->cliente ?? Cliente::find($validated['cliente_id']);
            $cronogramaService = app(CronogramaSunatService::class);
            $fechaOficialPdt = $cliente ? $cronogramaService->obtenerFechaVencimiento('pdt_621', $validated['periodo'], $cliente) : null;
            $vencimientoOficialPdt = $fechaOficialPdt?->format('Y-m-d') ?? '';
            $vencimientoOficialPdtDmy = $fechaOficialPdt?->format('d/m/Y') ?? '';

            $anio = (int) explode('-', $validated['periodo'])[1];

            return response()->json([
                'success' => true,
                'message' => 'Operación actualizada exitosamente.',
                'operacion_id' => $operacion->id,
                'sire' => $operacion->estado_compras_ventas ?? 'Pendiente',
                'pdt' => $operacion->estado_pdt621 ?? 'Pendiente',
                'ple' => $operacion->estado_ple ?? 'Pendiente',
                'estado' => $this->determinarEstadoMaestro(
                    $operacion->estado_compras_ventas ?? 'Pendiente',
                    $operacion->estado_pdt621 ?? 'Pendiente',
                    $operacion->estado_ple ?? 'Pendiente',
                ),
                'tiene_deuda' => $operacion->tiene_deuda,
                'tributo_codigo' => $operacion->tributo_codigo,
                'tributo_nombre' => $operacion->tributo_nombre,
                'tributo_nombre_corto' => $operacion->tributo_nombre_corto,
                'tributo_etiqueta' => $operacion->tributo_etiqueta,
                'es_igv_justo' => (bool) $operacion->es_igv_justo,
                'monto_deuda_inicial' => $operacion->monto_deuda_inicial,
                'monto_deuda_inicial_formateado' => $operacion->monto_deuda_inicial_formateado,
                'vencimiento_deuda' => $operacion->vencimiento_deuda?->format('Y-m-d') ?: ($operacion->es_igv_justo ? '' : $vencimientoOficialPdt),
                'vencimiento_deuda_dmy' => $operacion->vencimiento_deuda?->format('d/m/Y') ?: ($operacion->es_igv_justo ? '' : $vencimientoOficialPdtDmy),
                'vencimiento_oficial_pdt' => $vencimientoOficialPdt,
                'vencimiento_oficial_pdt_dmy' => $vencimientoOficialPdtDmy,
                'total_abonado' => $operacion->total_abonado,
                'total_abonado_formateado' => $operacion->total_abonado_formateado,
                'saldo_pendiente' => $this->saldoDeudaMes($operacion),
                'saldo_pendiente_formateado' => 'S/ '.number_format($this->saldoDeudaMes($operacion), 2),
                'deudas' => $this->arregloDeudas($operacion, $vencimientoOficialPdt, $vencimientoOficialPdtDmy),
                'abonos' => $operacion->abonosDeuda->sortByDesc('fecha_pago')->map(fn ($abono) => [
                    'id' => $abono->id,
                    'monto' => (float) $abono->monto,
                    'monto_formateado' => $abono->monto_formateado,
                    'fecha_pago' => $abono->fecha_pago?->format('d/m/Y') ?? '',
                    'observacion' => $abono->observacion ?? '',
                ])->values()->all(),
                'kpis' => $this->calcularKpis($anio),
            ]);
        }

        // 3. Guardado individual de mes con detalle de obligaciones (compatibilidad previa)
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
            'estado_compras_ventas' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_pdt621' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_ple' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente especificado no existe.',
            'periodo.required' => 'El periodo contable es obligatorio.',
            'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
            'estado_compras_ventas.in' => 'El estado de Compras/Ventas (SIRE) es inválido.',
            'estado_pdt621.in' => 'El estado de PDT 621 es inválido.',
            'estado_ple.in' => 'El estado de PLE es inválido.',
        ]);

        $operacion = Operacion::updateOrCreate(
            [
                'cliente_id' => $validated['cliente_id'],
                'periodo' => $validated['periodo'],
            ],
            [
                'estado_compras_ventas' => $validated['estado_compras_ventas'],
                'estado_pdt621' => $validated['estado_pdt621'],
                'estado_ple' => $validated['estado_ple'],
                'notas' => $validated['notas'] ?? null,
            ]
        );

        $anio = (int) explode('-', $validated['periodo'])[1];

        return response()->json([
            'success' => true,
            'message' => 'Operación guardada exitosamente.',
            'operacion' => $operacion,
            'operacion_id' => $operacion->id,
            'sire' => $operacion->estado_compras_ventas,
            'pdt' => $operacion->estado_pdt621,
            'ple' => $operacion->estado_ple,
            'estado' => $this->determinarEstadoMaestro(
                $operacion->estado_compras_ventas,
                $operacion->estado_pdt621,
                $operacion->estado_ple,
            ),
            'kpis' => $this->calcularKpis($anio),
        ]);
    }

    /**
     * Guarda o actualiza la liquidación mensual de IGV y Renta (PDT 621).
     */
    public function guardarLiquidacion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
            'es_zona_selva' => ['nullable', 'boolean'],
            'ventas_base' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'igv_ventas' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'ventas_no_gravadas' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'compras_base' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'igv_compras' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'igv_saldo_favor_anterior' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'igv_retenciones' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'igv_percepciones' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_base_ingresos' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_tasa' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'renta_calculada' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_saldo_favor_anterior' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_compensacion_itan' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_retenciones' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'renta_tributo_codigo' => ['nullable', 'string', 'max:20'],
            'vencimiento_deuda' => ['nullable', 'date'],
            'notas_liquidacion' => ['nullable', 'string', 'max:1000'],
            'actualizar_deudas' => ['nullable', 'boolean'],
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente no existe.',
            'periodo.required' => 'El periodo contable es obligatorio.',
            'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);

        $operacion = Operacion::firstOrCreate(
            [
                'cliente_id' => $cliente->id,
                'periodo' => $validated['periodo'],
            ],
            [
                'estudio_id' => $cliente->estudio_id,
            ]
        );

        if (! $operacion->estudio_id && $cliente->estudio_id) {
            $operacion->estudio_id = $cliente->estudio_id;
            $operacion->save();
        }

        $liquidacion = LiquidacionPeriodo::firstOrNew([
            'operacion_id' => $operacion->id,
        ]);

        $liquidacion->fill([
            'estudio_id' => $cliente->estudio_id,
            'cliente_id' => $cliente->id,
            'periodo' => $validated['periodo'],
            'es_zona_selva' => $request->boolean('es_zona_selva'),
            'ventas_base' => $validated['ventas_base'] ?? 0,
            'igv_ventas' => $validated['igv_ventas'] ?? 0,
            'ventas_no_gravadas' => $validated['ventas_no_gravadas'] ?? 0,
            'compras_base' => $validated['compras_base'] ?? 0,
            'igv_compras' => $validated['igv_compras'] ?? 0,
            'igv_saldo_favor_anterior' => $validated['igv_saldo_favor_anterior'] ?? 0,
            'igv_retenciones' => $validated['igv_retenciones'] ?? 0,
            'igv_percepciones' => $validated['igv_percepciones'] ?? 0,
            'renta_base_ingresos' => $validated['renta_base_ingresos'] ?? 0,
            'renta_tasa' => $validated['renta_tasa'] ?? 0,
            'renta_calculada' => $validated['renta_calculada'] ?? 0,
            'renta_saldo_favor_anterior' => $validated['renta_saldo_favor_anterior'] ?? 0,
            'renta_compensacion_itan' => $validated['renta_compensacion_itan'] ?? 0,
            'renta_retenciones' => $validated['renta_retenciones'] ?? 0,
            'notas_liquidacion' => $validated['notas_liquidacion'] ?? null,
        ]);

        $liquidacion->recalcularDeterminacion();
        $liquidacion->save();

        $cronogramaService = app(CronogramaSunatService::class);
        $fechaOficialPdt = $cronogramaService->obtenerFechaVencimiento('pdt_621', $validated['periodo'], $cliente);
        if (! $fechaOficialPdt) {
            [$mesP, $anioP] = explode('-', $validated['periodo']);
            $fechaOficialPdt = Carbon::create((int) $anioP, (int) $mesP, 1)->addMonth()->setDay(20);
        }
        $vencimientoOficialPdt = $fechaOficialPdt->format('Y-m-d');
        $vencimientoOficialPdtDmy = $fechaOficialPdt->format('d/m/Y');

        if ($request->boolean('actualizar_deudas', true)) {
            // Vencimiento de IGV
            $vencimientoIgv = $vencimientoOficialPdt;
            if ($operacion->es_igv_justo) {
                [$mesP, $anioP] = explode('-', $validated['periodo']);
                $fechaBase = Carbon::create((int) $anioP, (int) $mesP, 1)->addMonths(3);
                $periodoDestino = $fechaBase->format('m-Y');
                $fechaProrrogada = $cronogramaService->obtenerFechaVencimiento('pdt_621', $periodoDestino, $cliente);
                $vencimientoIgv = $fechaProrrogada ? $fechaProrrogada->format('Y-m-d') : $fechaBase->copy()->addMonth()->setDay(20)->format('Y-m-d');
            }

            // 1. IGV (1011) - Si es Zona Selva (Ley 27037), no genera deuda de IGV
            $deudaIgv = $operacion->deudasTributarias()->where('tributo_codigo', '1011')->first();
            if (! $liquidacion->es_zona_selva && (float) $liquidacion->igv_resultante_pagar > 0) {
                $vencIgvFinal = $validated['vencimiento_deuda']
                    ?? ($deudaIgv?->vencimiento_deuda?->format('Y-m-d') ?: $vencimientoIgv);

                $this->registroTributo->registrar($operacion, [
                    'deuda_id' => $deudaIgv?->id,
                    'tributo_codigo' => '1011',
                    'monto_deuda_inicial' => $liquidacion->igv_resultante_pagar,
                    'vencimiento_deuda' => $vencIgvFinal,
                    'es_igv_justo' => (bool) $operacion->es_igv_justo,
                ]);
            } elseif ($deudaIgv && $deudaIgv->abonos()->doesntExist()) {
                $deudaIgv->delete();
            }

            // 2. Renta (3121, 3111, 3031)
            $rentaCodigo = $validated['renta_tributo_codigo'] ?? null;
            $rentaTributo = $rentaCodigo
                ? TipoTributoSunat::tryFrom($rentaCodigo)
                : TipoTributoSunat::rentaDelRegimen($cliente->regimen_tributario);

            if (! $rentaTributo) {
                $rentaTributo = TipoTributoSunat::RentaMype;
            }

            $deudaRenta = $operacion->deudasTributarias()->where('tributo_codigo', $rentaTributo->value)->first();
            if ((float) $liquidacion->renta_resultante_pagar > 0) {
                $vencRentaFinal = $deudaRenta?->vencimiento_deuda?->format('Y-m-d') ?: $vencimientoOficialPdt;

                $this->registroTributo->registrar($operacion, [
                    'deuda_id' => $deudaRenta?->id,
                    'tributo_codigo' => $rentaTributo->value,
                    'monto_deuda_inicial' => $liquidacion->renta_resultante_pagar,
                    'vencimiento_deuda' => $vencRentaFinal,
                ]);
            } elseif ($deudaRenta && $deudaRenta->abonos()->doesntExist()) {
                $deudaRenta->delete();
            }

            $operacion->refrescarResumenDeudas();
        }

        $anio = (int) explode('-', $validated['periodo'])[1];

        return response()->json([
            'success' => true,
            'message' => 'Liquidación y determinación tributaria guardada exitosamente.',
            'operacion_id' => $operacion->id,
            'liquidacion' => [
                'id' => $liquidacion->id,
                'es_zona_selva' => (bool) $liquidacion->es_zona_selva,
                'ventas_base' => (float) $liquidacion->ventas_base,
                'igv_ventas' => (float) $liquidacion->igv_ventas,
                'ventas_no_gravadas' => (float) $liquidacion->ventas_no_gravadas,
                'compras_base' => (float) $liquidacion->compras_base,
                'igv_compras' => (float) $liquidacion->igv_compras,
                'igv_saldo_favor_anterior' => (float) $liquidacion->igv_saldo_favor_anterior,
                'igv_retenciones' => (float) $liquidacion->igv_retenciones,
                'igv_percepciones' => (float) $liquidacion->igv_percepciones,
                'igv_saldo_favor_siguiente' => (float) $liquidacion->igv_saldo_favor_siguiente,
                'igv_resultante_pagar' => (float) $liquidacion->igv_resultante_pagar,
                'renta_base_ingresos' => (float) $liquidacion->renta_base_ingresos,
                'renta_tasa' => (float) $liquidacion->renta_tasa,
                'renta_calculada' => (float) $liquidacion->renta_calculada,
                'renta_saldo_favor_anterior' => (float) $liquidacion->renta_saldo_favor_anterior,
                'renta_compensacion_itan' => (float) $liquidacion->renta_compensacion_itan,
                'renta_retenciones' => (float) $liquidacion->renta_retenciones,
                'renta_resultante_pagar' => (float) $liquidacion->renta_resultante_pagar,
                'notas_liquidacion' => (string) ($liquidacion->notas_liquidacion ?? ''),
            ],
            'tiene_deuda' => $operacion->tiene_deuda,
            'monto_deuda_inicial' => $operacion->monto_deuda_inicial,
            'monto_deuda_inicial_formateado' => $operacion->monto_deuda_inicial_formateado,
            'saldo_pendiente' => $this->saldoDeudaMes($operacion),
            'saldo_pendiente_formateado' => 'S/ '.number_format($this->saldoDeudaMes($operacion), 2),
            'total_abonado' => $operacion->total_abonado,
            'total_abonado_formateado' => $operacion->total_abonado_formateado,
            'deudas' => $this->arregloDeudas($operacion, $vencimientoOficialPdt, $vencimientoOficialPdtDmy),
            'kpis' => $this->calcularKpis($anio),
        ]);
    }

    /**
     * Restablece o elimina la liquidación mensual de IGV y Renta (PDT 621)
     * y elimina los tributos pendientes de pago asociados que no posean abonos.
     */
    public function limpiarLiquidacion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente no existe.',
            'periodo.required' => 'El periodo contable es obligatorio.',
            'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);

        $operacion = Operacion::where('cliente_id', $cliente->id)
            ->where('periodo', $validated['periodo'])
            ->first();

        if ($operacion) {
            // Eliminar la liquidación guardada si existe
            $operacion->liquidacionPeriodo()?->delete();

            // Eliminar deudas PDT 621 (IGV 1011 y Renta: 3121, 3111, 3031, etc.) que no tengan abonos
            $codigosPdt = ['1011', '1016', '3121', '3111', '3031', '3011'];
            $deudasPdt = $operacion->deudasTributarias()
                ->whereIn('tributo_codigo', $codigosPdt)
                ->get();

            foreach ($deudasPdt as $deuda) {
                if ($deuda->abonos()->doesntExist()) {
                    $deuda->delete();
                } else {
                    // Si ya tenía abonos, su deuda inicial queda ajustada al monto abonado para mantener balance limpio
                    $totalAbonado = (float) $deuda->abonos()->sum('monto');
                    $deuda->monto_deuda_inicial = $totalAbonado;
                    $deuda->save();
                }
            }

            $operacion->refrescarResumenDeudas();
        }

        $anio = (int) explode('-', $validated['periodo'])[1];

        $cronogramaService = app(CronogramaSunatService::class);
        $fechaOficialPdt = $cronogramaService->obtenerFechaVencimiento('pdt_621', $validated['periodo'], $cliente);
        if (! $fechaOficialPdt) {
            [$mesP, $anioP] = explode('-', $validated['periodo']);
            $fechaOficialPdt = Carbon::create((int) $anioP, (int) $mesP, 1)->addMonth()->setDay(20);
        }
        $vencimientoOficialPdt = $fechaOficialPdt->format('Y-m-d');
        $vencimientoOficialPdtDmy = $fechaOficialPdt->format('d/m/Y');

        return response()->json([
            'success' => true,
            'message' => 'Hoja de liquidación y tributos pendientes restablecidos a cero correctamente.',
            'operacion_id' => $operacion?->id,
            'liquidacion' => null,
            'tiene_deuda' => (bool) ($operacion?->tiene_deuda ?? false),
            'monto_deuda_inicial' => $operacion ? $this->montoDeudaMes($operacion) : 0,
            'monto_deuda_inicial_formateado' => $operacion ? $operacion->monto_deuda_inicial_formateado : 'S/ 0.00',
            'saldo_pendiente' => $operacion ? $this->saldoDeudaMes($operacion) : 0,
            'saldo_pendiente_formateado' => 'S/ '.number_format($operacion ? $this->saldoDeudaMes($operacion) : 0, 2),
            'total_abonado' => $operacion ? $operacion->total_abonado : 0,
            'total_abonado_formateado' => $operacion ? $operacion->total_abonado_formateado : 'S/ 0.00',
            'deudas' => $operacion ? $this->arregloDeudas($operacion, $vencimientoOficialPdt, $vencimientoOficialPdtDmy) : [],
            'kpis' => $this->calcularKpis($anio),
        ]);
    }

    /**
     * Los doce periodos MM-AAAA de un ejercicio, para filtrar con whereIn y
     * usar el indice en lugar de un LIKE que no puede aprovecharlo.
     *
     * @return array<int, string>
     */
    private function periodosDelAnio(int $anio): array
    {
        return array_map(
            fn (int $mes) => sprintf('%02d-%d', $mes, $anio),
            range(1, 12),
        );
    }

    /**
     * Calcula la fecha oficial de vencimiento según la Ley de IGV Justo (prórroga de 3 meses).
     * Consulta el Cronograma Oficial de SUNAT para el PDT 621 correspondiente al periodo + 3 meses.
     */
    public function calcularIgvJusto(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
        ]);

        $cliente = Cliente::findOrFail($validated['cliente_id']);
        $periodo = $validated['periodo'];

        [$mes, $anio] = explode('-', $periodo);
        $fechaBase = Carbon::createFromDate((int) $anio, (int) $mes, 1);
        $fechaDestino = $fechaBase->copy()->addMonthsNoOverflow(3);
        $periodoDestino = $fechaDestino->format('m-Y');

        $cronogramaService = app(CronogramaSunatService::class);
        $fechaOficial = $cronogramaService->obtenerFechaVencimiento('pdt_621', $periodoDestino, $cliente);

        if (! $fechaOficial) {
            $fechaOficial = $fechaDestino->copy()->endOfMonth();
        }

        return response()->json([
            'success' => true,
            'periodo_origen' => $periodo,
            'periodo_destino' => $periodoDestino,
            'fecha_vencimiento' => $fechaOficial->format('Y-m-d'),
            'fecha_vencimiento_dmy' => $fechaOficial->format('d/m/Y'),
            'mensaje' => "Fecha oficial SUNAT calculada para {$periodoDestino} (Ley IGV Justo +3 meses).",
        ]);
    }

    /**
     * Obtiene los datos de deuda y abonos de una operación mensual vía AJAX.
     */
    public function getDeuda(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
        ]);

        $cliente = Cliente::find($validated['cliente_id']);
        $cronogramaService = app(CronogramaSunatService::class);
        $fechaOficialPdt = $cliente ? $cronogramaService->obtenerFechaVencimiento('pdt_621', $validated['periodo'], $cliente) : null;
        $oficialPdtStr = $fechaOficialPdt?->format('Y-m-d') ?? '';
        $oficialPdtDmy = $fechaOficialPdt?->format('d/m/Y') ?? '';

        $operacion = Operacion::where([
            'cliente_id' => $validated['cliente_id'],
            'periodo' => $validated['periodo'],
        ])->with(['abonosDeuda', 'deudasTributarias.abonos'])->first();

        if (! $operacion) {
            return response()->json([
                'operacion_id' => null,
                'tiene_deuda' => false,
                'tributo_codigo' => '1011',
                'tributo_nombre' => 'IGV Cuenta Propia 18%',
                'tributo_nombre_corto' => 'IGV 18%',
                'tributo_etiqueta' => '1011 - IGV Cuenta Propia 18%',
                'es_igv_justo' => false,
                'monto_deuda_inicial' => null,
                'vencimiento_deuda' => $oficialPdtStr,
                'vencimiento_deuda_dmy' => $oficialPdtDmy,
                'vencimiento_oficial_pdt' => $oficialPdtStr,
                'vencimiento_oficial_pdt_dmy' => $oficialPdtDmy,
                'abonos' => [],
                'deudas' => [],
                'total_abonado' => 0,
                'saldo_pendiente' => 0,
                'tributos_disponibles' => TipoTributoSunat::opciones(),
            ]);
        }

        return response()->json([
            'operacion_id' => $operacion->id,
            'tiene_deuda' => $operacion->tiene_deuda,
            'tributo_codigo' => $operacion->tributo_codigo ?: '1011',
            'tributo_nombre' => $operacion->tributo_nombre,
            'tributo_nombre_corto' => $operacion->tributo_nombre_corto,
            'tributo_etiqueta' => $operacion->tributo_etiqueta,
            'es_igv_justo' => (bool) $operacion->es_igv_justo,
            'monto_deuda_inicial' => $operacion->monto_deuda_inicial,
            'monto_deuda_inicial_formateado' => $operacion->monto_deuda_inicial_formateado,
            'vencimiento_deuda' => $operacion->vencimiento_deuda?->format('Y-m-d') ?: ($operacion->es_igv_justo ? '' : $oficialPdtStr),
            'vencimiento_deuda_dmy' => $operacion->vencimiento_deuda?->format('d/m/Y') ?: ($operacion->es_igv_justo ? '' : $oficialPdtDmy),
            'vencimiento_oficial_pdt' => $oficialPdtStr,
            'vencimiento_oficial_pdt_dmy' => $oficialPdtDmy,
            'abonos' => $operacion->abonosDeuda->sortByDesc('fecha_pago')->map(fn ($abono) => [
                'id' => $abono->id,
                'monto' => $abono->monto,
                'monto_formateado' => $abono->monto_formateado,
                'fecha_pago' => $abono->fecha_pago->format('d/m/Y'),
                'observacion' => $abono->observacion,
            ])->values(),
            'total_abonado' => $operacion->total_abonado,
            'total_abonado_formateado' => $operacion->total_abonado_formateado,
            'saldo_pendiente' => $this->saldoDeudaMes($operacion),
            'saldo_pendiente_formateado' => 'S/ '.number_format($this->saldoDeudaMes($operacion), 2),
            'deudas' => $this->arregloDeudas($operacion, $oficialPdtStr, $oficialPdtDmy),
            'tributos_disponibles' => TipoTributoSunat::opciones(),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function arregloDeudas(Operacion $operacion, string $vencimientoOficial = '', string $vencimientoOficialDmy = ''): array
    {
        $deudas = $operacion->relationLoaded('deudasTributarias')
            ? $operacion->deudasTributarias
            : $operacion->deudasTributarias()->with('abonos')->get();

        if ($deudas->isNotEmpty()) {
            return $deudas->map(fn (DeudaTributaria $deuda) => $this->serializarDeuda($deuda))->values()->all();
        }

        $montoInicial = (float) ($operacion->monto_deuda_inicial ?? 0);
        if ($montoInicial <= 0 && $operacion->abonosDeuda->isEmpty()) {
            return [];
        }

        $codigo = $operacion->tributo_codigo ?: '1011';

        return [[
            'id' => null,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => $codigo,
            'declaracion' => TipoTributoSunat::declaracionDe($codigo),
            'tributo_nombre' => TipoTributoSunat::obtenerNombre($codigo, 'IGV Cuenta Propia 18%'),
            'tributo_nombre_corto' => TipoTributoSunat::obtenerNombreCorto($codigo, 'IGV 18%'),
            'tributo_etiqueta' => TipoTributoSunat::tryFrom($codigo)?->etiquetaCompleta() ?? $codigo,
            'es_igv_justo' => (bool) $operacion->es_igv_justo,
            'monto_deuda_inicial' => (float) ($operacion->monto_deuda_inicial ?? 0),
            'vencimiento_deuda' => $operacion->vencimiento_deuda?->format('Y-m-d') ?: ($operacion->es_igv_justo ? '' : $vencimientoOficial),
            'vencimiento_deuda_dmy' => $operacion->vencimiento_deuda?->format('d/m/Y') ?: ($operacion->es_igv_justo ? '' : $vencimientoOficialDmy),
            'total_abonado' => (float) $operacion->total_abonado,
            'saldo_pendiente' => (float) $operacion->saldo_pendiente,
            'abonos' => $this->serializarAbonos($operacion->abonosDeuda),
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializarDeuda(DeudaTributaria $deuda): array
    {
        $deuda->loadMissing('abonos');

        return [
            'id' => $deuda->id,
            'operacion_id' => $deuda->operacion_id,
            'tributo_codigo' => $deuda->tributo_codigo,
            'declaracion' => TipoTributoSunat::declaracionDe($deuda->tributo_codigo),
            'tributo_nombre' => $deuda->tributo_nombre,
            'tributo_nombre_corto' => $deuda->tributo_nombre_corto,
            'tributo_etiqueta' => $deuda->tributo_etiqueta,
            'es_igv_justo' => (bool) $deuda->es_igv_justo,
            'monto_deuda_inicial' => (float) $deuda->monto_deuda_inicial,
            'vencimiento_deuda' => $deuda->vencimiento_deuda?->format('Y-m-d') ?? '',
            'vencimiento_deuda_dmy' => $deuda->vencimiento_deuda?->format('d/m/Y') ?? '',
            'total_abonado' => (float) $deuda->total_abonado,
            'saldo_pendiente' => (float) $deuda->saldo_pendiente,
            'abonos' => $this->serializarAbonos($deuda->abonos),
        ];
    }

    /**
     * @param  iterable<int, AbonoDeuda>  $abonos
     * @return array<int, array<string, mixed>>
     */
    private function serializarAbonos(iterable $abonos): array
    {
        return collect($abonos)->sortByDesc(fn ($abono) => $abono->fecha_pago?->timestamp ?? 0)->map(fn ($abono) => [
            'id' => $abono->id,
            'deuda_tributaria_id' => $abono->deuda_tributaria_id,
            'monto' => (float) $abono->monto,
            'monto_formateado' => $abono->monto_formateado,
            'fecha_pago' => $abono->fecha_pago?->format('d/m/Y') ?? '',
            'observacion' => $abono->observacion ?? '',
            'medio_pago' => $abono->medio_pago?->value,
            'medio_etiqueta' => $abono->medio_pago?->etiqueta() ?? '',
        ])->values()->all();
    }

    private function montoDeudaMes(Operacion $operacion): float
    {
        $deudas = $operacion->relationLoaded('deudasTributarias') ? $operacion->deudasTributarias : collect();
        if ($deudas->isNotEmpty()) {
            return round((float) $deudas->sum(fn (DeudaTributaria $deuda) => (float) $deuda->monto_deuda_inicial), 2);
        }

        return (float) ($operacion->monto_deuda_inicial ?? 0);
    }

    private function abonadoDeudaMes(Operacion $operacion): float
    {
        $deudas = $operacion->relationLoaded('deudasTributarias') ? $operacion->deudasTributarias : collect();
        if ($deudas->isNotEmpty()) {
            return round((float) $deudas->sum(fn (DeudaTributaria $deuda) => (float) $deuda->total_abonado), 2);
        }

        return (float) $operacion->total_abonado;
    }

    private function saldoDeudaMes(Operacion $operacion): float
    {
        return max(0, round($this->montoDeudaMes($operacion) - $this->abonadoDeudaMes($operacion), 2));
    }
}
