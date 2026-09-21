<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OperacionController extends Controller
{
    /**
     * Guarda o actualiza el estado de las obligaciones de un periodo específico para el cliente.
     */
    public function storeOrUpdate(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
            'estado_compras_ventas' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_pdt621' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_ple' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'periodo.required' => 'El periodo contable es obligatorio.',
            'periodo.regex' => 'El formato del periodo debe ser MM-AAAA (ej. 08-2026).',
            'estado_compras_ventas.in' => 'El estado de Compras/Ventas (SIRE) es inválido.',
            'estado_pdt621.in' => 'El estado de PDT 621 es inválido.',
            'estado_ple.in' => 'El estado de PLE es inválido.',
        ]);

        $cliente->operaciones()->updateOrCreate(
            ['periodo' => $validated['periodo']],
            [
                'estado_compras_ventas' => $validated['estado_compras_ventas'],
                'estado_pdt621' => $validated['estado_pdt621'],
                'estado_ple' => $validated['estado_ple'],
                'notas' => $validated['notas'] ?? null,
            ]
        );

        return redirect()->route('clientes.show', ['cliente' => $cliente, 'tab' => 'operaciones'])
            ->with('success', "✅ Periodo {$validated['periodo']} actualizado correctamente.");
    }
}
