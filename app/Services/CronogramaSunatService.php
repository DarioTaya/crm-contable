<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\CronogramaSunat;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Artisan;

class CronogramaSunatService
{
    /**
     * Obtiene el período tributario activo que se encuentra en fase de vencimiento/declaración.
     *
     * En el sistema tributario peruano (SUNAT), en un mes M se declaran las operaciones del mes M-1.
     * Por ejemplo, en Setiembre 2026 (09-2026), se declaran las obligaciones de Agosto 2026 (08-2026).
     */
    public function obtenerPeriodoEnCurso(string $tipo = 'pdt_621', ?Carbon $fecha = null): string
    {
        $fecha = $fecha ? $fecha->copy() : Carbon::now();
        $mesActual = (int) $fecha->format('m');
        $anioActual = (int) $fecha->format('Y');

        // Buscar en el cronograma si hay un período cuyas fechas de vencimiento caen en el mes actual
        $cronogramaMes = CronogramaSunat::query()
            ->where('tipo', $tipo)
            ->where('anio', $anioActual)
            ->whereNotNull('digito_0')
            ->whereMonth('digito_0', $mesActual)
            ->first();

        if ($cronogramaMes) {
            return $cronogramaMes->periodo;
        }

        // Si no hay cronograma configurado en BD, por defecto mantener el período del mes calendario
        return $fecha->format('m-Y');
    }

    /**
     * Obtiene la fecha exacta de vencimiento para un cliente o RUC dado.
     */
    public function obtenerFechaVencimiento(
        string $tipo,
        string $periodo,
        string|Cliente $cliente,
        bool $esBuenContribuyente = false
    ): ?Carbon {
        $cronograma = CronogramaSunat::query()
            ->where('tipo', $tipo)
            ->where('periodo', $periodo)
            ->first();

        if (! $cronograma) {
            return null;
        }

        if ($cronograma->fecha_vencimiento_general) {
            return $cronograma->fecha_vencimiento_general;
        }

        if ($cliente instanceof Cliente) {
            $ruc = $cliente->ruc ?? $cliente->numero_documento ?? '';
            $buenContribuyente = (bool) ($cliente->es_buen_contribuyente ?? $esBuenContribuyente);
        } else {
            $ruc = (string) $cliente;
            $buenContribuyente = $esBuenContribuyente;
        }

        return $cronograma->getFechaPorDigito($ruc, $buenContribuyente);
    }

    /**
     * Evalúa el estado de vencimiento en base a la fecha de vencimiento y una fecha de referencia.
     *
     * @return array{
     *     estado: 'vencido'|'hoy'|'proximo'|'al_dia'|'sin_cronograma',
     *     dias: int|null,
     *     dias_texto: string,
     *     fecha_formateada: string,
     *     badge_clase: string,
     *     badge_texto: string
     * }
     */
    public function calcularEstadoVencimiento(?Carbon $fechaVencimiento, ?Carbon $fechaReferencia = null): array
    {
        if (! $fechaVencimiento) {
            return [
                'estado' => 'sin_cronograma',
                'dias' => null,
                'dias_texto' => 'Sin fecha',
                'fecha_formateada' => '-',
                'badge_clase' => 'bg-slate-100 text-slate-600 border-slate-200',
                'badge_texto' => 'No programado',
            ];
        }

        $hoy = ($fechaReferencia ? $fechaReferencia->copy() : Carbon::now())->startOfDay();
        $vence = $fechaVencimiento->copy()->startOfDay();

        $formatoFecha = $vence->translatedFormat('d M');

        if ($vence->lt($hoy)) {
            $dias = abs((int) $hoy->diffInDays($vence));

            return [
                'estado' => 'vencido',
                'dias' => $dias,
                'dias_texto' => "Venció hace {$dias} ".($dias === 1 ? 'día' : 'días'),
                'fecha_formateada' => $vence->format('d/m/Y'),
                'badge_clase' => 'bg-rose-50 text-rose-700 border-rose-200',
                'badge_texto' => "⚠️ Vencido ({$formatoFecha})",
            ];
        }

        if ($vence->isSameDay($hoy)) {
            return [
                'estado' => 'hoy',
                'dias' => 0,
                'dias_texto' => 'Vence hoy',
                'fecha_formateada' => $vence->format('d/m/Y'),
                'badge_clase' => 'bg-amber-100 text-amber-900 border-amber-300 font-semibold animate-pulse',
                'badge_texto' => "⚡ Vence Hoy ({$formatoFecha})",
            ];
        }

        // Futuro
        $dias = abs((int) $hoy->diffInDays($vence));

        if ($dias <= 3) {
            return [
                'estado' => 'proximo',
                'dias' => $dias,
                'dias_texto' => "Vence en {$dias} ".($dias === 1 ? 'día' : 'días'),
                'fecha_formateada' => $vence->format('d/m/Y'),
                'badge_clase' => 'bg-amber-50 text-amber-700 border-amber-200',
                'badge_texto' => "⏳ Vence {$formatoFecha} (en {$dias}d)",
            ];
        }

        return [
            'estado' => 'al_dia',
            'dias' => $dias,
            'dias_texto' => "Vence en {$dias} días",
            'fecha_formateada' => $vence->format('d/m/Y'),
            'badge_clase' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'badge_texto' => "✓ {$formatoFecha}",
        ];
    }

