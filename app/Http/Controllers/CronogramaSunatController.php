<?php

namespace App\Http\Controllers;

use App\Models\CronogramaSunat;
use App\Services\CronogramaSunatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CronogramaSunatController extends Controller
{
    public function __construct(
        protected CronogramaSunatService $cronogramaService
    ) {}

    /**
     * Muestra la matriz del cronograma oficial de SUNAT para el año y tipo seleccionado.
     */
    public function index(Request $request): View
    {
        $anio = (int) $request->input('anio', 2026);
        $tipo = $request->input('tipo', 'pdt_621');

        $tiposValidos = ['pdt_621', 'sire', 'balance_mensual', 'balance_anual'];
        if (! in_array($tipo, $tiposValidos, true)) {
            $tipo = 'pdt_621';
        }

        $aniosDisponibles = [2025, 2026, 2027, 2028];

        $cronogramas = $this->cronogramaService->obtenerCronograma($anio, $tipo);

        // Si no existen registros para este año y tipo, inicializar estructura vacía para edición directa
        if ($cronogramas->isEmpty()) {
            $this->inicializarEstructuraAnio($anio, $tipo);
            $cronogramas = $this->cronogramaService->obtenerCronograma($anio, $tipo);
        }

        $periodoActivo = $this->cronogramaService->obtenerPeriodoEnCurso($tipo);

        $totales = [
            'pdt_621' => CronogramaSunat::query()->where('anio', $anio)->where('tipo', 'pdt_621')->count(),
            'sire' => CronogramaSunat::query()->where('anio', $anio)->where('tipo', 'sire')->count(),
            'balance_mensual' => CronogramaSunat::query()->where('anio', $anio)->where('tipo', 'balance_mensual')->count(),
            'balance_anual' => CronogramaSunat::query()->where('anio', $anio)->where('tipo', 'balance_anual')->count(),
        ];

        return view('cronogramas.index', compact(
            'cronogramas',
            'anio',
            'tipo',
            'aniosDisponibles',
            'periodoActivo',
            'totales'
        ));
    }

    /**
     * Actualiza masivamente los vencimientos editados en la grilla del cronograma.
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'cronogramas' => ['required', 'array'],
            'cronogramas.*.id' => ['required', 'exists:cronogramas_sunat,id'],
            'cronogramas.*.digito_0' => ['nullable', 'date'],
            'cronogramas.*.digito_1' => ['nullable', 'date'],
            'cronogramas.*.digito_2_3' => ['nullable', 'date'],
            'cronogramas.*.digito_4_5' => ['nullable', 'date'],
            'cronogramas.*.digito_6_7' => ['nullable', 'date'],
            'cronogramas.*.digito_8_9' => ['nullable', 'date'],
            'cronogramas.*.buenos_contribuyentes' => ['nullable', 'date'],
            'cronogramas.*.fecha_vencimiento_general' => ['nullable', 'date'],
            'cronogramas.*.descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $camposFecha = [
            'digito_0',
            'digito_1',
            'digito_2_3',
            'digito_4_5',
            'digito_6_7',
            'digito_8_9',
            'buenos_contribuyentes',
            'fecha_vencimiento_general',
        ];

        foreach ($validated['cronogramas'] as $item) {
            $cronograma = CronogramaSunat::find($item['id']);
            if (! $cronograma) {
                continue;
            }

            $datosActualizar = [];
            foreach ($camposFecha as $campo) {
                $datosActualizar[$campo] = ! empty($item[$campo]) ? Carbon::parse($item[$campo])->format('Y-m-d') : null;
            }

            if (isset($item['descripcion'])) {
                $datosActualizar['descripcion'] = $item['descripcion'];
            }

            $cronograma->update($datosActualizar);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Cronograma tributario actualizado correctamente.',
            ]);
        }

        return back()->with('success', 'Cronograma tributario actualizado correctamente.');
    }

    /**
     * Auto-genera las fechas de vencimiento del SIRE restando 1 día hábil al PDT 621.
     */
    public function autogenerarSire(Request $request): JsonResponse|RedirectResponse
    {
        $anio = (int) $request->input('anio', 2026);

        $actualizados = $this->cronogramaService->autogenerarSireDesdePdt($anio);

        $mensaje = "Se auto-generaron con éxito {$actualizados} períodos del cronograma SIRE (1 día hábil previo a PDT 621).";

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'actualizados' => $actualizados,
            ]);
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Restablece el cronograma oficial de SUNAT 2026 con los datos base normativos.
     */
    public function restablecer(Request $request): JsonResponse|RedirectResponse
    {
        $this->cronogramaService->restablecerOficial2026();

        $mensaje = 'Cronograma oficial SUNAT 2026 restablecido con éxito.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
            ]);
        }

        return back()->with('success', $mensaje);
    }

    /**
     * Inicializa los 12 meses vacíos para un año y tipo nuevo.
     */
    protected function inicializarEstructuraAnio(int $anio, string $tipo): void
    {
        if ($tipo === 'balance_anual') {
            CronogramaSunat::firstOrCreate([
                'anio' => $anio,
                'tipo' => $tipo,
                'periodo' => (string) $anio,
            ], [
                'mes' => 12,
                'descripcion' => "Declaración Jurada Anual del Impuesto a la Renta {$anio}",
            ]);

            return;
        }

        for ($mes = 1; $mes <= 12; $mes++) {
            $periodo = sprintf('%02d-%d', $mes, $anio);
            CronogramaSunat::firstOrCreate([
                'anio' => $anio,
                'tipo' => $tipo,
                'periodo' => $periodo,
            ], [
                'mes' => $mes,
                'descripcion' => "Cronograma {$tipo} - Período {$periodo}",
            ]);
        }
    }
}
