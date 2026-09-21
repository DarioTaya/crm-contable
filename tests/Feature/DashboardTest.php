<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Operacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_and_computes_kpis_accurately(): void
    {
        // 1. Clientes (1 activo, 1 inactivo)
        $activo = Cliente::create([
            'razon_social' => 'EMPRESA ACTIVA SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $inactivo = Cliente::create([
            'razon_social' => 'EMPRESA INACTIVA SAC',
            'ruc' => '20987654321',
            'regimen_tributario' => 'General',
            'estado' => false,
        ]);

        // 2. Alertas de Buzón (leídas y no leídas, normales y de alta prioridad)
        AlertaBuzon::create([
            'cliente_id' => $activo->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'RESOLUCION COACTIVA N 123',
            'fecha_recepcion' => '2026-09-15 10:00:00',
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
        ]);

        AlertaBuzon::create([
            'cliente_id' => $activo->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'COMUNICADO GENERAL SUNAT',
            'fecha_recepcion' => '2026-09-15 11:00:00',
            'prioridad' => 'Media',
            'estado' => 'Pendiente',
        ]);

        AlertaBuzon::create([
            'cliente_id' => $activo->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'MENSAJE YA LEIDO',
            'fecha_recepcion' => '2026-09-14 09:00:00',
            'prioridad' => 'Baja',
            'estado' => 'Leído',
        ]);

        // 3. Operación del mes actual
        $periodo = now()->format('m-Y');
        Operacion::create([
            'cliente_id' => $activo->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Procesando',
            'estado_ple' => 'Pendiente',
        ]);

        // Acceder a la ruta principal del Dashboard
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('dashboard');
        $response->assertViewHas('totalClientesActivos', 1);
        $response->assertViewHas('totalClientesInactivos', 1);
        $response->assertViewHas('notificacionesPendientes', 2);
        $response->assertViewHas('notificacionesImportantes', 1);
        $response->assertViewHas('declarados', 0);
        $response->assertViewHas('procesando', 1);
        $response->assertViewHas('pendientes', 0);
        $response->assertViewHas('totalObligaciones', 1);

        $response->assertSee('Centro de Comando');
        $response->assertSee('EMPRESA ACTIVA SAC');
        $response->assertSee('Dígito');
    }

    public function test_expiration_alerts_are_sorted_by_ruc_last_digit_according_to_sunat_schedule(): void
    {
        $periodo = now()->format('m-Y');

        // Cliente con dígito 8
        $c8 = Cliente::create([
            'razon_social' => 'CLIENTE OCHO SAC',
            'ruc' => '20000000008',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Operacion::create([
            'cliente_id' => $c8->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        // Cliente con dígito 0
        $c0 = Cliente::create([
            'razon_social' => 'CLIENTE CERO SAC',
            'ruc' => '20000000000',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Operacion::create([
            'cliente_id' => $c0->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        // Cliente con dígito 3
        $c3 = Cliente::create([
            'razon_social' => 'CLIENTE TRES SAC',
            'ruc' => '20000000003',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Operacion::create([
            'cliente_id' => $c3->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        // Cliente que ya declaró todo (no debe figurar en las alertas)
        $cAlDia = Cliente::create([
            'razon_social' => 'CLIENTE AL DIA SAC',
            'ruc' => '20000000005',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Operacion::create([
            'cliente_id' => $cAlDia->id,
            'periodo' => $periodo,
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
        ]);

        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);

        /** @var array $alertas */
        $alertas = $response->viewData('alertasVencimiento');

        // Solo deben estar los 3 clientes con pendientes
        $this->assertCount(3, $alertas);

        // Deben estar ordenados estrictamente por el último dígito del RUC (0, 3, 8)
        $this->assertEquals(0, $alertas[0]['ultimo_digito']);
        $this->assertEquals('CLIENTE CERO SAC', $alertas[0]['cliente']->razon_social);

        $this->assertEquals(3, $alertas[1]['ultimo_digito']);
        $this->assertEquals('CLIENTE TRES SAC', $alertas[1]['cliente']->razon_social);

        $this->assertEquals(8, $alertas[2]['ultimo_digito']);
        $this->assertEquals('CLIENTE OCHO SAC', $alertas[2]['cliente']->razon_social);
    }

    public function test_clientes_index_renders_quick_actions_toolbar_and_sidebar_is_clean(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'BOTONERA DEMO SAC',
            'ruc' => '20999888777',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->get('/clientes');

        $response->assertStatus(200);
        $response->assertViewIs('clientes.index');

        // Botonera de acciones rápidas
        $response->assertSee('Ver');
        $response->assertSee('SUNAT');
        $response->assertSee('SUNAFIL');
        $response->assertSee(route('clientes.show', $cliente));
        $response->assertSee(route('clientes.sunat', $cliente));
        $response->assertSee(route('clientes.sunafil', $cliente));

        // Sidebar limpio: no debe contener los enlaces anteriores de Notificaciones ni Operaciones
        $response->assertDontSee('Notificaciones');
        $response->assertDontSee('Operaciones');
    }
}
