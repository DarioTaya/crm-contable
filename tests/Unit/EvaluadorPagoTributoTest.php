<?php

namespace Tests\Unit;

use App\Services\Impuestos\EvaluadorPagoTributo;
use Tests\TestCase;

class EvaluadorPagoTributoTest extends TestCase
{
    private EvaluadorPagoTributo $evaluador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->evaluador = new EvaluadorPagoTributo;
    }

    public function test_pago_en_el_dia_del_vencimiento_esta_a_tiempo(): void
    {
        $resultado = $this->evaluador->evaluar('1500.00', '2026-02-18', [
            ['id' => 1, 'monto' => '1500.00', 'fecha' => '2026-02-18'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::PAGADO_A_TIEMPO, $resultado->situacion);
        $this->assertSame('2026-02-18', $resultado->fechaCancelacion);
        $this->assertFalse($resultado->debe);
        $this->assertSame(1, $resultado->cantidadPagos);
        $this->assertSame(0, $resultado->saldoCentavos);
    }

    public function test_el_dia_que_completa_el_importe_define_si_llego_fuera_de_fecha(): void
    {
        $resultado = $this->evaluador->evaluar(1500, '2026-02-18', [
            ['id' => 1, 'monto' => 800, 'fecha' => '2026-02-10'],
            ['id' => 2, 'monto' => 700, 'fecha' => '2026-03-02'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::PAGADO_FUERA_DE_FECHA, $resultado->situacion);
        $this->assertSame('2026-03-02', $resultado->fechaCancelacion);
        $this->assertSame(2, $resultado->cantidadPagos);
        $this->assertTrue($resultado->cuentaComoFueraDeFecha());
    }

    public function test_dos_pagos_dentro_del_vencimiento_cancelan_a_tiempo(): void
    {
        $resultado = $this->evaluador->evaluar(1500, '2026-02-18', [
            ['id' => 2, 'monto' => 700, 'fecha' => '2026-02-17'],
            ['id' => 1, 'monto' => 800, 'fecha' => '2026-02-10'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::PAGADO_A_TIEMPO, $resultado->situacion);
        $this->assertSame('2026-02-17', $resultado->fechaCancelacion);
    }

    public function test_saldo_antes_del_vencimiento_sigue_en_plazo(): void
    {
        $sinPagos = $this->evaluador->evaluar(1000, '2026-12-15', [], '2026-09-26');
        $parcial = $this->evaluador->evaluar(1000, '2026-12-15', [
            ['id' => 1, 'monto' => 400, 'fecha' => '2026-09-01'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::DEBE_EN_PLAZO, $sinPagos->situacion);
        $this->assertSame(60000, $parcial->saldoCentavos);
        $this->assertSame(EvaluadorPagoTributo::DEBE_PARCIAL, $parcial->situacion);
        $this->assertTrue($parcial->debe);
    }

    public function test_saldo_despues_del_vencimiento_queda_vencido(): void
    {
        $sinPagos = $this->evaluador->evaluar(1000, '2026-02-18', [], '2026-09-26');
        $parcial = $this->evaluador->evaluar(1000, '2026-02-18', [
            ['id' => 1, 'monto' => 400, 'fecha' => '2026-02-10'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::DEBE_VENCIDO, $sinPagos->situacion);
        $this->assertSame(EvaluadorPagoTributo::DEBE_PARCIAL_VENCIDO, $parcial->situacion);
        $this->assertSame('Debe · parcial vencido', $parcial->etiqueta);
    }

    public function test_el_mismo_dia_del_vencimiento_aun_no_esta_vencido(): void
    {
        $resultado = $this->evaluador->evaluar(1000, '2026-09-26', [], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::DEBE_EN_PLAZO, $resultado->situacion);
    }

    public function test_pago_sin_vencimiento_no_se_marca_a_tiempo(): void
    {
        $resultado = $this->evaluador->evaluar(500, null, [
            ['id' => 1, 'monto' => 500, 'fecha' => '2026-02-18'],
        ], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::PAGADO_SIN_VENCIMIENTO, $resultado->situacion);
        $this->assertFalse($resultado->cuentaComoFueraDeFecha());
    }

    public function test_importe_no_determinado_no_se_trata_como_cero(): void
    {
        $resultado = $this->evaluador->evaluar(0, '2026-02-18', [], '2026-09-26');

        $this->assertSame(EvaluadorPagoTributo::SIN_DETERMINAR, $resultado->situacion);
        $this->assertSame(0, $resultado->saldoCentavos);
    }
}
