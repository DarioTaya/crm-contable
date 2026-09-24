<?php

namespace App\Http\Controllers;

use App\Models\AbonoDeuda;
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
        $validated = $request->validate([
            'operacion_id' => 'required|exists:operaciones,id',
            'monto' => 'required|numeric|min:0.01|max:99999999.99',
            'fecha_pago' => 'required|date',
            'observacion' => 'nullable|string|max:500',
        ]);

        $operacion = Operacion::findOrFail($validated['operacion_id']);
        $validated['estudio_id'] = $operacion->estudio_id;

        $abono = AbonoDeuda::create($validated);

        $operacion = Operacion::with('abonosDeuda')->find($validated['operacion_id']);

        return response()->json([
            'success' => true,
            'abono' => [
                'id' => $abono->id,
                'monto' => $abono->monto,
                'fecha_pago' => $abono->fecha_pago->format('d/m/Y'),
                'observacion' => $abono->observacion,
            ],
            'total_abonado' => $operacion->total_abonado,
            'saldo_pendiente' => $operacion->saldo_pendiente,
        ]);
    }

    /**
     * Elimina un abono existente.
     */
    public function destroy(AbonoDeuda $abonoDeuda): JsonResponse
    {
        $operacionId = $abonoDeuda->operacion_id;
        $abonoDeuda->delete();

        $operacion = Operacion::with('abonosDeuda')->find($operacionId);

        return response()->json([
            'success' => true,
            'total_abonado' => $operacion->total_abonado,
            'saldo_pendiente' => $operacion->saldo_pendiente,
        ]);
    }
}
