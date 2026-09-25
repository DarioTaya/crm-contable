<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\Operacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlDeudasTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Estudio $estudio;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Contable Test',
            'ruc' => '20123456789',
        ]);

        $this->user = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->actingAs($this->user);

        $this->cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CLIENTE DEUDAS SAC',
            'ruc' => '20555666777',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
    }

    public function test_operacion_computes_saldo_pendiente_and_total_abonado(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 1500.00,
            'vencimiento_deuda' => '2026-09-30',
        ]);

        $this->assertEquals(1500.00, $operacion->saldo_pendiente);
        $this->assertEquals(0.00, $operacion->total_abonado);

        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 500.00,
            'fecha_pago' => '2026-09-15',
            'observacion' => 'Primer abono',
        ]);

        $operacion->refresh();
        $this->assertEquals(1000.00, $operacion->saldo_pendiente);
        $this->assertEquals(500.00, $operacion->total_abonado);

        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 1000.00,
            'fecha_pago' => '2026-09-20',
            'observacion' => 'Pago final',
        ]);

        $operacion->refresh();
        $this->assertEquals(0.00, $operacion->saldo_pendiente);
        $this->assertEquals(1500.00, $operacion->total_abonado);
    }

    public function test_control_deudas_dashboard_renders_with_summary_cards_and_table(): void
    {
        $operacionConDeuda = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 2000.00,
            'vencimiento_deuda' => '2026-10-15',
        ]);

        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacionConDeuda->id,
            'monto' => 600.00,
            'fecha_pago' => '2026-09-10',
        ]);

        $response = $this->get(route('control_deudas.index'));

        $response->assertStatus(200);
        $response->assertViewIs('deudas.index');
        $response->assertSee('CLIENTE DEUDAS SAC');
        $response->assertSee('08-2026');
        $response->assertSee('2,000.00'); // Deuda original
        $response->assertSee('600.00');   // Total abonado
        $response->assertSee('1,400.00'); // Saldo pendiente
        $response->assertSee('Control de Deudas');
    }

    public function test_abonos_store_and_destroy_via_ajax(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 1000.00,
        ]);

        // Registrar abono
        $response = $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $operacion->id,
            'monto' => 400.00,
            'fecha_pago' => '2026-09-24',
            'observacion' => 'Abono parcial cuota 1',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_abonado' => 400.00,
                'saldo_pendiente' => 600.00,
            ]);

        $this->assertDatabaseHas('abonos_deudas', [
            'operacion_id' => $operacion->id,
            'monto' => 400.00,
        ]);

        $abonoId = $response->json('abono.id');

        // Eliminar abono
        $deleteResponse = $this->deleteJson(route('abonos_deudas.destroy', $abonoId));

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_abonado' => 0.00,
                'saldo_pendiente' => 1000.00,
            ]);

        $this->assertDatabaseMissing('abonos_deudas', [
            'id' => $abonoId,
        ]);
    }

    public function test_control_get_deuda_returns_json_details(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 850.50,
            'vencimiento_deuda' => '2026-10-20',
        ]);

        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 200.00,
            'fecha_pago' => '2026-09-18',
            'observacion' => 'Transferencia BCP',
        ]);

        $response = $this->postJson(route('control.deuda'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'tiene_deuda' => true,
                'monto_deuda_inicial' => 850.50,
                'vencimiento_deuda' => '2026-10-20',
                'total_abonado' => 200.00,
                'saldo_pendiente' => 650.50,
            ])
            ->assertJsonCount(1, 'abonos');
    }

    public function test_control_deudas_view_includes_client_code_tributo_and_export_buttons(): void
    {
        $this->cliente->update(['numero' => 42]);

        Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 1250.00,
            'vencimiento_deuda' => '2026-10-25',
            'tributo_codigo' => '1011',
        ]);

        $response = $this->get(route('control_deudas.index'));

        $response->assertStatus(200);
        $response->assertSee('42');
        $response->assertSee('1011 -');
        $response->assertSee('IGV 18%');
        $response->assertSee('Exportar a PDF');
        $response->assertSee('Imprimir Reporte');
        $response->assertSee('Filtrar Clientes');
        $response->assertViewHas('clientesConDeuda');
        $response->assertViewHas('deudasData');
    }

    public function test_abono_sanitizes_commas_in_monto(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 5000.00,
            'vencimiento_deuda' => '2026-10-25',
        ]);

        $response = $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $operacion->id,
            'monto' => '1,250.50',
            'fecha_pago' => '2026-09-24',
            'observacion' => 'Abono con formato de miles',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'total_abonado' => 1250.50,
                'saldo_pendiente' => 3749.50,
            ]);

        $this->assertDatabaseHas('abonos_deudas', [
            'operacion_id' => $operacion->id,
            'monto' => 1250.50,
        ]);
    }

    public function test_calcular_igv_justo_calculates_correct_official_date(): void
    {
        $response = $this->postJson(route('control.calcular_igv_justo'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'periodo_origen' => '08-2026',
                'periodo_destino' => '11-2026',
            ]);

        $this->assertNotNull($response->json('fecha_vencimiento'));
        $this->assertNotNull($response->json('fecha_vencimiento_dmy'));
    }

    public function test_control_deudas_ajax_filter_returns_json_and_recalculates_totals(): void
    {
        Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 2000.00,
            'vencimiento_deuda' => '2026-10-25',
            'tributo_codigo' => '1011',
        ]);

        $response = $this->getJson(route('control_deudas.index', [
            'ajax' => 1,
            'clientes' => [$this->cliente->id],
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'deudas',
                'total_deuda',
                'total_recaudado',
                'total_saldo',
                'count',
            ])
            ->assertJson([
                'success' => true,
                'total_deuda' => 2000.00,
                'total_saldo' => 2000.00,
                'count' => 1,
            ]);
    }

    public function test_closing_with_the_debt_switch_off_deletes_the_debt_and_its_payments(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'monto_deuda_inicial' => 850.50,
            'vencimiento_deuda' => '2026-10-20',
            'tributo_codigo' => '1011',
            'es_igv_justo' => false,
        ]);

        $abono = AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 200.00,
            'fecha_pago' => '2026-09-18',
        ]);

        $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => false,
        ])->assertOk();

        $operacion->refresh();
        $this->assertFalse($operacion->tiene_deuda);
        $this->assertEquals(850.50, (float) $operacion->monto_deuda_inicial);
        $this->assertDatabaseHas('abonos_deudas', ['id' => $abono->id]);

        $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => false,
            'descartar_deuda' => true,
        ])->assertOk()
            ->assertJson([
                'tiene_deuda' => false,
                'monto_deuda_inicial' => null,
                'abonos' => [],
            ]);

        $operacion->refresh();
        $this->assertNull($operacion->monto_deuda_inicial);
        $this->assertNull($operacion->vencimiento_deuda);
        $this->assertDatabaseMissing('abonos_deudas', ['id' => $abono->id]);
    }

    public function test_un_mes_puede_registrar_varios_tributos_con_pagos_independientes(): void
    {
        $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 800,
            'vencimiento_deuda' => '2026-09-18',
        ])->assertOk();

        $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => '3121',
            'monto_deuda_inicial' => 200,
            'vencimiento_deuda' => '2026-09-18',
        ])->assertOk();

        $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => '5210',
            'monto_deuda_inicial' => 150,
            'vencimiento_deuda' => '2026-09-18',
        ])->assertOk()
            ->assertJsonPath('saldo_pendiente', 1150);

        $duplicado = $this->postJson(route('control.update'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '08-2026',
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 50,
            'vencimiento_deuda' => '2026-09-18',
        ]);
        $duplicado->assertStatus(422);

        $igv = DeudaTributaria::where('tributo_codigo', '1011')->first();
        $renta = DeudaTributaria::where('tributo_codigo', '3121')->first();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $igv->operacion_id,
            'deuda_tributaria_id' => $igv->id,
            'monto' => 300,
            'fecha_pago' => '2026-09-20',
        ])->assertOk()
            ->assertJsonPath('saldo_pendiente', 500);

        $renta->refresh();
        $this->assertEquals(200.0, (float) $renta->saldo_pendiente);

        $response = $this->get(route('control_deudas.index'));
        $response->assertOk();
        $response->assertSee('IGV 18%');
        $response->assertSee('Renta MYPE 1%');
        $response->assertSee('EsSalud');
        $response->assertSee('800.00');
        $response->assertSee('200.00');
        $response->assertSee('150.00');
    }

    public function test_historial_archivado_agrupa_por_anio_solo_los_tributos_pagados(): void
    {
        $archivada = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'ARCHIVADA SUNAT SAC',
            'ruc' => '20999111001',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
        $pendiente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'PENDIENTE DEUDA SAC',
            'ruc' => '20999111002',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
        $parcial = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'PARCIAL DEUDA SAC',
            'ruc' => '20999111003',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $this->guardarTributo($archivada->id, '08-2026', '1011', 800);
        $igv = DeudaTributaria::where('tributo_codigo', '1011')->whereHas('operacion', fn ($q) => $q->where('cliente_id', $archivada->id))->first();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $igv->operacion_id,
            'deuda_tributaria_id' => $igv->id,
            'monto' => 800,
            'fecha_pago' => '2026-09-20',
        ])->assertOk();

        $this->guardarTributo($archivada->id, '03-2025', '5210', 120);
        $essalud = DeudaTributaria::where('tributo_codigo', '5210')->first();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $essalud->operacion_id,
            'deuda_tributaria_id' => $essalud->id,
            'monto' => 120,
            'fecha_pago' => '2025-04-10',
        ])->assertOk();

        $this->guardarTributo($pendiente->id, '09-2026', '3121', 450);
        $this->guardarTributo($parcial->id, '07-2026', '3031', 300);
        $renta = DeudaTributaria::where('tributo_codigo', '3031')->first();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $renta->operacion_id,
            'deuda_tributaria_id' => $renta->id,
            'monto' => 100,
            'fecha_pago' => '2026-08-01',
        ])->assertOk();

        $vigentes = $this->get(route('control_deudas.index'));
        $vigentes->assertOk();
        $vigentes->assertSee('Historial archivado');
        $vigentes->assertSee('PENDIENTE DEUDA SAC');
        $vigentes->assertSee('PARCIAL DEUDA SAC');
        $vigentes->assertDontSee('ARCHIVADA SUNAT SAC');

        $archivo = $this->get(route('control_deudas.archivadas'));
        $archivo->assertOk();
        $archivo->assertSee('Historial archivado');
        $archivo->assertSee('2026');
        $archivo->assertSee('2025');
        $archivo->assertSee('Agosto');
        $archivo->assertSee('Marzo');
        $archivo->assertSee('ARCHIVADA SUNAT SAC');
        $archivo->assertSee('IGV 18%');
        $archivo->assertSee('EsSalud');
        $archivo->assertDontSee('PENDIENTE DEUDA SAC');
        $archivo->assertDontSee('PARCIAL DEUDA SAC');
        $archivo->assertSee('Deudas pendientes');
        $archivo->assertSee('Todos los ejercicios');
        $archivo->assertSee('Todos los meses');
        $archivo->assertSee('Todos los clientes');
        $archivo->assertSee('Todos los tributos');
        $archivo->assertSee('Deuda original');
        $archivo->assertSee('Vencimiento');
        $archivo->assertSee('08-2026');
        $archivo->assertSee('03-2025');
        $archivo->assertSee('18/09/2026');
        $archivo->assertSee('20/09/2026');
        $archivo->assertSee('10/04/2025');
        $archivo->assertSee('800.00');
        $archivo->assertSee('120.00');
        $archivo->assertSee('S/ 0.00');
        $archivo->assertSee('20999111001');
        $archivo->assertSee('IGV - OPER. INT. - CTA. PROPIA');
        $archivo->assertSee('ESSALUD SEG REGULAR TRABAJADOR');
        $archivo->assertSee('Ver pagos');
        $archivo->assertDontSee('20999111002');
        $archivo->assertDontSee('20999111003');
    }

    private function guardarTributo(int $clienteId, string $periodo, string $codigo, float $monto): void
    {
        $this->postJson(route('control.update'), [
            'cliente_id' => $clienteId,
            'periodo' => $periodo,
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => $codigo,
            'monto_deuda_inicial' => $monto,
            'vencimiento_deuda' => '2026-09-18',
        ])->assertOk();
    }
}
