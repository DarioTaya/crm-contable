<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\Navegacion\SelectorEmpresa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SelectorEmpresaController extends Controller
{
    public function __construct(private SelectorEmpresa $selector) {}

    /**
     * Cartera visible para el buscador del acceso rápido.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cliente::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
        ]);

        $termino = trim((string) ($validated['q'] ?? ''));

        return response()
            ->json($this->selector->buscar($termino))
            ->header('Cache-Control', 'private, no-store');
    }

    /**
     * Fija la empresa que el contador quiere a mano cuando no está dentro de un expediente.
     */
    public function seleccionar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
        ]);

        $cliente = Cliente::query()
            ->paraUsuario()
            ->whereKey($validated['cliente_id'])
            ->first();

        if ($cliente === null) {
            abort(404);
        }

        Gate::authorize('view', $cliente);

        $this->selector->recordar($request, $cliente);

        return response()
            ->json(['empresa' => $this->selector->presentar($cliente)])
            ->header('Cache-Control', 'private, no-store');
    }
}
