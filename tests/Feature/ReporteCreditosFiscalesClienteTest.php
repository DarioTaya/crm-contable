<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReporteCreditosFiscalesClienteTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Contable Fiscal Senior SAC',
            'ruc' => '20600000001',
        ]);

        $this->usuario = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->actingAs($this->usuario);
    }

    public function test_la_vista_de_creditos_fiscales_y_detracciones_carga_correctamente(): void
    {
        $cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'COMERCIALIZADORA LOS ANDES SAC',
            'ruc' => '20555666777',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $response = $this->get(route('clientes.creditos', $cliente));

        $response->assertOk();
        $response->assertSee('Control de Detracciones, Retenciones y Percepciones');
        $response->assertSee('Pagos con Detracciones');
        $response->assertSee('Retenciones IGV (3%)');
        $response->assertSee('Percepciones IGV');
        $response->assertSee('Crédito Fiscal & ITAN', false);
        $response->assertSee('data-seccion="creditos"', false);
    }

    public function test_calcula_kpis_y_lineas_mensuales_de_creditos_y_detracciones(): void
    {
        $cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CONSTRUCTORA DEL SUR SAC',
            'ruc' => '20888999111',
            'regimen_tributario' => 'GENERAL',
            'estado' => 'activo',
        ]);

        // Registrar operación con liquidación en Enero 2026
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'tiene_deuda' => true,
        ]);

        LiquidacionPeriodo::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $cliente->id,
            'operacion_id' => $operacion->id,
            'periodo' => '01-2026',
            'igv_saldo_favor_anterior' => 1500,
            'igv_saldo_favor_siguiente' => 500,
            'igv_retenciones_saldo_anterior' => 800,
            'igv_retenciones' => 3000,
            'igv_retenciones_saldo_siguiente' => 1800,
            'igv_percepciones_saldo_anterior' => 200,
            'igv_percepciones' => 1200,
            'igv_percepciones_saldo_siguiente' => 400,
        ]);

        // Registrar un pago con cargo a cuenta de detracciones del Banco de la Nación
        $deuda = DeudaTributaria::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 2500,
            'saldo_pendiente' => 1000,
            'vencimiento_deuda' => '2026-02-18',
        ]);

        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 1500,
            'fecha_pago' => '2026-02-15',
            'medio_pago' => 'detracciones',
            'observacion' => 'Pago IGV con fondos Banco de la Nación SPOT',
        ]);

        $response = $this->get(route('clientes.creditos', $cliente));
        $response->assertOk();

        $reporte = $response->viewData('reporteCreditosFiscales');
        $this->assertNotNull($reporte);

        $kpis = $reporte['kpis'];
        $this->assertEquals(1500.0, $kpis['detracciones_total_pagado']);
        $this->assertEquals(1, $kpis['detracciones_conteo_pagos']);
        $this->assertEquals(3000.0, $kpis['retenciones_acumuladas']);
        $this->assertEquals(2000.0, $kpis['retenciones_aplicadas']);
        $this->assertEquals(1800.0, $kpis['retenciones_saldo_disponible']);
        $this->assertEquals(1200.0, $kpis['percepciones_acumuladas']);
        $this->assertEquals(1000.0, $kpis['percepciones_aplicadas']);
        $this->assertEquals(400.0, $kpis['percepciones_saldo_disponible']);
        $this->assertEquals(500.0, $kpis['credito_fiscal_vigente']);

        // Verificar que los datos aparecen en la vista HTML
        $response->assertSee('1,500.00');
        $response->assertSee('1,800.00');
        $response->assertSee('400.00');
        $response->assertSee('500.00');
    }
}
