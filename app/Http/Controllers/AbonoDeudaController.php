<?php

namespace App\Http\Controllers;

use App\Models\AbonoDeuda;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbonoDeudaController extends Controller
{
    /**
     * Registra un nuevo abono a una deuda tributaria.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('monto') && is_string($request->input('monto'))) {
            $request->merge([
                'monto' => (float) str_replace(',', '', $request->input('monto')),
            ]);
        }

        $validated = $request->validate([
            'operacion_id' => 'required|exists:operaciones,id',
            'deuda_tributaria_id' => 'nullable|integer|exists:deuda_tributarias,id',
            'monto' => 'required|numeric|min:0.01|max:99999999.99',
            'fecha_pago' => 'required|date',
            'observacion' => 'nullable|string|max:500',
        ]);

        $operacion = Operacion::findOrFail($validated['operacion_id']);
        $deuda = null;
        if (! empty($validated['deuda_tributaria_id'])) {
            $deuda = DeudaTributaria::where('operacion_id', $operacion->id)
                ->whereKey($validated['deuda_tributaria_id'])
                ->firstOrFail();
        }

        $validated['estudio_id'] = $operacion->estudio_id;
        $abono = AbonoDeuda::create($validated);

        $totales = $this->totalesDelAbono($operacion->id, $deuda?->id);

        return response()->json([
            'success' => true,
            'abono' => [
                'id' => $abono->id,
                'deuda_tributaria_id' => $abono->deuda_tributaria_id,
                'monto' => $abono->monto,
                'monto_formateado' => $abono->monto_formateado,
                'fecha_pago' => $abono->fecha_pago->format('d/m/Y'),
                'observacion' => $abono->observacion,
            ],
            'deuda_tributaria_id' => $deuda?->id,
            'total_abonado' => $totales['total_abonado'],
            'total_abonado_formateado' => $totales['total_abonado_formateado'],
            'saldo_pendiente' => $totales['saldo_pendiente'],
            'saldo_pendiente_formateado' => $totales['saldo_pendiente_formateado'],
            'total_abonado_mes' => $totales['total_abonado_mes'],
            'saldo_pendiente_mes' => $totales['saldo_pendiente_mes'],
        ]);
    }

    /**
     * Elimina un abono existente.
     */
    public function destroy(AbonoDeuda $abonoDeuda): JsonResponse
    {
        $operacionId = $abonoDeuda->operacion_id;
        $deudaId = $abonoDeuda->deuda_tributaria_id;
        $abonoDeuda->delete();

        $totales = $this->totalesDelAbono($operacionId, $deudaId);

        return response()->json([
            'success' => true,
            'deuda_tributaria_id' => $deudaId,
            'total_abonado' => $totales['total_abonado'],
            'total_abonado_formateado' => $totales['total_abonado_formateado'],
            'saldo_pendiente' => $totales['saldo_pendiente'],
            'saldo_pendiente_formateado' => $totales['saldo_pendiente_formateado'],
            'total_abonado_mes' => $totales['total_abonado_mes'],
            'saldo_pendiente_mes' => $totales['saldo_pendiente_mes'],
        ]);
    }

    /**
     * @return array{total_abonado: float, total_abonado_formateado: string, saldo_pendiente: float, saldo_pendiente_formateado: string, total_abonado_mes: float, saldo_pendiente_mes: float}
     */
    private function totalesDelAbono(int $operacionId, ?int $deudaId): array
    {
        $operacion = Operacion::with('abonosDeuda')->findOrFail($operacionId);
        $deuda = $deudaId ? DeudaTributaria::with('abonos')->find($deudaId) : null;
        $total = $deuda ? (float) $deuda->total_abonado : (float) $operacion->total_abonado;
        $saldo = $deuda ? (float) $deuda->saldo_pendiente : (float) $operacion->saldo_pendiente;

        return [
            'total_abonado' => $total,
            'total_abonado_formateado' => 'S/ '.number_format($total, 2),
            'saldo_pendiente' => $saldo,
            'saldo_pendiente_formateado' => 'S/ '.number_format($saldo, 2),
            'total_abonado_mes' => (float) $operacion->total_abonado,
            'saldo_pendiente_mes' => (float) $operacion->saldo_pendiente,
        ];
    }
}
