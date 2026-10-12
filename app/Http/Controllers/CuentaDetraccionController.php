<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\MovimientoDetraccion;
use App\Models\SaldoDetraccion;
use App\Services\Detracciones\ExtractoDetraccionesService;
use App\Services\Detracciones\LectorConstanciaDetraccionPdf;
use App\Services\Impuestos\ReporteCreditosFiscalesCliente;
use App\Services\Impuestos\ReporteImpuestosCliente;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CuentaDetraccionController extends Controller
{
    public function __construct(
        private ExtractoDetraccionesService $extractoService,
        private LectorConstanciaDetraccionPdf $lectorPdf,
    ) {}

    /**
     * Muestra la vista del extracto de cuenta de detracciones en el expediente del cliente.
     */
    public function show(Cliente $cliente, Request $request): View
    {
        return app(ClienteController::class)->show(
            $request,
            $cliente,
            app(ReporteImpuestosCliente::class),
            app(ReporteCreditosFiscalesCliente::class),
            $this->extractoService,
        );
    }

    private const NOMBRES_MESES = [
        1 => 'Enero',
        2 => 'Febrero',
        3 => 'Marzo',
        4 => 'Abril',
        5 => 'Mayo',
        6 => 'Junio',
        7 => 'Julio',
        8 => 'Agosto',
        9 => 'Setiembre',
        10 => 'Octubre',
        11 => 'Noviembre',
        12 => 'Diciembre',
    ];

    /**
     * Lee y procesa al vuelo uno o varios PDFs de constancias de detracción SPOT sin almacenar ningún archivo en disco.
     */
    public function cargarPdf(Cliente $cliente, Request $request): JsonResponse
    {
        $request->validate([
            'archivos' => 'required|array|min:1',
            'archivos.*' => 'required|file|mimes:pdf|max:10240',
            'anio_contexto' => 'nullable|integer|min:2000|max:2100',
            'mes_contexto' => 'nullable|integer|between:1,12',
        ], [
            'archivos.required' => 'Debe adjuntar al menos un archivo PDF de constancia oficial.',
            'archivos.*.mimes' => 'El archivo debe ser un documento PDF válido.',
            'archivos.*.max' => 'El archivo no debe superar los 10 MB.',
        ]);

        $resultados = [];
        $archivos = $request->file('archivos');
        $anioContexto = $request->filled('anio_contexto') ? (int) $request->input('anio_contexto') : null;
        $mesContexto = $request->filled('mes_contexto') ? (int) $request->input('mes_contexto') : null;

        foreach ($archivos as $archivo) {
            try {
                $info = $this->lectorPdf->procesar($archivo, $cliente);

                // Análisis inteligente de periodo contable
                $fechaPagoStr = $info['datos']['fecha_pago'] ?? null;
                $fechaPago = $fechaPagoStr ? Carbon::parse($fechaPagoStr) : null;
                $anioConstancia = $fechaPago ? (int) $fechaPago->year : null;
                $mesConstancia = $fechaPago ? (int) $fechaPago->month : null;
                $nombreMesConstancia = $mesConstancia ? (self::NOMBRES_MESES[$mesConstancia] ?? '') : null;
                $periodoTextoConstancia = ($nombreMesConstancia && $anioConstancia) ? "{$nombreMesConstancia} {$anioConstancia}" : null;

                $coincidePeriodo = true;
                $motivoIncompatibilidad = null;
                $urlIrPeriodo = null;

                if ($fechaPago && $anioContexto) {
                    if ($mesContexto !== null) {
                        // Vista mensual activa: debe coincidir tanto año como mes
                        if ($anioConstancia !== $anioContexto || $mesConstancia !== $mesContexto) {
                            $coincidePeriodo = false;
                            $nombreMesContexto = self::NOMBRES_MESES[$mesContexto] ?? "Mes {$mesContexto}";
                            $motivoIncompatibilidad = "Esta constancia corresponde a {$periodoTextoConstancia}, pero actualmente estás en la vista de {$nombreMesContexto} {$anioContexto}.";
                            $urlIrPeriodo = route('clientes.detracciones', [
                                'cliente' => $cliente,
                                'anio' => $anioConstancia,
                                'detraccion_mes' => $mesConstancia,
                            ]);
                        }
                    } else {
                        // Vista anual activa: debe pertenecer al ejercicio fiscal seleccionado
                        if ($anioConstancia !== $anioContexto) {
                            $coincidePeriodo = false;
                            $motivoIncompatibilidad = "Esta constancia corresponde al ejercicio fiscal {$anioConstancia}, pero estás posicionado en el año {$anioContexto}.";
                            $urlIrPeriodo = route('clientes.detracciones', [
                                'cliente' => $cliente,
                                'anio' => $anioConstancia,
                            ]);
                        }
                    }
                }

                $esFechaFutura = $fechaPago ? $fechaPago->isFuture() : false;

                $info['periodo_info'] = [
                    'anio' => $anioConstancia,
                    'mes' => $mesConstancia,
                    'mes_nombre' => $nombreMesConstancia,
                    'periodo_texto' => $periodoTextoConstancia,
                    'coincide_periodo' => $coincidePeriodo,
                    'motivo_incompatibilidad' => $motivoIncompatibilidad,
                    'url_ir_periodo' => $urlIrPeriodo,
                    'es_fecha_futura' => $esFechaFutura,
                ];

                $resultados[] = [
                    'exito' => true,
                    'nombre_archivo' => $archivo->getClientOriginalName(),
                    'resultado' => $info,
                ];
            } catch (\Throwable $e) {
                $resultados[] = [
                    'exito' => false,
                    'nombre_archivo' => $archivo->getClientOriginalName(),
                    'error' => $e->getMessage(),
                ];
            }
        }

        $exitosos = array_filter($resultados, fn ($r) => $r['exito']);
        if (empty($exitosos)) {
            $primerError = $resultados[0]['error'] ?? 'No se pudo leer la constancia de detracción.';

            return response()->json([
                'success' => false,
                'message' => $primerError,
                'resultados' => $resultados,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Constancias leídas y analizadas correctamente.',
            'total_procesados' => count($resultados),
            'total_exitosos' => count($exitosos),
            'resultados' => $resultados,
        ]);
    }

    /**
     * Registra un movimiento (depósito oficial o manual, o retiro bancario) en la cuenta de detracciones.
     */
    public function guardarMovimiento(Cliente $cliente, Request $request): JsonResponse
    {
        // Limpieza y compatibilidad de aliases (monto con comas, glosa -> descripcion, cargo/abono)
        if ($request->has('monto') && is_string($request->input('monto'))) {
            $request->merge(['monto' => str_replace([',', ' '], '', $request->input('monto'))]);
        }
        if (! $request->filled('descripcion') && $request->filled('glosa')) {
            $request->merge(['descripcion' => $request->input('glosa')]);
        }
        if ($request->input('tipo') === 'abono') {
            $request->merge(['tipo' => 'deposito']);
        } elseif ($request->input('tipo') === 'cargo') {
            $request->merge(['tipo' => 'retiro']);
        }

        $validated = $request->validate([
            'tipo' => 'required|string|in:deposito,retiro',
            'fecha' => 'required|date|before_or_equal:today',
            'monto' => 'required|numeric|min:0.01|max:999999999.99',
            'descripcion' => 'required|string|max:255',
            'numero_operacion' => 'nullable|string|max:50',
            'numero_constancia' => 'nullable|string|max:50',
            'comprobante_referencia' => 'nullable|string|max:50',
            'adquiriente_ruc' => 'nullable|string|size:11',
            'adquiriente_razon_social' => 'nullable|string|max:255',
            'proveedor_ruc' => 'nullable|string|size:11',
            'tipo_bien' => 'nullable|string|max:150',
            'origen' => 'nullable|string|in:constancia_pdf,manual',
            'actualizar_cuenta_cliente' => 'nullable|boolean',
            'cuenta_detracciones' => 'nullable|string|max:50',
            'anio_contexto' => 'nullable|integer|min:2000|max:2100',
            'mes_contexto' => 'nullable|integer|between:1,12',
            'ignorar_periodo' => 'nullable|boolean',
            'ignorar_duplicado' => 'nullable|boolean',
        ], [
            'fecha.before_or_equal' => 'La fecha de la operación no puede ser posterior a la fecha actual.',
        ]);

        $fechaObj = Carbon::parse($validated['fecha']);
        $anioMov = (int) $fechaObj->year;
        $mesMov = (int) $fechaObj->month;
        $periodo = $fechaObj->format('m-Y');

        $anioContexto = $request->filled('anio_contexto') ? (int) $request->input('anio_contexto') : null;
        $mesContexto = $request->filled('mes_contexto') ? (int) $request->input('mes_contexto') : null;

        // 1. Validación inteligente de periodo fiscal
        if ($anioContexto && empty($request->input('ignorar_periodo'))) {
            $nombreMesMov = self::NOMBRES_MESES[$mesMov] ?? "Mes {$mesMov}";

            if ($mesContexto !== null) {
                if ($anioMov !== $anioContexto || $mesMov !== $mesContexto) {
                    $nombreMesContexto = self::NOMBRES_MESES[$mesContexto] ?? "Mes {$mesContexto}";
                    $urlDestino = route('clientes.detracciones', [
                        'cliente' => $cliente,
                        'anio' => $anioMov,
                        'detraccion_mes' => $mesMov,
                    ]);

                    return response()->json([
                        'success' => false,
                        'periodo_mismatch' => true,
                        'message' => "La fecha de la operación ({$fechaObj->format('d/m/Y')}) pertenece al periodo {$nombreMesMov} {$anioMov}, pero estás ubicado en {$nombreMesContexto} {$anioContexto}. Para mantener la integridad contable, debes registrarla en {$nombreMesMov} {$anioMov}.",
                        'url_ir_periodo' => $urlDestino,
                        'periodo_detectado' => "{$nombreMesMov} {$anioMov}",
                    ], 422);
                }
            } else {
                if ($anioMov !== $anioContexto) {
                    $urlDestino = route('clientes.detracciones', [
                        'cliente' => $cliente,
                        'anio' => $anioMov,
                    ]);

                    return response()->json([
                        'success' => false,
                        'periodo_mismatch' => true,
                        'message' => "La fecha de la operación ({$fechaObj->format('d/m/Y')}) pertenece al año fiscal {$anioMov}, pero estás en el ejercicio {$anioContexto}.",
                        'url_ir_periodo' => $urlDestino,
                        'periodo_detectado' => "Año {$anioMov}",
                    ], 422);
                }
            }
        }

        // 2. Validación de titular / RUC (para evitar registrar constancias de terceros)
        if (! empty($validated['proveedor_ruc']) && trim($validated['proveedor_ruc']) !== $cliente->ruc) {
            return response()->json([
                'success' => false,
                'ruc_mismatch' => true,
                'message' => "El RUC de la constancia ({$validated['proveedor_ruc']}) no coincide con el RUC de este cliente ({$cliente->ruc} - {$cliente->razon_social}).",
            ], 422);
        }

        // 3. Validación de constancia duplicada
        if ($validated['tipo'] === 'deposito' && ! empty($validated['numero_constancia']) && empty($request->input('ignorar_duplicado'))) {
            $existeDuplicado = MovimientoDetraccion::where('cliente_id', $cliente->id)
                ->where('tipo', 'deposito')
                ->where('numero_constancia', trim($validated['numero_constancia']))
                ->exists();

            if ($existeDuplicado) {
                return response()->json([
                    'success' => false,
                    'duplicado' => true,
                    'message' => "La constancia de detracción N° {$validated['numero_constancia']} ya se encuentra registrada en el extracto de este cliente.",
                ], 422);
            }
        }

        // Si el usuario marcó actualizar la cuenta del cliente y viene una cuenta
        if (! empty($validated['actualizar_cuenta_cliente']) && ! empty($validated['cuenta_detracciones'])) {
            $cliente->credencial()->updateOrCreate(
                ['cliente_id' => $cliente->id],
                ['cuenta_detracciones' => trim($validated['cuenta_detracciones'])]
            );
        }

        $movimiento = MovimientoDetraccion::create([
            'estudio_id' => $cliente->estudio_id,
            'cliente_id' => $cliente->id,
            'tipo' => $validated['tipo'],
            'fecha' => $fechaObj->toDateString(),
            'periodo' => $periodo,
            'monto' => $validated['monto'],
            'descripcion' => trim($validated['descripcion']),
            'numero_operacion' => ! empty($validated['numero_operacion']) ? trim($validated['numero_operacion']) : null,
            'numero_constancia' => ! empty($validated['numero_constancia']) ? trim($validated['numero_constancia']) : null,
            'comprobante_referencia' => ! empty($validated['comprobante_referencia']) ? trim($validated['comprobante_referencia']) : null,
            'adquiriente_ruc' => ! empty($validated['adquiriente_ruc']) ? trim($validated['adquiriente_ruc']) : null,
            'adquiriente_razon_social' => ! empty($validated['adquiriente_razon_social']) ? trim($validated['adquiriente_razon_social']) : null,
            'tipo_bien' => ! empty($validated['tipo_bien']) ? trim($validated['tipo_bien']) : null,
            'origen' => $validated['origen'] ?? 'manual',
            'metadatos' => [
                'usuario_registrador' => auth()->user()?->name,
                'registrado_at' => now()->toDateTimeString(),
            ],
        ]);

        $anio = (int) $fechaObj->year;
        $extractoActualizado = $this->extractoService->obtenerExtracto($cliente, $anio);

        return response()->json([
            'success' => true,
            'message' => ($validated['tipo'] === 'deposito' ? 'Depósito registrado' : 'Retiro registrado').' exitosamente en la cuenta de detracciones.',
            'movimiento' => $movimiento,
            'resumen' => [
                'saldo_final' => $extractoActualizado['saldo_final'],
                'total_depositos' => $extractoActualizado['total_depositos'],
                'total_retiros' => $extractoActualizado['total_retiros'],
            ],
        ]);
    }

    /**
     * Elimina un movimiento registrado (manual o procedente de constancia PDF).
     */
    public function eliminarMovimiento(Cliente $cliente, MovimientoDetraccion $movimiento): JsonResponse
    {
        if ($movimiento->cliente_id !== $cliente->id) {
            abort(403, 'El movimiento no pertenece al cliente.');
        }

        if ($movimiento->origen === 'control_impuestos' && $movimiento->abono_deuda_id) {
            return response()->json([
                'success' => false,
                'message' => 'Este retiro proviene directamente del módulo de Control de Impuestos. Para anularlo, elimine el abono en la pestaña de Impuestos.',
            ], 422);
        }

        $anio = (int) $movimiento->fecha->year;
        $movimiento->delete();

        $extractoActualizado = $this->extractoService->obtenerExtracto($cliente, $anio);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento eliminado correctamente de la cuenta de detracciones.',
            'resumen' => [
                'saldo_final' => $extractoActualizado['saldo_final'],
                'total_depositos' => $extractoActualizado['total_depositos'],
                'total_retiros' => $extractoActualizado['total_retiros'],
            ],
        ]);
    }

    /**
     * Configura o actualiza el saldo inicial de apertura para un ejercicio fiscal dado.
     */
    public function guardarSaldoInicial(Cliente $cliente, Request $request): JsonResponse
    {
        if ($request->has('saldo_inicial') && is_string($request->input('saldo_inicial'))) {
            $request->merge(['saldo_inicial' => str_replace([',', ' '], '', $request->input('saldo_inicial'))]);
        }

        $validated = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'saldo_inicial' => 'required|numeric|min:0|max:999999999.99',
            'observacion' => 'nullable|string|max:255',
        ]);

        SaldoDetraccion::withoutGlobalScopes()->updateOrCreate(
            [
                'cliente_id' => $cliente->id,
                'anio' => $validated['anio'],
            ],
            [
                'estudio_id' => $cliente->estudio_id,
                'saldo_inicial' => $validated['saldo_inicial'],
                'observacion' => $validated['observacion'] ?? 'Saldo de apertura configurado por el estudio contable',
            ]
        );

        $extracto = $this->extractoService->obtenerExtracto($cliente, (int) $validated['anio']);

        return response()->json([
            'success' => true,
            'message' => "Saldo inicial para el ejercicio {$validated['anio']} actualizado a S/ ".number_format((float) $validated['saldo_inicial'], 2),
            'extracto' => $extracto,
            'redirect_url' => route('clientes.detracciones', ['cliente' => $cliente, 'anio' => $validated['anio']]),
        ]);
    }

    /**
     * Devuelve el saldo sugerido / arrastrado del año anterior para aperturar un ejercicio fiscal.
     */
    public function saldoSugerido(Cliente $cliente, Request $request): JsonResponse
    {
        $anio = (int) $request->query('anio', now()->year);
        $saldoSugerido = $this->extractoService->obtenerSaldoInicialAnio($cliente, $anio);

        return response()->json([
            'success' => true,
            'anio' => $anio,
            'anio_anterior' => $anio - 1,
            'saldo_sugerido' => $saldoSugerido,
            'saldo_sugerido_formateado' => number_format($saldoSugerido, 2),
        ]);
    }

    /**
     * Vista de impresión ejecutiva del extracto bancario oficial Banco de la Nación.
     */
    public function imprimir(Cliente $cliente, Request $request): View
    {
        $anio = (int) $request->query('anio', now()->year);
        $mes = $request->filled('mes') ? (int) $request->query('mes') : null;

        $extracto = $this->extractoService->obtenerExtracto($cliente, $anio, $mes);
        $estudio = $cliente->estudio;

        return view('clientes.cuenta-detracciones-imprimir', compact('cliente', 'extracto', 'estudio', 'anio', 'mes'));
    }

    /**
     * Exporta el extracto bancario a archivo CSV / Excel con delimitación contable y cabeceras oficiales.
     */
    public function exportar(Cliente $cliente, Request $request): StreamedResponse
    {
        $anio = (int) $request->query('anio', now()->year);
        $mes = $request->filled('mes') ? (int) $request->query('mes') : null;

        $extracto = $this->extractoService->obtenerExtracto($cliente, $anio, $mes);
        $movimientos = $extracto['movimientos'];

        $nombreMes = $extracto['mes_nombre'] ? "__{$extracto['mes_nombre']}" : '';
        $nombreArchivo = "Extracto_Detracciones_BN_{$cliente->ruc}_{$anio}{$nombreMes}.csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($cliente, $extracto, $movimientos) {
            $handle = fopen('php://output', 'w');
            // Agregar BOM UTF-8 para que Excel abra sin problemas de tildes
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['EXTRACTO BANCARIO - CUENTA DE DETRACCIONES (BANCO DE LA NACIÓN)']);
            fputcsv($handle, ['CLIENTE / TITULAR:', $cliente->razon_social]);
            fputcsv($handle, ['RUC:', $cliente->ruc]);
            fputcsv($handle, ['N° CUENTA DETRACCIONES BN:', $cliente->credencial?->cuenta_detracciones ?? 'No registrada']);
            fputcsv($handle, ['EJERCICIO:', $extracto['anio'], 'PERIODO:', $extracto['mes_nombre'] ?? 'Todo el año']);
            fputcsv($handle, ['SALDO INICIAL:', number_format($extracto['saldo_inicial'], 2, '.', '')]);
            fputcsv($handle, ['TOTAL DEPÓSITOS (+):', number_format($extracto['total_depositos'], 2, '.', '')]);
            fputcsv($handle, ['TOTAL RETIROS (-):', number_format($extracto['total_retiros'], 2, '.', '')]);
            fputcsv($handle, ['SALDO FINAL DISPONIBLE:', number_format($extracto['saldo_final'], 2, '.', '')]);
            fputcsv($handle, []);

            fputcsv($handle, [
                'N°',
                'FECHA',
                'TIPO',
                'N° OPERACIÓN BN',
                'N° CONSTANCIA SPOT',
                'COMPROBANTE',
                'DESCRIPCIÓN / DETALLE',
                'RUC ADQUIRIENTE',
                'RAZÓN SOCIAL ADQUIRIENTE',
                'ORIGEN',
                'DEPÓSITO (S/)',
                'RETIRO (S/)',
                'SALDO RESULTANTE (S/)',
            ]);

            $idx = 1;
            foreach ($movimientos as $mov) {
                fputcsv($handle, [
                    $idx++,
                    $mov->fecha->format('d/m/Y'),
                    strtoupper($mov->tipo),
                    $mov->numero_operacion ?? '—',
                    $mov->numero_constancia ?? '—',
                    $mov->comprobante_referencia ?? '—',
                    $mov->descripcion,
                    $mov->adquiriente_ruc ?? '—',
                    $mov->adquiriente_razon_social ?? '—',
                    strtoupper($mov->origen),
                    $mov->isDeposito() ? number_format((float) $mov->monto, 2, '.', '') : '0.00',
                    $mov->isRetiro() ? number_format((float) $mov->monto, 2, '.', '') : '0.00',
                    number_format((float) ($mov->saldo_acumulado ?? 0), 2, '.', ''),
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }
}