    /**
     * Auto-genera el cronograma SIRE para un año a partir de las fechas de PDT 621,
     * restando un (1) día hábil a cada fecha (saltando sábados y domingos).
     */
    public function autogenerarSireDesdePdt(int $anio): int
    {
        $pdtCronogramas = CronogramaSunat::query()
            ->where('anio', $anio)
            ->where('tipo', 'pdt_621')
            ->get();

        $camposDigitos = [
            'digito_0',
            'digito_1',
            'digito_2_3',
            'digito_4_5',
            'digito_6_7',
            'digito_8_9',
            'buenos_contribuyentes',
        ];

        $actualizados = 0;

        foreach ($pdtCronogramas as $pdt) {
            $datosSire = [
                'anio' => $anio,
                'tipo' => 'sire',
                'mes' => $pdt->mes,
                'periodo' => $pdt->periodo,
                'descripcion' => "Registros Electrónicos SIRE - Período {$pdt->mes_nombre} {$anio}",
            ];

            foreach ($camposDigitos as $campo) {
                if ($pdt->$campo) {
                    $datosSire[$campo] = $this->restarDiaHabil($pdt->$campo);
                }
            }

            CronogramaSunat::updateOrCreate(
                ['anio' => $anio, 'tipo' => 'sire', 'periodo' => $pdt->periodo],
                $datosSire
            );

            $actualizados++;
        }

        return $actualizados;
    }

    /**
     * Resta un día hábil (lunes a viernes).
     */
    public function restarDiaHabil(Carbon $fecha): Carbon
    {
        $nuevaFecha = $fecha->copy()->subDay();

        // Si cae domingo (0 o 7 según Carbon), retroceder a viernes
        if ($nuevaFecha->isSunday()) {
            $nuevaFecha->subDays(2);
        } elseif ($nuevaFecha->isSaturday()) {
            $nuevaFecha->subDay();
        }

        return $nuevaFecha;
    }

    /**
     * Restablece el cronograma oficial de SUNAT 2026 ejecutando el seeder oficial.
     */
    public function restablecerOficial2026(): void
    {
        Artisan::call('db:seed', [
            '--class' => 'Database\\Seeders\\CronogramaSunatSeeder',
            '--force' => true,
        ]);
    }

    /**
     * Obtiene todos los registros de un año y tipo ordenados.
     */
    public function obtenerCronograma(int $anio, string $tipo): Collection
    {
        return CronogramaSunat::query()
            ->where('anio', $anio)
            ->where('tipo', $tipo)
            ->orderBy('mes')
            ->get();
    }
}
