<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\ControlBalance;
use App\Models\Estudio;
use App\Models\Operacion;
use App\Models\User;
use Database\Seeders\CronogramaSunatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        $response->assertDontSee('Centro de Comando');
        $response->assertDontSee('Salir');
        $response->assertSee('EMPRESA ACTIVA SAC');
        $response->assertSee('Dígito');
        $response->assertSee(route('clientes.index', ['estado' => 'activo']), false);
        $response->assertSee(route('clientes.create'), false);
        $response->assertSee(route('alertas.index', ['estado' => 'Pendiente']), false);
        $response->assertSee(route('control.index', ['anio' => (int) now()->format('Y')]), false);
        $response->assertDontSee('Obligaciones Pendientes');
        $response->assertSee('+ Nuevo Cliente');
        $response->assertSee('Suspendidos (0)');
        $response->assertSee('Retirados (1)');
        $response->assertSee('SUNAT (2)');
        $response->assertSee('SUNAFIL (0)');
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
        $response->assertDontSee('Salir');
    }

    public function test_dashboard_balance_advance_uses_the_three_month_ple_deadline(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $alDia = Cliente::create([
            'razon_social' => 'BALANCE AL DIA SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        ControlBalance::create([
            'cliente_id' => $alDia->id,
            'anio' => 2026,
            'meses' => ['6' => 'Declarado'],
        ]);

        Cliente::create([
            'razon_social' => 'BALANCE PENDIENTE SAC',
            'ruc' => '20222222222',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        Cliente::create([
            'razon_social' => 'RUS SIN BALANCE SAC',
            'ruc' => '10333333333',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee('Avance Impuestos');
            $response->assertSee('Avance Balances');
            $response->assertSee('Junio de 2026');
            $response->assertSee('30/09/2026');
            $response->assertSee('plazo PLE 3 meses');
            $response->assertSee(route('balances.index', ['anio' => 2026]));

            $avance = $response->viewData('avanceBalances');
            $this->assertSame(1, $avance['declarados']);
            $this->assertSame(2, $avance['total']);
            $this->assertSame('06-2026', $avance['periodo']);
        });
    }

    public function test_dashboard_greets_admin_by_first_name_in_the_morning(): void
    {
        $usuario = $this->usuarioDelEstudio('sebastian');

        $this->travelTo(Carbon::parse('2026-09-23 09:30:00', 'America/Lima'), function () use ($usuario) {
            $this->actingAs($usuario)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Buenos días, Sebastian')
                ->assertSee('miércoles, 23 de setiembre de 2026')
                ->assertSee('09:30 AM');
        });
    }

    public function test_dashboard_greets_assistant_by_first_name_in_the_afternoon(): void
    {
        $usuario = $this->usuarioDelEstudio('diego', 'asistente');

        $this->travelTo(Carbon::parse('2026-09-23 15:10:00', 'America/Lima'), function () use ($usuario) {
            $this->actingAs($usuario)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Buenas tardes, Diego')
                ->assertSee('03:10 PM');
        });
    }

    public function test_dashboard_greets_with_good_evening_after_seven(): void
    {
        $usuario = $this->usuarioDelEstudio('MARÍA LÓPEZ');

        $this->travelTo(Carbon::parse('2026-09-23 21:05:00', 'America/Lima'), function () use ($usuario) {
            $this->actingAs($usuario)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('Buenas noches, María');
        });
    }

    public function test_dashboard_lists_overdue_and_open_balance_alerts(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $vencido = Cliente::create([
            'razon_social' => 'BALANCE VENCIDO SAC',
            'ruc' => '20444444444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        ControlBalance::create([
            'cliente_id' => $vencido->id,
            'anio' => 2026,
            'meses' => [
                '1' => 'Declarado',
                '2' => 'Declarado',
                '3' => 'Declarado',
                '4' => 'Declarado',
                '6' => 'Declarado',
            ],
        ]);

        $enCurso = Cliente::create([
            'razon_social' => 'BALANCE DE JUNIO SAC',
            'ruc' => '20555555555',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        ControlBalance::create([
            'cliente_id' => $enCurso->id,
            'anio' => 2026,
            'meses' => [
                '1' => 'Declarado',
                '2' => 'Declarado',
                '3' => 'Declarado',
                '4' => 'Declarado',
                '5' => 'Declarado',
            ],
        ]);

        $completo = Cliente::create([
            'razon_social' => 'BALANCE COMPLETO SAC',
            'ruc' => '20666666666',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        ControlBalance::create([
            'cliente_id' => $completo->id,
            'anio' => 2026,
            'meses' => [
                '1' => 'Declarado',
                '2' => 'Declarado',
                '3' => 'Declarado',
                '4' => 'Declarado',
                '5' => 'Declarado',
                '6' => 'Declarado',
            ],
        ]);

        Cliente::create([
            'razon_social' => 'RUS SIN LIBROS SAC',
            'ruc' => '10777777777',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee('Alertas de Vencimiento de Balances');
            $response->assertSee('BALANCE VENCIDO SAC');
            $response->assertSee('BALANCE DE JUNIO SAC');
            $response->assertSee(route('balances.index', ['anio' => 2026]));

            $alertas = collect($response->viewData('alertasBalances'));
            $nombres = $alertas->map(fn (array $alerta): string => $alerta['cliente']->razon_social);

            $this->assertTrue($nombres->contains('BALANCE VENCIDO SAC'));
            $this->assertTrue($nombres->contains('BALANCE DE JUNIO SAC'));
            $this->assertFalse($nombres->contains('BALANCE COMPLETO SAC'));
            $this->assertFalse($nombres->contains('RUS SIN LIBROS SAC'));

            $alertaVencida = $alertas->first(fn (array $alerta): bool => $alerta['cliente']->razon_social === 'BALANCE VENCIDO SAC');
            $this->assertSame('vencido', $alertaVencida['estado_critico']['estado']);
            $this->assertSame([5], array_column($alertaVencida['meses'], 'mes'));

            $alertaJunio = $alertas->first(fn (array $alerta): bool => $alerta['cliente']->razon_social === 'BALANCE DE JUNIO SAC');
            $this->assertSame('al_dia', $alertaJunio['estado_critico']['estado']);
            $this->assertSame([6], array_column($alertaJunio['meses'], 'mes'));
        });
    }

    public function test_dashboard_groups_unpaid_fees_from_the_last_three_closed_months(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            $conDeuda = Cliente::create([
                'razon_social' => 'HONORARIOS DEBE SAC',
                'ruc' => '20111111111',
                'regimen_tributario' => 'General',
                'estado' => 'activo',
            ]);
            $alDia = Cliente::create([
                'razon_social' => 'HONORARIOS PAGADOS SAC',
                'ruc' => '20222222222',
                'regimen_tributario' => 'General',
                'estado' => 'activo',
            ]);
            Cliente::create([
                'razon_social' => 'HONORARIOS SUSPENDIDO SAC',
                'ruc' => '20333333333',
                'regimen_tributario' => 'General',
                'estado' => 'suspendido',
            ]);
            Cliente::create([
                'razon_social' => 'HONORARIOS BAJA AGOSTO SAC',
                'ruc' => '20444444444',
                'regimen_tributario' => 'General',
                'estado' => 'retirado',
                'mes_baja' => '2026-08',
            ]);

            foreach (['08-2026' => 'Pendiente', '07-2026' => 'Pendiente', '06-2026' => 'Pagado', '09-2026' => 'Pendiente'] as $periodo => $estado) {
                Operacion::create([
                    'cliente_id' => $conDeuda->id,
                    'periodo' => $periodo,
                    'estado_pago_honorarios' => $estado,
                ]);
            }

            foreach (['08-2026', '07-2026', '06-2026'] as $periodo) {
                Operacion::create([
                    'cliente_id' => $alDia->id,
                    'periodo' => $periodo,
                    'estado_pago_honorarios' => 'Pagado',
                ]);
            }

            $response = $this->get(route('dashboard'));

            $response->assertOk();
            $response->assertViewHas('honorariosAtrasados', [
                'total' => 7,
                'anio' => 2026,
                'meses' => [
                    ['etiqueta' => 'AGO-26', 'cantidad' => 2, 'periodo' => '08-2026'],
                    ['etiqueta' => 'JUL-26', 'cantidad' => 3, 'periodo' => '07-2026'],
                    ['etiqueta' => 'JUN-26', 'cantidad' => 2, 'periodo' => '06-2026'],
                ],
            ]);
            $response->assertSee('Control Honorarios');
            $response->assertSee('Pendientes de cobro');
            $response->assertSeeInOrder(['AGO-26 (2)', 'JUL-26 (3)', 'JUN-26 (2)']);
            $response->assertSee(route('control.index', ['anio' => 2026]), false);
            $response->assertSee('Ver control de honorarios', false);
            $response->assertDontSee('SET-26');
        });
    }

    private function usuarioDelEstudio(string $nombre, string $rol = 'admin'): User
    {
        $estudio = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Test', 'correo' => 'estudio@test.com', 'activo' => true]
        );

        return User::factory()->create([
            'estudio_id' => $estudio->id,
            'name' => $nombre,
            'rol' => $rol,
            'activo' => true,
        ]);
    }
}
