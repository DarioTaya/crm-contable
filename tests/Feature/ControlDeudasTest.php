<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
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
}
