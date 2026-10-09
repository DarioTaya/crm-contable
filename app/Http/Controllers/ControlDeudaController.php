<?php

namespace App\Http\Controllers;

use App\Models\DeudaTributaria;
use App\Models\Operacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $todasDeudas = $this->filasDeuda($clienteIds);
        $deudas = $todasDeudas->filter(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente > 0)->values();

        $totalDeudaHistorica = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->monto_deuda_inicial);
        $totalRecaudado = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->total_abonado);
        $totalSaldoPorCobrar = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente);

        $todasLasDeudasSinFiltro = empty($clienteIds)
            ? $deudas
            : $this->filasDeuda(null)->filter(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente > 0);

        $clientesConDeuda = $todasLasDeudasSinFiltro->map(fn (DeudaTributaria|Operacion $fila) => $fila->cliente)
            ->filter()
            ->unique('id')
            ->map(function ($cli) use ($todasLasDeudasSinFiltro) {
                return [
                    'id' => $cli->id,
                    'numero' => $cli->numero ?? $cli->id,
                    'numero_formateado' => (string) ($cli->numero ?? $cli->id),
                    'razon_social' => $cli->razon_social,
                    'ruc' => $cli->ruc,
                    'total_deudas' => $todasLasDeudasSinFiltro->filter(function (DeudaTributaria|Operacion $fila) use ($cli) {
                        $clienteId = $fila instanceof Operacion ? $fila->cliente_id : $fila->operacion?->cliente_id;

                        return (int) $clienteId === (int) $cli->id;
                    })->count(),
                ];
            })
            ->sortBy('razon_social')
            ->values();

        // Mapeo detallado para reactividad en Alpine.js
        $deudasData = $deudas->map(function (DeudaTributaria|Operacion $op) {
            $numCliente = $op->cliente ? ($op->cliente->numero ?? $op->cliente->id) : 0;
            $clienteId = $op instanceof Operacion ? $op->cliente_id : $op->operacion?->cliente_id;
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
                'deuda_id' => $op instanceof DeudaTributaria ? $op->id : null,
                'operacion_id' => $op instanceof Operacion ? $op->id : $op->operacion_id,
                'cliente_id' => $clienteId,
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

    /**
     * Cada tributo es una fila. Las deudas antiguas sin detalle siguen saliendo de la operación.
     *
     * @param  array<int, int>|null  $clienteIds
     * @return Collection<int, DeudaTributaria|Operacion>
     */
    private function filasDeuda(?array $clienteIds): Collection
    {
        $hijas = DeudaTributaria::query()
            ->with(['operacion.cliente', 'abonos'])
            ->whereHas('operacion', function ($query) use ($clienteIds) {
                $query->where('tiene_deuda', true);
                if (! empty($clienteIds)) {
                    $query->whereIn('cliente_id', $clienteIds);
                }
            })
            ->get();

        $legacy = Operacion::query()
            ->where('tiene_deuda', true)
            ->whereDoesntHave('deudasTributarias')
            ->with(['cliente', 'abonosDeuda'])
            ->when(! empty($clienteIds), fn ($query) => $query->whereIn('cliente_id', $clienteIds))
            ->get();

        return $hijas->concat($legacy)->sortBy(function (DeudaTributaria|Operacion $fila) {
            return $fila->vencimiento_deuda?->timestamp ?? PHP_INT_MAX;
        })->values();
    }
}
