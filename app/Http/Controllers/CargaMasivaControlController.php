<?php

namespace App\Http\Controllers;

use App\Http\Requests\CargaMasivaControlRequest;
use App\Services\ControlImpuestos\CargaMasivaControlImpuestos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CargaMasivaControlController extends Controller
{
    public function __construct(private CargaMasivaControlImpuestos $carga) {}

    public function plantilla(Request $request): Response
    {
        $anio = (int) $request->query('anio', now()->year);
        if ($anio < 2000 || $anio > 2100) {
            $anio = (int) now()->year;
        }

        $nombre = 'control-impuestos-'.$anio.'.csv';

        return response($this->carga->plantilla($anio, $this->idsDePlantilla($request)), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function previsualizar(CargaMasivaControlRequest $request): JsonResponse
    {
        return response()->json($this->carga->previsualizar($request->solicitud())->toArray());
    }

    public function aplicar(CargaMasivaControlRequest $request): JsonResponse
    {
        $resultado = $this->carga->aplicar($request->solicitud());

        return response()->json($resultado->toArray(), $resultado->aplicada ? 200 : 422);
    }

    /**
     * @return list<int>
     */
    private function idsDePlantilla(Request $request): array
    {
        $brutos = $request->query('cliente_ids', []);
        if (! is_array($brutos)) {
            $brutos = [$brutos];
        }

        $ids = [];
        foreach ($brutos as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $ids[] = (int) $id;
            }
        }

        return array_slice(array_values(array_unique($ids)), 0, 500);
    }
}
