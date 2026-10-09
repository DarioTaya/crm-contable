<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaMasivaClienteLeerRequest;
use App\Http\Requests\CargaMasivaClienteRegistrarRequest;
use App\Models\Cliente;
use App\Services\Clientes\CargaMasivaClientes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CargaMasivaClienteController extends Controller
{
    public function __construct(private CargaMasivaClientes $carga) {}

    public function plantilla(Request $request): Response
    {
        Gate::authorize('create', Cliente::class);

        $formato = strtolower((string) $request->query('formato', 'xlsx'));

        if ($formato === 'csv') {
            return response($this->carga->plantilla('csv'), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="carga-masiva-clientes.csv"',
                'Cache-Control' => 'no-store',
            ]);
        }

        return response($this->carga->plantilla('xlsx'), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="carga-masiva-clientes.xlsx"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function leer(CargaMasivaClienteLeerRequest $request): JsonResponse
    {
        return response()->json($this->carga->leer((string) $request->validated('contenido')));
    }

    public function consultar(Request $request): JsonResponse
    {
        Gate::authorize('create', Cliente::class);

        return response()->json($this->carga->consultar(trim((string) $request->input('ruc', ''))));
    }

    public function registrar(CargaMasivaClienteRegistrarRequest $request): JsonResponse
    {
        $resultado = $this->carga->registrar($request->datos());

        return response()->json($resultado, ($resultado['registrado'] ?? false) ? 200 : 422);
    }
}
