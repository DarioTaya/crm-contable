<?php

namespace App\Http\Controllers;

use App\Models\Operacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlDeudaController extends Controller
{
    /**
     * Dashboard de Control de Deudas pendientes con SUNAT.
     */
    public function index(Request $request): View|JsonResponse
    {
        $clientesParam = $request->input('clientes');
        $clienteIds = null;
        if (! empty($clientesParam)) {
            $clienteIds = is_array($clientesParam) ? $clientesParam : explode(',', (string) $clientesParam);
            $clienteIds = array_map('intval', array_filter($clienteIds));
        }

        $query = Operacion::where('tiene_deuda', true)
            ->with(['cliente', 'abonosDeuda'])
            ->orderBy('vencimiento_deuda');

        if (! empty($clienteIds)) {
            $query->whereIn('cliente_id', $clienteIds);
        }

        $deudas = $query->get()
            ->filter(fn (Operacion $op) => $op->saldo_pendiente > 0)
            ->values();

        $todasDeudas = Operacion::where('tiene_deuda', true)
            ->with('abonosDeuda')
            ->when(! empty($clienteIds), fn ($q) => $q->whereIn('cliente_id', $clienteIds))
            ->get();

        $totalDeudaHistorica = (float) $todasDeudas->sum('monto_deuda_inicial');
        $totalRecaudado = (float) $todasDeudas->sum(fn (Operacion $op) => $op->total_abonado);
        $totalSaldoPorCobrar = (float) $todasDeudas->sum(fn (Operacion $op) => $op->saldo_pendiente);

        // Lista de clientes únicos con deudas activas para el selector multi-cliente
        $todasLasDeudasSinFiltro = Operacion::where('tiene_deuda', true)
            ->with('cliente')
            ->get()
            ->filter(fn (Operacion $op) => $op->saldo_pendiente > 0);

        $clientesConDeuda = $todasLasDeudasSinFiltro->pluck('cliente')
            ->filter()
            ->unique('id')
            ->map(function ($cli) use ($todasLasDeudasSinFiltro) {
                return [
                    'id' => $cli->id,
                    'numero' => $cli->numero ?? $cli->id,
                    'numero_formateado' => (string) ($cli->numero ?? $cli->id),
                    'razon_social' => $cli->razon_social,
                    'ruc' => $cli->ruc,
                    'total_deudas' => $todasLasDeudasSinFiltro->where('cliente_id', $cli->id)->count(),
                ];
            })
            ->sortBy('razon_social')
            ->values();

        // Mapeo detallado para reactividad en Alpine.js
        $deudasData = $deudas->map(function (Operacion $op) {
            $numCliente = $op->cliente ? ($op->cliente->numero ?? $op->cliente->id) : 0;
            $vence = $op->vencimiento_deuda;
            $urgencia = 'normal';
            if ($vence) {
                if ($vence->isPast()) {
                    $urgencia = 'vencido';
                } elseif ($vence->diffInDays(now()) <= 7) {
                    $urgencia = 'por_vencer';
                }
            }

            return [
                'id' => $op->id,
                'cliente_id' => $op->cliente_id,
                'cliente_codigo' => (string) $numCliente,
                'cliente_razon' => $op->cliente->razon_social ?? 'Empresa sin nombre',
                'cliente_ruc' => $op->cliente->ruc ?? '',
                'periodo' => $op->periodo,
                'tributo_codigo' => $op->tributo_codigo ?? '1011',
                'tributo_nombre' => $op->tributo_nombre,
                'tributo_nombre_corto' => $op->tributo_nombre_corto,
                'tributo_etiqueta' => $op->tributo_etiqueta,
                'es_igv_justo' => (bool) $op->es_igv_justo,
                'vencimiento' => $vence?->format('Y-m-d'),
                'vencimiento_formateado' => $vence?->format('d/m/Y') ?? '—',
                'vencimiento_urgencia' => $urgencia,
                'monto_deuda_inicial' => (float) ($op->monto_deuda_inicial ?? 0),
                'monto_deuda_inicial_formateado' => $op->monto_deuda_inicial_formateado,
                'total_abonado' => (float) $op->total_abonado,
                'total_abonado_formateado' => $op->total_abonado_formateado,
                'saldo_pendiente' => (float) $op->saldo_pendiente,
                'saldo_pendiente_formateado' => $op->saldo_pendiente_formateado,
                'porcentaje_pagado' => $op->monto_deuda_inicial > 0
                    ? min(100, round(($op->total_abonado / $op->monto_deuda_inicial) * 100))
                    : 0,
                'abonos' => $op->abonosDeuda->sortByDesc('fecha_pago')->map(fn ($abono) => [
                    'id' => $abono->id,
                    'monto' => (float) $abono->monto,
                    'monto_formateado' => $abono->monto_formateado,
                    'fecha_pago' => $abono->fecha_pago?->format('d/m/Y') ?? '',
                    'observacion' => $abono->observacion ?? '',
                ])->values()->all(),
            ];
        })->values();

        // Si es petición AJAX / JSON, devolver JSON con deudas y KPIs recalculados
        if ($request->ajax() || $request->wantsJson() || $request->has('ajax')) {
            return response()->json([
                'success' => true,
                'deudas' => $deudasData,
                'total_deuda' => $totalDeudaHistorica,
                'total_recaudado' => $totalRecaudado,
                'total_saldo' => $totalSaldoPorCobrar,
                'count' => $deudas->count(),
            ]);
        }

        return view('deudas.index', compact(
            'deudas',
            'clientesConDeuda',
            'deudasData',
            'totalDeudaHistorica',
            'totalRecaudado',
            'totalSaldoPorCobrar',
        ));
    }
}
