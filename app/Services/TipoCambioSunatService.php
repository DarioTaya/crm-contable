<?php

namespace App\Services;

use App\Models\TipoCambioSunat;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TipoCambioSunatService
{
    private const CLAVE_VIGENTE = 'sunat.tipo-cambio.vigente';

    private const CLAVE_ULTIMO = 'sunat.tipo-cambio.ultimo';

    /**
     * El listado mensual exige este campo con un valor no vacío.
     * La serie que devuelve es la publicación pública de SUNAT.
     */
    private const TOKEN_CONSULTA = 'consulta';

    /**
     * Tipo de cambio vigente publicado por SUNAT.
     *
     * Se reconsulta como máximo cada 15 minutos. Si SUNAT no responde,
     * se conserva la última publicación guardada.
     *
     * @return array{
     *     fecha: string,
     *     fecha_corta: string,
     *     fecha_iso: string,
     *     compra: float,
     *     venta: float,
     *     compra_texto: string,
     *     venta_texto: string,
     *     diferencia_texto: string,
     *     moneda: string,
     *     consultado_en: string
     * }|null
     */
    public function vigente(): ?array
    {
        $url = $this->url();

        if ($url === null) {
            return null;
        }

        $cacheado = Cache::get(self::CLAVE_VIGENTE);

        if (is_array($cacheado) && $this->sigueFresco($cacheado)) {
            return $this->esFallo($cacheado) ? $this->ultimoDisponible() : $cacheado;
        }

        $fresco = $this->consultar($url);

        if ($fresco !== null) {
            Cache::put(self::CLAVE_VIGENTE, $fresco, now()->addMinutes(15));
            Cache::put(self::CLAVE_ULTIMO, $fresco, now()->addDays(7));

            return $fresco;
        }

        Cache::put(self::CLAVE_VIGENTE, [
            'fallo' => true,
            'consultado_en' => now()->toIso8601String(),
        ], now()->addMinutes(5));

        return $this->ultimoDisponible();
    }

    /**
     * Obliga una consulta nueva a SUNAT, sin usar la copia de 15 minutos.
     *
     * @return array{
     *     fecha: string,
     *     fecha_corta: string,
     *     fecha_iso: string,
     *     compra: float,
     *     venta: float,
     *     compra_texto: string,
     *     venta_texto: string,
     *     diferencia_texto: string,
     *     moneda: string,
     *     consultado_en: string
     * }|null
     */
    public function sincronizar(): ?array
    {
        Cache::forget(self::CLAVE_VIGENTE);

        return $this->vigente();
    }

    /**
     * Completa el mes en curso si SUNAT ya publicó el día de hoy y el mes aún no está copiado.
     * Los meses anteriores no se consultan aquí: esos se copian solo con el botón.
     */
    public function asegurarMesEnCurso(): void
    {
        $hoy = now('America/Lima');
        $clave = $this->claveMes($hoy);
        $marca = Cache::get($clave);

        if ($marca === 'listo' || $marca === 'espera') {
            return;
        }

        $resultado = $this->sincronizarMes($hoy->year, $hoy->month);

        if ($resultado['incluye_hoy']) {
            return;
        }

        Cache::put($clave, 'espera', now()->addMinutes(5));
    }

    /**
     * Copia el tipo de cambio oficial de un mes. SUNAT numera enero como 0.
     * No consulta meses posteriores al actual ni días que todavía no se publican.
     *
     * @return array{ok: bool, dias: int, incluye_hoy: bool}
     */
    public function sincronizarMes(int $anio, int $mes): array
    {
        $hoy = now('America/Lima');
        $url = $this->urlMes();

        if ($url === null || $mes < 1 || $mes > 12 || $anio < 2010 || $anio > $hoy->year) {
            return $this->resultadoMes(false);
        }

        $inicio = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Lima');

        if ($inicio->gt($hoy->copy()->startOfMonth())) {
            return $this->resultadoMes(false);
        }

        try {
            $respuesta = Http::acceptJson()
                ->withHeaders([
                    'Origin' => 'https://e-consulta.sunat.gob.pe',
                    'Referer' => 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias',
                ])
                ->withUserAgent('Mozilla/5.0')
                ->connectTimeout(3)
                ->timeout(10)
                ->post($url, [
                    'anio' => $anio,
                    'mes' => $mes - 1,
                    'token' => self::TOKEN_CONSULTA,
                ]);
        } catch (Throwable $exception) {
            report($exception);

            return $this->resultadoMes(false);
        }

        if (! $respuesta->successful()) {
            return $this->resultadoMes(false);
        }

        $filas = $respuesta->json();

        if (! is_array($filas) || ! array_is_list($filas)) {
            return $this->resultadoMes(false);
        }

        $porFecha = [];

        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $fecha = $this->fechaPublicada((string) ($fila['fecPublica'] ?? ''));

            if ($fecha === null || $fecha->year !== $anio || $fecha->month !== $mes) {
                continue;
            }

            if ($fecha->toDateString() > $hoy->toDateString()) {
                continue;
            }

            $valor = $this->tasa((string) ($fila['valTipo'] ?? ''));

            if ($valor === null) {
                continue;
            }

            $iso = $fecha->toDateString();
            $tipo = strtoupper(trim((string) ($fila['codTipo'] ?? '')));

            if ($tipo === 'C') {
                $porFecha[$iso]['compra'] = $valor;
            }

            if ($tipo === 'V') {
                $porFecha[$iso]['venta'] = $valor;
            }
        }

        $guardados = 0;
        $incluyeHoy = false;

        foreach ($porFecha as $iso => $tasas) {
            if (! isset($tasas['compra'], $tasas['venta']) || $tasas['venta'] < $tasas['compra']) {
                continue;
            }

            try {
                TipoCambioSunat::query()->updateOrCreate(
                    ['fecha' => $iso],
                    [
                        'compra' => $tasas['compra'],
                        'venta' => $tasas['venta'],
                    ],
                );
            } catch (Throwable $exception) {
                report($exception);

                return $this->resultadoMes(false, $guardados);
            }

            $guardados++;

            if ($iso === $hoy->toDateString()) {
                $incluyeHoy = true;
            }
        }

        if ($incluyeHoy) {
            Cache::put($this->claveMes($hoy), 'listo', $hoy->copy()->endOfDay());
        }

        return $this->resultadoMes(true, $guardados, $incluyeHoy);
    }

    /**
     * Cotizaciones de los últimos doce meses, hasta el día indicado.
     *
     * @return list<array{fecha: string, compra: float, venta: float}>
     */
    public function serieAnual(Carbon $hasta): array
    {
        $desde = $hasta->copy()->subYear()->startOfDay();

        return TipoCambioSunat::query()
            ->where('fecha', '>=', $desde->toDateString())
            ->where('fecha', '<=', $hasta->toDateString())
            ->orderBy('fecha')
            ->get(['fecha', 'compra', 'venta'])
            ->map(fn (TipoCambioSunat $fila): array => [
                'fecha' => $fila->fecha->toDateString(),
                'compra' => round((float) $fila->compra, 3),
                'venta' => round((float) $fila->venta, 3),
            ])
            ->all();
    }

    /**
     * Publicaciones ya guardadas de un mes.
     *
     * @return Collection<int, TipoCambioSunat>
     */
    public function historial(int $anio, int $mes): Collection
    {
        return TipoCambioSunat::query()
            ->whereYear('fecha', $anio)
            ->whereMonth('fecha', $mes)
            ->orderBy('fecha')
            ->get();
    }

    /**
     * Presenta una tasa con los decimales que publica SUNAT, como mínimo tres.
     */
    public function formatearTasa(float|string $tasa): string
    {
        $numero = (float) $tasa;
        $texto = number_format($numero, 4, '.', '');
        $texto = rtrim(rtrim($texto, '0'), '.');

        if (! str_contains($texto, '.')) {
            return number_format($numero, 3, '.', '');
        }

        $decimales = strlen(substr(strrchr($texto, '.'), 1));

        if ($decimales < 3) {
            return number_format($numero, 3, '.', '');
        }

        return $texto;
    }

    private function url(): ?string
    {
        $url = trim((string) config('services.sunat.tipo_cambio_url'));

        return $url === '' ? null : $url;
    }

    private function urlMes(): ?string
    {
        $url = trim((string) config('services.sunat.tipo_cambio_mes_url'));

        return $url === '' ? null : $url;
    }

    private function claveMes(Carbon $hoy): string
    {
        return 'sunat.tipo-cambio.mes.'.$hoy->toDateString();
    }

    /**
     * @return array{ok: bool, dias: int, incluye_hoy: bool}
     */
    private function resultadoMes(bool $ok, int $dias = 0, bool $incluyeHoy = false): array
    {
        return [
            'ok' => $ok,
            'dias' => $dias,
            'incluye_hoy' => $incluyeHoy,
        ];
    }

    private function fechaPublicada(string $texto): ?Carbon
    {
        $texto = trim($texto);

        if ($texto === '') {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!d/m/Y', $texto, 'America/Lima');
        } catch (Throwable) {
            return null;
        }

        if ($fecha->format('d/m/Y') !== $texto) {
            return null;
        }

        return $fecha->startOfDay();
    }

    /**
     * @param  array{consultado_en?: string, fallo?: bool}  $cacheado
     */
    private function sigueFresco(array $cacheado): bool
    {
        if (! isset($cacheado['consultado_en'])) {
            return false;
        }

        $minutos = $this->esFallo($cacheado) ? 5 : 15;

        return Carbon::parse($cacheado['consultado_en'])->greaterThan(now()->subMinutes($minutos));
    }

    /**
     * @param  array{fallo?: bool}  $cacheado
     */
    private function esFallo(array $cacheado): bool
    {
        return ($cacheado['fallo'] ?? false) === true;
    }

    /**
     * @return array{
     *     fecha: string,
     *     fecha_corta: string,
     *     fecha_iso: string,
     *     compra: float,
     *     venta: float,
     *     compra_texto: string,
     *     venta_texto: string,
     *     diferencia_texto: string,
     *     moneda: string,
     *     consultado_en: string
     * }|null
     */
    private function ultimoDisponible(): ?array
    {
        $ultimo = Cache::get(self::CLAVE_ULTIMO);

        return is_array($ultimo) && ! $this->esFallo($ultimo) ? $ultimo : null;
    }

    /**
     * @return array{
     *     fecha: string,
     *     fecha_corta: string,
     *     fecha_iso: string,
     *     compra: float,
     *     venta: float,
     *     compra_texto: string,
     *     venta_texto: string,
     *     diferencia_texto: string,
     *     moneda: string,
     *     consultado_en: string
     * }|null
     */
    private function consultar(string $url): ?array
    {
        try {
            $response = Http::connectTimeout(2)
                ->timeout(4)
                ->get($url);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $publicado = $this->interpretar($response->body());

        if ($publicado === null) {
            return null;
        }

        try {
            $this->guardar($publicado);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $publicado;
    }

    /**
     * El archivo oficial trae una línea `dd/mm/aaaa|compra|venta|`.
     *
     * @return array{
     *     fecha: string,
     *     fecha_corta: string,
     *     fecha_iso: string,
     *     compra: float,
     *     venta: float,
     *     compra_texto: string,
     *     venta_texto: string,
     *     diferencia_texto: string,
     *     moneda: string,
     *     consultado_en: string
     * }|null
     */
    private function interpretar(string $cuerpo): ?array
    {
        $linea = trim($cuerpo);
        $partes = explode('|', $linea);

        if (count($partes) < 3) {
            return null;
        }

        try {
            $fecha = Carbon::createFromFormat('!d/m/Y', trim($partes[0]));
        } catch (Throwable) {
            return null;
        }

        $compra = $this->tasa($partes[1]);
        $venta = $this->tasa($partes[2]);

        if (! $fecha || $fecha->format('d/m/Y') !== trim($partes[0]) || $compra === null || $venta === null || $venta < $compra) {
            return null;
        }

        $fecha = $fecha->locale('es');
        $nombre = str_replace('septiembre', 'setiembre', $fecha->isoFormat('D [de] MMMM [de] YYYY'));

        return [
            'fecha' => $nombre,
            'fecha_corta' => $fecha->format('d/m/Y'),
            'fecha_iso' => $fecha->toDateString(),
            'compra' => $compra,
            'venta' => $venta,
            'compra_texto' => $this->formatearTasa($compra),
            'venta_texto' => $this->formatearTasa($venta),
            'diferencia_texto' => $this->formatearTasa($venta - $compra),
            'moneda' => 'USD/PEN',
            'consultado_en' => now()->toIso8601String(),
        ];
    }

    private function tasa(string $valor): ?float
    {
        $texto = trim($valor);

        if (! is_numeric($texto)) {
            return null;
        }

        $numero = (float) $texto;

        if ($numero <= 0) {
            return null;
        }

        return $numero;
    }

    /**
     * @param  array{fecha_iso: string, compra: float, venta: float}  $publicado
     */
    private function guardar(array $publicado): void
    {
        TipoCambioSunat::query()->updateOrCreate(
            ['fecha' => $publicado['fecha_iso']],
            [
                'compra' => $publicado['compra'],
                'venta' => $publicado['venta'],
            ],
        );
    }
}
