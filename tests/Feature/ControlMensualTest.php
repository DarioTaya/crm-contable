<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Operacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ControlMensualTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_mensual_renders_active_clients_matrix_and_annual_kpis(): void
    {
        $activo = Cliente::create([
            'razon_social' => 'EMPRESA MATRIZ ACTIVA SAC',
            'ruc' => '20777888999',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $inactivo = Cliente::create([
            'razon_social' => 'EMPRESA MATRIZ INACTIVA SAC',
            'ruc' => '20777888000',
            'regimen_tributario' => 'General',
            'estado' => false,
        ]);

        $periodo = now()->format('m-Y');

        Operacion::create([
            'cliente_id' => $activo->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Procesando',
            'estado_ple' => 'Pendiente',
            'notas' => 'Factura pendiente de envío por cliente',
        ]);

        $response = $this->get(route('control.index'));

        $response->assertStatus(200);
        $response->assertViewIs('control.index');
        $response->assertSee('EMPRESA MATRIZ ACTIVA SAC');
        $response->assertDontSee('EMPRESA MATRIZ INACTIVA SAC');
        $response->assertSee('Factura pendiente de envío por cliente');
        $response->assertSee('Control de Impuestos');
        $response->assertViewHas('anio', (int) now()->year);
        $response->assertViewHas('totalClientes', 1);
        // Unified: 1 client × 12 months = 12 total obligations
        $response->assertViewHas('totalObligacionesAnio', 12);
        // estadoMaestro for the month with data: SIRE=Declarado, PDT=Procesando, PLE=Pendiente → 'Procesando'
        // The other 11 months have no data → 'Pendiente'
        $response->assertViewHas('declaradasTotal', 0);
        $response->assertViewHas('procesandoTotal', 1);
        $response->assertViewHas('pendientesTotal', 11);
    }

    public function test_control_mensual_filters_by_annual_parameter(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE HISTORICO 2025 SAC',
            'ruc' => '20555666777',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);

        Operacion::create([
            'cliente_id' => $cliente->id,
            'periodo' => '12-2025',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'notas' => 'Cierre Anual 2025 OK',
        ]);

        // Consulta pasando parámetro anio=2025
        $response = $this->get(route('control.index', ['anio' => 2025]));

        $response->assertStatus(200);
        $response->assertViewHas('anio', 2025);
        $response->assertSee('Control de Impuestos 2025');
        $response->assertSee('Cierre Anual 2025 OK');
    }

    public function test_control_mensual_update_stores_batch_row_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE BATCH SAC',
            'ruc' => '20444555666',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $meses = [];
        for ($m = 1; $m <= 12; $m++) {
            $meses[] = [
                'periodo' => sprintf('%02d-2026', $m),
                'sire' => 'Declarado',
                'pdt' => 'Declarado',
                'ple' => 'Declarado',
            ];
        }

        $payload = [
            'cliente_id' => $cliente->id,
            'anio' => 2026,
            'meses' => $meses,
            'notas' => 'DEBE RH 082026',
        ];

        $response = $this->postJson(route('control.update'), $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'kpis' => ['totalClientes', 'declaradasTotal', 'procesandoTotal', 'pendientesTotal', 'porcentajeAvanceAnual'],
        ]);
        $response->assertJson([
            'success' => true,
            'message' => 'Fila anual guardada exitosamente.',
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'notas' => 'DEBE RH 082026',
        ]);
    }

    public function test_control_mensual_update_stores_operation_and_notas_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE AJAX SAC',
            'ruc' => '20111222333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $payload = [
            'cliente_id' => $cliente->id,
            'periodo' => '09-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'notas' => 'Canceló honorarios en su totalidad',
        ];

        $response = $this->postJson(route('control.update'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Operación guardada exitosamente.',
            'operacion' => [
                'cliente_id' => $cliente->id,
                'periodo' => '09-2026',
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
                'notas' => 'Canceló honorarios en su totalidad',
            ],
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '09-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'notas' => 'Canceló honorarios en su totalidad',
        ]);
    }

    public function test_control_mensual_update_validates_input(): void
    {
        $response = $this->postJson(route('control.update'), [
            'cliente_id' => 99999, // Inexistente
            'periodo' => 'invalido',
            'estado_compras_ventas' => 'NoExiste',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['cliente_id', 'periodo', 'estado_compras_ventas']);
    }

    public function test_control_mensual_update_master_switch_updates_all_three_obligations(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE MASTER SWITCH SAC',
            'ruc' => '20999888777',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => '04-2026',
            'estado' => 'Declarado',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Operación actualizada exitosamente.',
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '04-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
        ]);
    }

    public function test_control_mensual_update_floating_note_saves_for_specific_month(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE NOTA FLOTANTE SAC',
            'ruc' => '20123123123',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);

        $response = $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => '07-2026',
            'notas' => 'Debe recibo por honorarios mes 07',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Operación actualizada exitosamente.',
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '07-2026',
            'notas' => 'Debe recibo por honorarios mes 07',
        ]);
    }

    public function test_control_mensual_update_honorarios_payment_status_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE HONORARIOS SAC',
            'ruc' => '20999111222',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => '08-2026',
            'honorarios' => 'Pagado',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Operación actualizada exitosamente.',
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '08-2026',
            'estado_pago_honorarios' => 'Pagado',
        ]);
    }

    public function test_client_numbering_is_synchronized_between_clientes_index_and_control_index(): void
    {
        $clienteB = Cliente::create([
            'razon_social' => 'BETA SERVICIOS SAC',
            'ruc' => '20111222334',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $clienteA = Cliente::create([
            'razon_social' => 'ALFA LOGISTICA SAC',
            'ruc' => '20111222335',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        // BETA SERVICIOS fue creado primero (N° 1) y ALFA LOGISTICA después (N° 2)
        $this->assertEquals(1, $clienteB->numero);
        $this->assertEquals(2, $clienteA->numero);

        $responseClientes = $this->get(route('clientes.index'));
        $responseClientes->assertStatus(200);
        $responseClientes->assertSee('N°');
        $responseClientes->assertSee('Cliente N° 1');
        $responseClientes->assertSee('Cliente N° 2');

        // La misma numeración N° 1 para BETA y N° 2 para ALFA en Control de Impuestos
        $responseControl = $this->get(route('control.index'));
        $responseControl->assertStatus(200);
        $responseControl->assertSee('Cliente N° 1');
        $responseControl->assertSee('Cliente N° 2');
        $matriz = $responseControl->viewData('matrizClientes');
        $this->assertEquals(1, $matriz[0]['numero']);
        $this->assertEquals($clienteB->id, $matriz[0]['cliente']->id);
        $this->assertEquals(2, $matriz[1]['numero']);
        $this->assertEquals($clienteA->id, $matriz[1]['cliente']->id);
    }

    public function test_control_update_returns_correct_unified_kpis(): void
    {
        $clienteA = Cliente::create([
            'razon_social' => 'CLIENTE A KPI SAC',
            'ruc' => '20111333444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'CLIENTE B KPI SAC',
            'ruc' => '20111333555',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        // Declare all 12 months for client A (all 3 sub-states = 'Declarado' → maestro = 'Declarado')
        for ($m = 1; $m <= 12; $m++) {
            Operacion::create([
                'cliente_id' => $clienteA->id,
                'periodo' => sprintf('%02d-2026', $m),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }

        // Client B has no operations (all 12 months → 'Pendiente')
        // Update client B's month 01 to 'Declarado' via AJAX
        $response = $this->postJson(route('control.update'), [
            'cliente_id' => $clienteB->id,
            'periodo' => '01-2026',
            'estado' => 'Declarado',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('kpis.totalClientes', 2);
        $response->assertJsonPath('kpis.totalObligacionesAnio', 24); // 2 clients × 12 months
        $response->assertJsonPath('kpis.declaradasTotal', 13); // 12 (client A) + 1 (client B month 01)
        $response->assertJsonPath('kpis.pendientesTotal', 11); // 11 remaining months for client B
        $response->assertJsonPath('kpis.procesandoTotal', 0);
    }
}
