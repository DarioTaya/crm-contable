<?php

namespace App\Http\Controllers;

use App\Models\Operacion;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlDeudaController extends Controller
{
    /**
     * Dashboard de Control de Deudas pendientes con SUNAT.
     */
    public function index(Request $request): View
    {
        $deudas = Operacion::where('tiene_deuda', true)
            ->with(['cliente', 'abonosDeuda'])
            ->orderBy('vencimiento_deuda')
            ->get()
            ->filter(fn (Operacion $op) => $op->saldo_pendiente > 0);

        $todasDeudas = Operacion::where('tiene_deuda', true)
            ->with('abonosDeuda')
            ->get();

        $totalDeudaHistorica = $todasDeudas->sum('monto_deuda_inicial');
        $totalRecaudado = $todasDeudas->sum(fn (Operacion $op) => $op->total_abonado);
        $totalSaldoPorCobrar = $todasDeudas->sum(fn (Operacion $op) => $op->saldo_pendiente);

        return view('deudas.index', compact(
            'deudas',
            'totalDeudaHistorica',
            'totalRecaudado',
            'totalSaldoPorCobrar',
        ));
    }
}
