<?php

namespace App\Http\Controllers;

use App\Models\TipoCambioSunat;
use App\Services\TipoCambioSunatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TipoCambioController extends Controller
{
    public function __construct(
        protected TipoCambioSunatService $tipoCambioSunat
    ) {}

    /**
     * Muestra el tipo de cambio del día y el historial del mes publicado por SUNAT.
     */
    public function index(Request $request): View|RedirectResponse
    {
        $hoy = now('America/Lima');
        $anioPedido = (int) $request->input('anio', $hoy->year);
        $mesPedido = (int) $request->input('mes', $hoy->month);
        [$anio, $mes] = $this->periodoVisible($anioPedido, $mesPedido, $hoy);

        if ($request->hasAny(['anio', 'mes']) && ($anioPedido !== $anio || $mesPedido !== $mes)) {
            return redirect()->route('tipo-cambio.index', ['anio' => $anio, 'mes' => $mes]);
        }

        if ($anio === $hoy->year && $mes === $hoy->month) {
            $this->tipoCambioSunat->asegurarMesEnCurso();
        }

        $vigente = $this->tipoCambioSunat->vigente();
        $historial = $this->tipoCambioSunat->historial($anio, $mes);

        return view('tipo-cambio.index', [
            'vigente' => $vigente,
            'historial' => $historial,
            'semanas' => $this->calendario($anio, $mes, $historial, $hoy),
            'diasSemana' => ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'],
            'anio' => $anio,
            'mes' => $mes,
            'nombreMes' => $this->nombreMes($anio, $mes),
            'meses' => $this->mesesDisponibles($anio, $hoy),
            'anios' => range($hoy->year, 2010),
            'esMesActual' => $anio === $hoy->year && $mes === $hoy->month,
            'serie' => $this->tipoCambioSunat->serieAnual($hoy),
            'hoyIso' => $hoy->toDateString(),
        ]);
    }

    /**
     * Copia el tipo de cambio oficial del mes elegido. El mes en curso también se completa solo.
     */
    public function sincronizar(Request $request): RedirectResponse
    {
        $hoy = now('America/Lima');
        $anio = (int) $request->input('anio');
        $mes = (int) $request->input('mes');
        [$anioVisible, $mesVisible] = $this->periodoVisible($anio, $mes, $hoy);

        if ($anio !== $anioVisible || $mes !== $mesVisible) {
            return redirect()
                ->route('tipo-cambio.index', ['anio' => $anioVisible, 'mes' => $mesVisible])
                ->with('error', 'Ese mes todavía no tiene tipo de cambio publicado.');
        }

        $resultado = $this->tipoCambioSunat->sincronizarMes($anio, $mes);
        $destino = redirect()->route('tipo-cambio.index', ['anio' => $anio, 'mes' => $mes]);

        if (! $resultado['ok']) {
            return $destino->with('error', 'No se pudo copiar el tipo de cambio de SUNAT. Inténtalo de nuevo en unos minutos.');
        }

        if ($resultado['dias'] === 0) {
            return $destino->with('error', 'SUNAT no publicó cotizaciones para ese mes.');
        }

        $nombre = lcfirst($this->nombreMes($anio, $mes));
        $mensaje = $resultado['dias'] === 1
            ? 'Se copió 1 cotización de '.$nombre.'.'
            : 'Se copiaron '.$resultado['dias'].' cotizaciones de '.$nombre.'.';

        return $destino->with('success', $mensaje);
    }

    /**
     * Devuelve el tipo de cambio vigente para refrescar el dashboard sin recargar la página.
     */
    public function actual(): JsonResponse
    {
        $vigente = $this->tipoCambioSunat->vigente();

        if ($vigente === null) {
            return response()->json([
                'disponible' => false,
            ]);
        }

        return response()->json([
            'disponible' => true,
            'fecha' => $vigente['fecha'],
            'fecha_corta' => $vigente['fecha_corta'],
            'compra' => $vigente['compra_texto'],
            'venta' => $vigente['venta_texto'],
            'compra_valor' => $vigente['compra'],
            'venta_valor' => $vigente['venta'],
            'diferencia' => $vigente['diferencia_texto'],
        ]);
    }

    /**
     * Calendario del mes, de domingo a sábado, como la consulta oficial de SUNAT.
     *
     * @param  Collection<int, TipoCambioSunat>  $historial
     * @return list<list<array{dia: int, del_mes: bool, hoy: bool, compra: ?string, venta: ?string}>>
     */
    private function calendario(int $anio, int $mes, Collection $historial, Carbon $hoy): array
    {
        $porDia = [];

        foreach ($historial as $fila) {
            $porDia[(int) $fila->fecha->day] = [
                'compra' => number_format((float) $fila->compra, 3, '.', ''),
                'venta' => number_format((float) $fila->venta, 3, '.', ''),
            ];
        }

        $primero = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Lima');
        $cursor = $primero->copy()->startOfWeek(Carbon::SUNDAY);
        $fin = $primero->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $semanas = [];
        $semana = [];

        while ($cursor->lte($fin)) {
            $delMes = $cursor->year === $anio && $cursor->month === $mes;
            $tasas = $delMes ? ($porDia[$cursor->day] ?? null) : null;

            $semana[] = [
                'dia' => $cursor->day,
                'del_mes' => $delMes,
                'hoy' => $delMes && $cursor->isSameDay($hoy),
                'compra' => $tasas['compra'] ?? null,
                'venta' => $tasas['venta'] ?? null,
            ];

            if (count($semana) === 7) {
                $semanas[] = $semana;
                $semana = [];
            }

            $cursor->addDay();
        }

        return $semanas;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function periodoVisible(int $anio, int $mes, Carbon $hoy): array
    {
        if ($anio < 2010 || $anio > $hoy->year) {
            $anio = $hoy->year;
        }

        $mesMaximo = $anio === $hoy->year ? $hoy->month : 12;

        if ($mes < 1 || $mes > $mesMaximo) {
            $mes = $mesMaximo;
        }

        return [$anio, $mes];
    }

    /**
     * @return array<int, string>
     */
    private function mesesDisponibles(int $anio, Carbon $hoy): array
    {
        $tope = $anio === $hoy->year ? $hoy->month : 12;
        $meses = [];

        for ($numero = 1; $numero <= $tope; $numero++) {
            $meses[$numero] = $this->nombreMesCorto($numero);
        }

        return $meses;
    }

    private function nombreMes(int $anio, int $mes): string
    {
        $texto = ucfirst(Carbon::createFromDate($anio, $mes, 1)->locale('es')->isoFormat('MMMM [de] YYYY'));

        return $this->setiembre($texto);
    }

    private function nombreMesCorto(int $mes): string
    {
        $texto = ucfirst(Carbon::createFromDate(2026, $mes, 1)->locale('es')->isoFormat('MMMM'));

        return $this->setiembre($texto);
    }

    private function setiembre(string $texto): string
    {
        return str_replace(
            ['Septiembre', 'septiembre'],
            ['Setiembre', 'setiembre'],
            $texto
        );
    }
}
