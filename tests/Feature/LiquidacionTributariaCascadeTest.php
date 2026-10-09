<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Models\User;
use App\Services\ControlImpuestos\CalculadorLiquidacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiquidacionTributariaCascadeTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Liquidaciones Test',
            'ruc' => '20888777666',
        ]);

        $this->user = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'COMERCIAL PRUEBA SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $this->actingAs($this->user);
    }

    public function test_calculo_exacto_sunat_separacion_credito_fiscal_y_retenciones(): void
    {
        $service = app(CalculadorLiquidacionService::class);

        // Ventas: 10,000 (IGV: 1,800), Compras: 15,000 (IGV: 2,700)
        // Neto fiscal = 1800 - 2700 = -900 (Crédito a favor)
        // Retenciones del mes: 200, Percepciones del mes: 150
        $datos = [
            'es_zona_selva' => false,
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 15000,
            'igv_compras' => 2700,
            'igv_saldo_favor_anterior' => 0,
            'igv_retenciones_saldo_anterior' => 0,
            'igv_retenciones' => 200,
            'igv_percepciones_saldo_anterior' => 0,
            'igv_percepciones' => 150,
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,
            'plame_essalud_base' => 2000,
            'plame_onp_a_pagar' => 260,
        ];

        $liq = new LiquidacionPeriodo($datos);
        $service->determinar($liq, $this->cliente);

        // 1. Crédito fiscal del mes = 900
        $this->assertEquals(900.0, (float) $liq->igv_saldo_favor_siguiente);
        // 2. Dado que el neto fiscal <= 0, retenciones y percepciones NO se agotan y pasan 100% al siguiente periodo
        $this->assertEquals(200.0, (float) $liq->igv_retenciones_saldo_siguiente);
        $this->assertEquals(150.0, (float) $liq->igv_percepciones_saldo_siguiente);
        // 3. Resultante a pagar IGV = 0
        $this->assertEquals(0.0, (float) $liq->igv_resultante_pagar);

        // 4. Renta MYPE 1% de 10,000 = 100
        $this->assertEquals(100.0, (float) $liq->renta_calculada);
        $this->assertEquals(100.0, (float) $liq->renta_resultante_pagar);

        // 5. PLAME: EsSalud 9% de 2000 = 180, ONP = 260
        $this->assertEquals(180.0, (float) $liq->plame_essalud_a_pagar);
        $this->assertEquals(260.0, (float) $liq->plame_onp_a_pagar);
    }

    public function test_arrastre_interanual_desde_diciembre_anterior_hacia_enero(): void
    {
        // Guardar liquidación en Diciembre 2025 con saldo a favor de 1,200 y retenciones de 300
        $opDic = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '12-2025',
        ]);

        LiquidacionPeriodo::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'operacion_id' => $opDic->id,
            'periodo' => '12-2025',
            'ventas_base' => 0,
            'igv_ventas' => 0,
            'compras_base' => 6666.67,
            'igv_compras' => 1200,
            'igv_saldo_favor_siguiente' => 1200,
            'igv_retenciones_saldo_siguiente' => 300,
            'igv_resultante_pagar' => 0,
            'renta_base_ingresos' => 0,
            'renta_calculada' => 0,
            'renta_resultante_pagar' => 0,
        ]);

        $service = app(CalculadorLiquidacionService::class);
        $saldosEnero = $service->obtenerSaldosAnteriores($this->cliente, '01-2026');

        $this->assertEquals(1200.0, $saldosEnero['igv_saldo_favor_anterior']);
        $this->assertEquals(300.0, $saldosEnero['igv_retenciones_saldo_anterior']);
    }

    public function test_guardar_liquidacion_genera_deudas_y_propaga_cascada(): void
    {
        // Guardar Enero 2026 con saldo a favor
        $responseEne = $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 20000,
            'igv_compras' => 3600, // neto = -1800
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,
            'plame_essalud_base' => 1000, // essalud = piso legal 101.70 (RMV 2026 = S/ 1,130)
            'actualizar_deudas' => true,
        ]);

        $responseEne->assertOk();
        $this->assertDatabaseHas('liquidaciones_periodo', [
            'periodo' => '01-2026',
            'igv_saldo_favor_siguiente' => 1800.0,
            'igv_resultante_pagar' => 0,
            'renta_resultante_pagar' => 100.0,
            'plame_essalud_a_pagar' => 101.70,
        ]);

        // Verificar deudas generadas
        $this->assertDatabaseHas('deuda_tributarias', [
            'tributo_codigo' => '3121', // Renta MYPE
            'monto_deuda_inicial' => 100.0,
        ]);
        $this->assertDatabaseHas('deuda_tributarias', [
            'tributo_codigo' => '5210', // EsSalud (piso legal RMV 2026)
            'monto_deuda_inicial' => 101.70,
        ]);
        // IGV no debe tener deuda porque es saldo a favor
        $this->assertDatabaseMissing('deuda_tributarias', [
            'tributo_codigo' => '1011',
        ]);

        // Ahora guardar Febrero 2026: Ventas 15,000 (IGV 2,700), Compras 0
        // Debe arrastrar saldo anterior de 1,800, resultando en IGV a pagar = 2,700 - 1,800 = 900
        $responseFeb = $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '02-2026',
            'ventas_base' => 15000,
            'igv_ventas' => 2700,
            'compras_base' => 0,
            'igv_compras' => 0,
            'igv_saldo_favor_anterior' => 1800,
            'renta_base_ingresos' => 15000,
            'renta_tasa' => 1.0,
            'actualizar_deudas' => true,
        ]);

        $responseFeb->assertOk();
        $this->assertDatabaseHas('liquidaciones_periodo', [
            'periodo' => '02-2026',
            'igv_saldo_favor_anterior' => 1800.0,
            'igv_resultante_pagar' => 900.0,
            'igv_saldo_favor_siguiente' => 0,
        ]);

        // Ahora modificar Enero: compras aumentan a 25,000 (IGV 4,500), neto Enero = -2700
        $responseEneUpdate = $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 25000,
            'igv_compras' => 4500, // neto = -2700
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,
            'actualizar_deudas' => true,
        ]);

        $responseEneUpdate->assertOk();

        // Verificar que Febrero se actualizó EN CASCADA automáticamente:
        // En Febrero: saldo anterior ahora es 2,700, y el IGV resultante a pagar pasa a ser 0 (2700 - 2700 = 0)
        $liqFeb = LiquidacionPeriodo::where('cliente_id', $this->cliente->id)
            ->where('periodo', '02-2026')
            ->first();

        $this->assertEquals(2700.0, (float) $liqFeb->igv_saldo_favor_anterior);
        $this->assertEquals(0.0, (float) $liqFeb->igv_resultante_pagar);
    }

    public function test_limpiar_liquidacion_elimina_deudas_y_recalcula_cascada(): void
    {
        // 1. Crear liquidación con deuda en Enero y liquidación en Febrero
        $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 20000,
            'igv_compras' => 3600, // saldo favor = 1800
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,
            'actualizar_deudas' => true,
        ])->assertOk();

        $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '02-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 0,
            'igv_compras' => 0,
            'igv_saldo_favor_anterior' => 1800, // Resultante = 0
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,
            'actualizar_deudas' => true,
        ])->assertOk();

        // 2. Limpiar Enero
        $responseLimpiar = $this->postJson(route('control.liquidacion.limpiar'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
        ]);

        $responseLimpiar->assertOk();
        $this->assertDatabaseMissing('liquidaciones_periodo', [
            'periodo' => '01-2026',
        ]);

        // Febrero debe haber recalculado en cascada su saldo anterior a 0
        $liqFeb = LiquidacionPeriodo::where('cliente_id', $this->cliente->id)
            ->where('periodo', '02-2026')
            ->first();

        $this->assertEquals(0.0, (float) $liqFeb->igv_saldo_favor_anterior);
        // Al tener saldo anterior 0, ahora IGV resultante de Febrero es 1800
        $this->assertEquals(1800.0, (float) $liqFeb->igv_resultante_pagar);
    }

    public function test_compensacion_itan_y_arrastre_a_mes_siguiente(): void
    {
        // 1. Marzo: ITAN disponible 1200, compensamos voluntariamente solo 300 de un impuesto de 500
        $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '03-2026',
            'ventas_base' => 50000,
            'igv_ventas' => 9000,
            'compras_base' => 50000,
            'igv_compras' => 9000,
            'renta_base_ingresos' => 50000,
            'renta_tasa' => 1.0, // Renta calc = 500
            'renta_itan_saldo_anterior' => 1200, // ITAN pagado / disponible
            'renta_compensacion_itan' => 300,    // Compensación voluntaria, casilla 328
            'actualizar_deudas' => true,
        ])->assertOk();

        $liqMar = LiquidacionPeriodo::where('cliente_id', $this->cliente->id)
            ->where('periodo', '03-2026')
            ->first();

        $this->assertNotNull($liqMar);
        $this->assertEquals(200.0, (float) $liqMar->renta_resultante_pagar);
        $this->assertEquals(900.0, (float) $liqMar->renta_itan_saldo_siguiente);

        // 2. Abril: Consultar saldos anteriores para Abril
        $service = app(CalculadorLiquidacionService::class);
        $saldosAbril = $service->obtenerSaldosAnteriores($this->cliente, '04-2026');

        $this->assertEquals(900.0, (float) $saldosAbril['renta_itan_saldo_anterior']);
    }

    public function test_limpiar_liquidacion_elimina_hoja_deudas_y_abonos_asociados(): void
    {
        // 1. Crear liquidación con deuda de IGV y Renta
        $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '05-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'compras_base' => 5000,
            'igv_compras' => 900, // Resultante IGV = 900
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1.0,  // Resultante Renta = 100
            'actualizar_deudas' => true,
        ])->assertOk();

        $op = Operacion::where('cliente_id', $this->cliente->id)->where('periodo', '05-2026')->first();
        $this->assertNotNull($op);

        $deudaIgv = $op->deudasTributarias()->where('tributo_codigo', '1011')->first();
        $this->assertNotNull($deudaIgv);

        // 2. Registrar un pago (abono) en esa deuda
        $abono = $deudaIgv->abonos()->create([
            'operacion_id' => $op->id,
            'cliente_id' => $this->cliente->id,
            'tributo_codigo' => '1011',
            'monto' => 450.0,
            'fecha_pago' => '2026-06-10',
            'medio_pago' => '001',
            'observacion' => 'Pago parcial de IGV',
        ]);
        $this->assertDatabaseHas('abonos_deudas', ['id' => $abono->id]);

        // 3. Limpiar la liquidación del PDT 621
        $responseLimpiar = $this->postJson(route('control.liquidacion.limpiar'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '05-2026',
            'obligacion' => 'pdt_621',
        ]);

        $responseLimpiar->assertOk();

        // 4. Verificar que la deuda de IGV y el abono se hayan eliminado en cascada
        $this->assertDatabaseMissing('deuda_tributarias', ['id' => $deudaIgv->id]);
        $this->assertDatabaseMissing('abonos_deudas', ['id' => $abono->id]);
    }
}
