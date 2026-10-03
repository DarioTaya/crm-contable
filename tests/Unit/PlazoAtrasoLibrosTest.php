<?php

namespace Tests\Unit;

use App\Services\PlazoAtrasoLibrosService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class PlazoAtrasoLibrosTest extends TestCase
{
    private PlazoAtrasoLibrosService $plazos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plazos = new PlazoAtrasoLibrosService;
    }

    public function test_julio_2024_vence_el_primer_dia_habil_despues_de_todos_los_santos(): void
    {
        $this->assertSame('2024-08-01', $this->plazos->primerDiaHabilDelMesSiguiente(7, 2024)->toDateString());
        $this->assertFalse($this->plazos->esDiaHabil(Carbon::parse('2024-11-01', 'America/Lima')));
        $this->assertSame('2024-11-04', $this->plazos->fechaLimite(7, 2024)->toDateString());
    }

    public function test_diciembre_2020_empieza_el_4_de_enero_y_corre_el_domingo_4_de_abril(): void
    {
        $this->assertSame('2021-01-04', $this->plazos->primerDiaHabilDelMesSiguiente(12, 2020)->toDateString());
        $this->assertSame('2021-04-05', $this->plazos->fechaLimite(12, 2020)->toDateString());
    }

    public function test_ejercicio_2026_usa_el_calendario_nacional_publicado(): void
    {
        $this->assertFalse($this->plazos->esDiaHabil(Carbon::parse('2026-04-02', 'America/Lima')));
        $this->assertFalse($this->plazos->esDiaHabil(Carbon::parse('2026-04-03', 'America/Lima')));
        $this->assertFalse($this->plazos->esDiaHabil(Carbon::parse('2026-01-02', 'America/Lima')));
        $this->assertFalse($this->plazos->esDiaHabil(Carbon::parse('2026-07-27', 'America/Lima')));

        $fechas = array_map(
            fn (Carbon $fecha): string => $fecha->toDateString(),
            $this->plazos->fechasDelEjercicio(2026),
        );

        $this->assertSame([
            1 => '2026-05-04',
            2 => '2026-06-02',
            3 => '2026-07-01',
            4 => '2026-08-04',
            5 => '2026-09-01',
            6 => '2026-10-01',
            7 => '2026-11-03',
            8 => '2026-12-01',
            9 => '2027-01-04',
            10 => '2027-02-02',
            11 => '2027-03-01',
            12 => '2027-04-05',
        ], $fechas);
    }

    public function test_el_periodo_en_curso_es_el_que_vence_dentro_del_mes(): void
    {
        $setiembre = Carbon::create(2026, 9, 23, 0, 0, 0, 'America/Lima');
        $enero = Carbon::create(2027, 1, 15, 0, 0, 0, 'America/Lima');

        $this->assertSame('05-2026', $this->plazos->periodoQueVenceEn($setiembre));
        $this->assertSame('09-2026', $this->plazos->periodoQueVenceEn($enero));
    }
}
