<?php

namespace App\Http\Controllers;

use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Models\DeudaTributaria;
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
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $this->periodosDelAnio($anio))->with(['abonosDeuda', 'deudasTributarias.abonos'])])
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
                ->get(['id', 'numero', 'razon_social', 'ruc'])
                ->map(fn (Cliente $cliente): array => [
                    'id' => $cliente->id,
                    'numero' => $cliente->numero,
                    'razon_social' => $cliente->razon_social,
                    'ruc' => $cliente->ruc,
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

        if (! $operacion->tiene_deuda && ! $operacion->monto_deuda_inicial && $operacion->abonosDeuda->isEmpty()) {
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
