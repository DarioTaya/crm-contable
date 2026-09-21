<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AlertaBuzonTest extends TestCase
{
    use RefreshDatabase;

    public function test_alerta_buzon_model_creation_and_relationship_with_cliente(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA ALERTA MODEL SAC',
            'ruc' => '20111444777',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $alerta = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'NOTIFICACION VALOR COACTIVO',
            'contenido' => 'Se notifica el inicio de cobranza coactiva por tributo insoluto.',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
        ]);

        $this->assertDatabaseHas('alertas_buzon', [
            'id' => $alerta->id,
            'asunto' => 'NOTIFICACION VALOR COACTIVO',
            'plataforma' => 'SUNAT',
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
        ]);

        $this->assertTrue($cliente->alertas->contains($alerta));
        $this->assertEquals($cliente->id, $alerta->cliente->id);
        $this->assertTrue($alerta->esPendiente());
        $this->assertTrue($alerta->esAltaPrioridad());
    }

    public function test_alertas_index_renders_inbox_and_filters_correctly(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE INBOX TEST SAC',
            'ruc' => '20555666777',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $alertaSunat = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'RESOLUCION DE DETERMINACION SUNAT',
            'contenido' => 'Cuerpo detallado de SUNAT',
            'fecha_recepcion' => now()->subDay(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
        ]);

        $alertaSunafil = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'REQUERIMIENTO INSPECTIVO SUNAFIL',
            'contenido' => 'Cuerpo detallado de SUNAFIL',
            'fecha_recepcion' => now(),
            'estado' => 'Leído',
            'prioridad' => 'Media',
        ]);

        // 1. Vista global sin filtros
        $response = $this->get(route('alertas.index'));
        $response->assertStatus(200);
        $response->assertViewIs('alertas.index');
        $response->assertSee('CLIENTE INBOX TEST SAC');
        $response->assertSee('RESOLUCION DE DETERMINACION SUNAT');
        $response->assertSee('REQUERIMIENTO INSPECTIVO SUNAFIL');
        $response->assertViewHas('totalAlertas', 2);
        $response->assertViewHas('totalPendientes', 1);

        // 2. Filtro por plataforma SUNAT
        $resSunat = $this->get(route('alertas.index', ['plataforma' => 'SUNAT']));
        $resSunat->assertStatus(200);
        $resSunat->assertSee('RESOLUCION DE DETERMINACION SUNAT');
        $resSunat->assertDontSee('REQUERIMIENTO INSPECTIVO SUNAFIL');

        // 3. Filtro por estado Pendiente
        $resPend = $this->get(route('alertas.index', ['estado' => 'Pendiente']));
        $resPend->assertStatus(200);
        $resPend->assertSee('RESOLUCION DE DETERMINACION SUNAT');
        $resPend->assertDontSee('REQUERIMIENTO INSPECTIVO SUNAFIL');
    }

    public function test_mark_as_read_updates_status_via_ajax_and_returns_pending_count(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA AJAX READ SAC',
            'ruc' => '20999000111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $alerta1 = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'ORDEN DE PAGO N 1',
            'contenido' => 'Contenido 1',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Media',
        ]);

        $alerta2 = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'CARTA INDUCTIVA N 2',
            'contenido' => 'Contenido 2',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Baja',
        ]);

        $this->assertEquals(2, AlertaBuzon::where('estado', 'Pendiente')->count());

        // Marcar alerta1 como leída
        $response = $this->postJson(route('alertas.mark-as-read', $alerta1));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Alerta marcada como leída exitosamente.',
            'pendientesCount' => 1,
        ]);

        $this->assertEquals('Leído', $alerta1->fresh()->estado);
        $this->assertEquals('Pendiente', $alerta2->fresh()->estado);
        $this->assertEquals(1, AlertaBuzon::where('estado', 'Pendiente')->count());
    }

    public function test_expediente_digital_renders_alertas_in_notificaciones_tab(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE EXPEDIENTE BUZON SAC',
            'ruc' => '20777111222',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'CARTA INDUCTIVA DE OMISOS 2026',
            'contenido' => 'Cuerpo detallado de la carta inductiva del cliente.',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
        ]);

        $response = $this->get(route('clientes.show', ['cliente' => $cliente, 'tab' => 'notificaciones']));

        $response->assertStatus(200);
        $response->assertSee('Buzón Electrónico SUNAT / SUNAFIL');
        $response->assertSee('CARTA INDUCTIVA DE OMISOS 2026');
        $response->assertSee('SUNAT');
    }

    public function test_dashboard_buzon_sol_counts_real_pending_alertas_buzon(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE DASHBOARD COUNT SAC',
            'ruc' => '20333222111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        // 3 alertas pendientes
        for ($i = 1; $i <= 3; $i++) {
            AlertaBuzon::create([
                'cliente_id' => $cliente->id,
                'plataforma' => 'SUNAT',
                'asunto' => "ALERTA PENDIENTE {$i}",
                'fecha_recepcion' => now(),
                'estado' => 'Pendiente',
                'prioridad' => $i === 1 ? 'Alta' : 'Media',
            ]);
        }

        // 1 alerta ya leída
        AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'ALERTA YA LEIDA',
            'fecha_recepcion' => now(),
            'estado' => 'Leído',
            'prioridad' => 'Baja',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('notificacionesPendientes', 3);
        $response->assertViewHas('notificacionesImportantes', 1);
    }
}
