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
            'mesAnterior' => $this->periodoVecino($anio, $mes, -1, $hoy),
            'mesSiguiente' => $this->periodoVecino($anio, $mes, 1, $hoy),
            'serie' => $this->tipoCambioSunat->serieAnual($hoy),
            'hoyIso' => $hoy->toDateString(),
        ]);
    }

    /**
     * Copia el tipo de cambio oficial del mes elegido. El mes en curso también se completa solo.
     */
    public function sincronizar(Request $request): RedirectResponse|JsonResponse
    {
        $hoy = now('America/Lima');
        $anio = (int) $request->input('anio');
        $mes = (int) $request->input('mes');
        [$anioVisible, $mesVisible] = $this->periodoVisible($anio, $mes, $hoy);

        if ($anio !== $anioVisible || $mes !== $mesVisible) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'Ese mes todavía no tiene tipo de cambio publicado.',
                ], 422);
            }

            return redirect()
                ->route('tipo-cambio.index', ['anio' => $anioVisible, 'mes' => $mesVisible])
                ->with('error', 'Ese mes todavía no tiene tipo de cambio publicado.');
        }

        $resultado = $this->tipoCambioSunat->sincronizarMes($anio, $mes);

        if ($request->expectsJson()) {
            if (! $resultado['ok']) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'No se pudo copiar el tipo de cambio de SUNAT. Inténtalo de nuevo en unos minutos.',
                ], 500);
            }

            if ($resultado['dias'] === 0) {
                return response()->json([
                    'ok' => false,
                    'mensaje' => 'SUNAT no publicó cotizaciones para ese mes.',
                ], 404);
            }

            $datos = $this->tipoCambioSunat->datosMes($anio, $mes, false);
            $nombre = lcfirst($this->nombreMes($anio, $mes));
            $mensaje = $resultado['dias'] === 1
                ? 'Se copió 1 cotización de '.$nombre.'.'
                : 'Se copiaron '.$resultado['dias'].' cotizaciones de '.$nombre.'.';

            return response()->json([
                'ok' => true,
                'dias' => $resultado['dias'],
                'mensaje' => $mensaje,
                'datos' => $datos,
            ]);
        }

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
     * Devuelve el calendario mensual de cotizaciones en JSON para la ventana flotante de acceso rápido.
     */
    public function mes(Request $request): JsonResponse
    {
        $hoy = now('America/Lima');
        $anioPedido = (int) $request->input('anio', $hoy->year);
        $mesPedido = (int) $request->input('mes', $hoy->month);

        $datos = $this->tipoCambioSunat->datosMes($anioPedido, $mesPedido, true);

        return response()->json($datos);
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
        return $this->tipoCambioSunat->calendario($anio, $mes, $historial, $hoy);
    }

    /**
     * Mes anterior o siguiente dentro del rango publicado (desde 2010 hasta el mes en curso).
     *
     * @return array{anio: int, mes: int}|null
     */
    private function periodoVecino(int $anio, int $mes, int $delta, Carbon $hoy): ?array
    {
        return $this->tipoCambioSunat->periodoVecino($anio, $mes, $delta, $hoy);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function periodoVisible(int $anio, int $mes, Carbon $hoy): array
    {
        return $this->tipoCambioSunat->periodoVisible($anio, $mes, $hoy);
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
        return $this->tipoCambioSunat->nombreMes($anio, $mes);
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
