<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarConstanciaRequest;
use App\Models\Cliente;
use App\Services\ControlImpuestos\ImportadorConstanciaDeclaracion;
use Illuminate\Http\JsonResponse;

class ConstanciaDeclaracionController extends Controller
{
    public function __construct(private ImportadorConstanciaDeclaracion $importador) {}

    /**
     * Lee la constancia y el detalle, cruza RUC, periodo y saldos, y devuelve la hoja lista para revisar.
     */
    public function previsualizar(ImportarConstanciaRequest $request): JsonResponse
    {
        $cliente = Cliente::query()->findOrFail($request->integer('cliente_id'));
        $archivos = $request->file('archivos', []);
        if (! is_array($archivos)) {
            $archivos = [$archivos];
        }

        return response()->json($this->importador->importar(
            $cliente,
            $request->string('periodo')->toString(),
            $request->string('formulario')->toString(),
            array_values($archivos),
        ));
    }
}
