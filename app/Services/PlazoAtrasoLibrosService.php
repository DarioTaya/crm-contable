<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Plazo máximo de atraso del Libro Diario, del Libro Diario de formato
 * simplificado y del Libro Mayor.
 *
 * Anexo 2, numerales 5, 5-A y 6, de la R.S. N.° 234-2006/SUNAT, con el texto
 * de la R.S. N.° 000257-2025/SUNAT (vigente desde el 1 de agosto de 2025):
 * tres (3) meses desde el primer día hábil del mes siguiente de realizadas
 * las operaciones.
 *
 * Norma XII del Título Preliminar del Código Tributario: el plazo en meses se
 * cumple el día del mes de vencimiento que corresponde al día de inicio. Si
 * ese día no existe, vence el último día de ese mes. Si el vencimiento cae en
 * día inhábil para la Administración, se prorroga al primer día hábil siguiente.
 *
 * El PDT 0625 solo cambia el plazo del contribuyente que lo presenta para
 * modificar o suspender pagos a cuenta. Este cronograma es el plazo ordinario.
 *
 * Feriados de 2026 tomados de gob.pe/feriados y gob.pe/feriados/historicos al
 * 3 de octubre de 2026, incluido el D.S. N.° 075-2026-PCM. En años posteriores
 * se usan los feriados nacionales de fecha fija y Semana Santa. Un decreto que
 * agregue un día no laborable del sector público se corrige en la grilla.
 */
class PlazoAtrasoLibrosService
{
    /**
     * @var array<int, array<string, true>>
     */
    private array $diasInhabiles = [];

    public function fechaLimite(int $mes, int $anio): Carbon
    {
        $inicio = $this->primerDiaHabilDelMesSiguiente($mes, $anio);

        return $this->primerDiaHabilDesde($inicio->copy()->addMonthsNoOverflow(3));
    }

    public function primerDiaHabilDelMesSiguiente(int $mes, int $anio): Carbon
    {
        $mesSiguiente = Carbon::create($anio, $mes, 1, 0, 0, 0, 'America/Lima')->addMonthNoOverflow();

        return $this->primerDiaHabilDesde($mesSiguiente);
    }

    /**
     * Período cuyas operaciones vencen dentro del mes de la fecha indicada.
     */
    public function periodoQueVenceEn(Carbon $fecha): string
    {
        $fecha = $fecha->copy()->timezone('America/Lima');

        for ($retroceso = 6; $retroceso >= 2; $retroceso--) {
            $periodo = $fecha->copy()->startOfMonth()->subMonthsNoOverflow($retroceso);
            $limite = $this->fechaLimite((int) $periodo->month, (int) $periodo->year);

            if ((int) $limite->year === (int) $fecha->year && (int) $limite->month === (int) $fecha->month) {
                return $periodo->format('m-Y');
            }
        }

        $proximo = null;
        $proximaFecha = null;

        for ($retroceso = 6; $retroceso >= 0; $retroceso--) {
            $periodo = $fecha->copy()->startOfMonth()->subMonthsNoOverflow($retroceso);
            $limite = $this->fechaLimite((int) $periodo->month, (int) $periodo->year)->startOfDay();

            if ($limite->greaterThanOrEqualTo($fecha->copy()->startOfDay()) && ($proximaFecha === null || $limite->lessThan($proximaFecha))) {
                $proximaFecha = $limite;
                $proximo = $periodo->format('m-Y');
            }
        }

        return $proximo ?? $fecha->copy()->subMonthsNoOverflow(4)->format('m-Y');
    }

    /**
     * @return array<int, Carbon>
     */
    public function fechasDelEjercicio(int $anio): array
    {
        $fechas = [];

        for ($mes = 1; $mes <= 12; $mes++) {
            $fechas[$mes] = $this->fechaLimite($mes, $anio);
        }

        return $fechas;
    }

    public function esDiaHabil(Carbon $fecha): bool
    {
        $fecha = $fecha->copy()->timezone('America/Lima')->startOfDay();

        if ($fecha->isWeekend()) {
            return false;
        }

        return ! isset($this->calendario((int) $fecha->year)[$fecha->toDateString()]);
    }

    public function primerDiaHabilDesde(Carbon $fecha): Carbon
    {
        $cursor = $fecha->copy()->timezone('America/Lima')->startOfDay();

        while (! $this->esDiaHabil($cursor)) {
            $cursor->addDay();
        }

        return $cursor;
    }

    /**
     * @return array<string, true>
     */
    private function calendario(int $anio): array
    {
        if (isset($this->diasInhabiles[$anio])) {
            return $this->diasInhabiles[$anio];
        }

        $fechas = [
            sprintf('%d-01-01', $anio),
            sprintf('%d-05-01', $anio),
            sprintf('%d-06-07', $anio),
            sprintf('%d-06-29', $anio),
            sprintf('%d-07-23', $anio),
            sprintf('%d-07-28', $anio),
            sprintf('%d-07-29', $anio),
            sprintf('%d-08-06', $anio),
            sprintf('%d-08-30', $anio),
            sprintf('%d-10-08', $anio),
            sprintf('%d-11-01', $anio),
            sprintf('%d-12-08', $anio),
            sprintf('%d-12-09', $anio),
            sprintf('%d-12-25', $anio),
        ];

        $pascua = Carbon::create($anio, 3, 21, 0, 0, 0, 'America/Lima')->addDays(easter_days($anio));
        $fechas[] = $pascua->copy()->subDays(3)->toDateString();
        $fechas[] = $pascua->copy()->subDays(2)->toDateString();

        if ($anio === 2026) {
            $fechas[] = '2026-01-02';
            $fechas[] = '2026-07-27';
        }

        $this->diasInhabiles[$anio] = array_fill_keys($fechas, true);

        return $this->diasInhabiles[$anio];
    }
}
