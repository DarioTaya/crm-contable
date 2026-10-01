<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\ControlBalance;
use App\Models\CronogramaSunat;
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

        // 3. Meses anteriores ya declarados; el mes en curso sigue abierto
        $this->declararMesesAnteriores($activo);

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
        $response->assertSee('+ Registrar cliente nuevo');
        $response->assertDontSee('+ Nuevo Cliente');
        $response->assertSee('Suspendidos (0)');
        $response->assertSee('Retirados (1)');
        $response->assertSee('Pendientes (0)');
        $response->assertSee('En Proceso (1)');
        $response->assertSee('SUNAT (2)');
        $response->assertSee('SUNAFIL (0)');
        $response->assertSee('PDT 601');
        $this->assertSame(
            ['PDT 621', 'PDT 601'],
            collect($response->viewData('alertasVencimiento')[0]['faltantes'])->pluck('nombre')->all()
        );
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
        $this->declararMesesAnteriores($cAlDia);

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

    public function test_dashboard_tax_alerts_list_open_months_and_link_to_tax_control(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $pendiente = Cliente::create([
            'razon_social' => 'MESES PENDIENTES SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        foreach ([1, 3, 4, 5, 6, 7] as $mes) {
            Operacion::create([
                'cliente_id' => $pendiente->id,
                'periodo' => sprintf('%02d-2026', $mes),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }

        Operacion::create([
            'cliente_id' => $pendiente->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Procesando',
            'estado_ple' => 'Declarado',
        ]);

        $alDia = Cliente::create([
            'razon_social' => 'IMPUESTOS AL DIA SAC',
            'ruc' => '20222222222',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        foreach (range(1, 8) as $mes) {
            Operacion::create([
                'cliente_id' => $alDia->id,
                'periodo' => sprintf('%02d-2026', $mes),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }

        Operacion::create([
            'cliente_id' => $alDia->id,
            'periodo' => '09-2026',
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        $this->travelTo(Carbon::parse('2026-09-29 10:00:00', 'America/Lima'), function () use ($pendiente) {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee('MESES PENDIENTES SAC');
            $response->assertSee('Febrero 2026');
            $response->assertSee('Agosto 2026 (En proceso)');
            $response->assertSee('Falta:');
            $response->assertSee('SIRE');
            $response->assertSee('PDT 621');
            $response->assertSee('PDT 601');
            $response->assertSee(route('control.index', ['anio' => 2026]), false);
            $response->assertDontSee(route('clientes.operaciones', $pendiente), false);
            $response->assertDontSee('IMPUESTOS AL DIA SAC');
            $response->assertDontSee('SIRE:');
            $response->assertDontSee('PDT 621:');
            $response->assertDontSee('Enero 2026');
            $response->assertDontSee('Setiembre 2026');

            $alerta = collect($response->viewData('alertasVencimiento'))
                ->first(fn (array $item): bool => $item['cliente']->razon_social === 'MESES PENDIENTES SAC');

            $this->assertSame([2, 8], array_column($alerta['meses'], 'mes'));
            $this->assertSame(['Febrero 2026', 'Agosto 2026'], array_column($alerta['meses'], 'nombre'));
            $this->assertSame('Pendiente', $alerta['meses'][0]['estado']);
            $this->assertSame('Procesando', $alerta['meses'][1]['estado']);
            $this->assertSame(2026, $alerta['anio']);
            $this->assertSame(
                ['SIRE', 'PDT 621', 'PDT 601'],
                collect($alerta['faltantes'])->pluck('nombre')->all()
            );

            $faltantes = collect($alerta['faltantes'])->keyBy('nombre');
            $this->assertNull($faltantes['SIRE']['vence']);
            $this->assertStringContainsString('meses anteriores', $faltantes['SIRE']['titulo']);
            $this->assertSame('16 set', $faltantes['PDT 621']['vence']);
            $this->assertSame('PDT 621', $faltantes['PDT 621']['etiqueta']);
            $this->assertSame('SIRE', $faltantes['SIRE']['etiqueta']);
            $this->assertNull($faltantes['PDT 601']['vence']);
            $this->assertSame('Febrero 2026 (Vencido)', $alerta['meses'][0]['presentacion']['texto']);
            $this->assertSame('alerta', $alerta['meses'][0]['presentacion']['marca']);
            $this->assertSame('Agosto 2026 (En proceso)', $alerta['meses'][1]['presentacion']['texto']);
            $this->assertSame('alerta', $alerta['meses'][1]['presentacion']['marca']);
            $response->assertSee('Febrero 2026 (Vencido)');
            $response->assertSee('⚠️', false);
            $response->assertDontSee('PDT 621 16 set');
            $response->assertDontSee('SIRE 16 mar');
            $response->assertDontSee('PDT 601 16 set');
            $response->assertDontSee('Vence:');
            $response->assertSee(route('control.index', ['anio' => 2026, 'empresa' => $pendiente->id]));
        });
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

        $enProceso = Cliente::create([
            'razon_social' => 'BALANCE EN PROCESO SAC',
            'ruc' => '20444444444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        ControlBalance::create([
            'cliente_id' => $enProceso->id,
            'anio' => 2026,
            'meses' => ['6' => 'Procesando'],
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
            $response->assertSee('Pendientes (1)');
            $response->assertSee('En Proceso (1)');
            $response->assertSee(route('balances.index', ['anio' => 2026]));

            $avance = $response->viewData('avanceBalances');
            $this->assertSame(1, $avance['declarados']);
            $this->assertSame(1, $avance['procesando']);
            $this->assertSame(1, $avance['pendientes']);
            $this->assertSame(3, $avance['total']);
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
                ->assertSee('Buenos días, Sebastian 👋')
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
                ->assertSee('Buenas tardes, Diego 👋')
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
                ->assertSee('Buenas noches, María 👋');
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
            $response->assertSeeInOrder([
                'Alertas de Vencimiento de Impuestos',
                'Alertas de Vencimiento de Balances',
            ]);
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
            $response->assertDontSee('100% Cobrado');
        });
    }

    public function test_dashboard_shows_a_clear_honorarios_card_when_nothing_is_owed(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            $cliente = Cliente::create([
                'razon_social' => 'HONORARIOS AL DIA SAC',
                'ruc' => '20555555555',
                'regimen_tributario' => 'General',
                'estado' => 'activo',
            ]);

            foreach (['08-2026', '07-2026', '06-2026'] as $periodo) {
                Operacion::create([
                    'cliente_id' => $cliente->id,
                    'periodo' => $periodo,
                    'estado_pago_honorarios' => 'Pagado',
                ]);
            }

            $response = $this->get(route('dashboard'));

            $response->assertOk();
            $response->assertViewHas('honorariosAtrasados.total', 0);
            $response->assertSee('Cartera al día, sin deudas');
            $response->assertSee('100% Cobrado');
            $response->assertSee('text-emerald-500', false);
            $response->assertDontSee('Pendientes de cobro');
        });
    }

    public function test_dashboard_radar_dims_past_dates_and_highlights_today(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            CronogramaSunat::create([
                'anio' => 2026,
                'tipo' => 'pdt_621',
                'mes' => 8,
                'periodo' => '08-2026',
                'digito_0' => '2026-09-10',
                'digito_1' => '2026-09-23',
                'digito_2_3' => '2026-09-24',
                'buenos_contribuyentes' => '2026-09-28',
            ]);
            CronogramaSunat::create([
                'anio' => 2026,
                'tipo' => 'sire',
                'mes' => 8,
                'periodo' => '08-2026',
                'digito_0' => '2026-09-09',
                'digito_1' => '2026-09-22',
                'digito_2_3' => '2026-09-22',
                'buenos_contribuyentes' => '2026-09-25',
            ]);
            CronogramaSunat::create([
                'anio' => 2026,
                'tipo' => 'balance_mensual',
                'mes' => 6,
                'periodo' => '06-2026',
                'fecha_vencimiento_general' => '2026-09-30',
            ]);

            $response = $this->get(route('dashboard'));

            $response->assertOk();
            $response->assertSeeInOrder([
                'Clientes Activos',
                'Avance Impuestos',
                'Avance Balances',
                'Buzón SOL Pendiente',
                'Control Honorarios',
                'Estado General del Estudio',
                'Obligaciones Mensuales',
                'SIRE, PDT 621 y PLAME',
                'PLE y DJ Anual',
                'Radar de Vencimientos (Mes Activo)',
                'Impuestos (SIRE y PDT 621)',
                'Balances Mensuales (PLE)',
            ]);

            $radar = $response->viewData('radarVencimientos');
            $impuestos = collect($radar['impuestos']['fechas'])->keyBy('digito');

            $this->assertSame('08-2026', $radar['impuestos']['periodo']);
            $this->assertSame('pasado', $impuestos['0']['estado']);
            $this->assertSame('10/09', $impuestos['0']['pdt']);
            $this->assertSame('09/09', $impuestos['0']['sire']);
            $this->assertSame('hoy', $impuestos['1']['estado']);
            $this->assertSame('futuro', $impuestos['2']['estado']);
            $this->assertSame('futuro', $impuestos['3']['estado']);
            $this->assertSame('futuro', $impuestos['BC']['estado']);
            $this->assertSame('06-2026', $radar['balances']['periodo']);
            $this->assertSame('futuro', $radar['balances']['fechas'][0]['estado']);
            $this->assertSame('30/09', $radar['balances']['fechas'][0]['pdt']);
            $this->assertCount(11, $radar['balances']['fechas']);
            $response->assertSeeInOrder([
                'Impuestos (SIRE y PDT 621)',
                '08-2026',
                'Balances Mensuales (PLE)',
                '06-2026',
            ]);
            $response->assertSee('grid-cols-6', false);
            $response->assertSee('col-span-2', false);
            $response->assertSee('bg-indigo-50', false);
            $response->assertSee('opacity-70', false);
            $response->assertDontSee('opacity-50', false);
            $response->assertSee('ring-emerald-500', false);
        });
    }

    public function test_tax_alert_chips_show_the_current_cronograma_due_date(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // Cliente 1: Dígito 8 estándar, con SIRE pendiente (SIRE vence 21/09/2026 para dígito 8)
        $clienteEstandar = Cliente::create([
            'razon_social' => 'CLIENTE DIGITO OCHO SAC',
            'ruc' => '20123456788',
            'regimen_tributario' => 'General',
            'es_buen_contribuyente' => false,
            'estado' => true,
        ]);
        foreach (range(1, 7) as $m) {
            Operacion::create([
                'cliente_id' => $clienteEstandar->id,
                'periodo' => sprintf('%02d-2026', $m),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }
        Operacion::create([
            'cliente_id' => $clienteEstandar->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        // Cliente 2: Dígito 8 pero Buen Contribuyente (aplica fecha especial BC: SIRE vence 22/09/2026)
        $clienteBueno = Cliente::create([
            'razon_social' => 'CLIENTE BUEN CONTRIBUYENTE SAC',
            'ruc' => '20987654328',
            'regimen_tributario' => 'General',
            'es_buen_contribuyente' => true,
            'estado' => true,
        ]);
        foreach (range(1, 7) as $m) {
            Operacion::create([
                'cliente_id' => $clienteBueno->id,
                'periodo' => sprintf('%02d-2026', $m),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }
        Operacion::create([
            'cliente_id' => $clienteBueno->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        // Cliente 3: Dígito 8 estándar, pero SIRE ya declarado y PDT 621 pendiente (vence según PDT: 22/09/2026)
        $clienteSireListo = Cliente::create([
            'razon_social' => 'CLIENTE SIRE DECLARADO SAC',
            'ruc' => '20555555558',
            'regimen_tributario' => 'General',
            'es_buen_contribuyente' => false,
            'estado' => true,
        ]);
        foreach (range(1, 7) as $m) {
            Operacion::create([
                'cliente_id' => $clienteSireListo->id,
                'periodo' => sprintf('%02d-2026', $m),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }
        Operacion::create([
            'cliente_id' => $clienteSireListo->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Declarado',
        ]);

        $this->travelTo(Carbon::parse('2026-09-21 10:00:00', 'America/Lima'), function () use ($clienteEstandar, $clienteBueno, $clienteSireListo) {
            $response = $this->actingAs($this->usuarioDelEstudio('Admin'))
                ->get(route('dashboard'));

            $response->assertOk();
            $response->assertDontSee('Sire:');
            $response->assertDontSee('PDTs:');
            $response->assertDontSee('SIRE 21 set');
            $response->assertDontSee('PDT 621 22 set');
            $response->assertDontSee('PDT 601 22 set');
            $response->assertDontSee('SIRE 22 set');
            $response->assertDontSee('PDT 621 23 set');
            $response->assertDontSee('PDT 601 23 set');
            $response->assertDontSee('¡Hoy!');
            $response->assertSee('Ago-26 (Vence: 21 set)');
            $response->assertSee('Ago-26 (Vence: 22 set)');
            $response->assertSee('border-rose-200 bg-rose-50', false);

            /** @var list<array<string, mixed>> $alertas */
            $alertas = $response->viewData('alertasVencimiento');

            $estandar = collect(collect($alertas)->firstWhere('cliente.id', $clienteEstandar->id)['faltantes'])->keyBy('nombre');
            $this->assertSame('SIRE', $estandar['SIRE']['etiqueta']);
            $this->assertSame('PDT 621', $estandar['PDT 621']['etiqueta']);
            $this->assertSame('PDT 601', $estandar['PDT 601']['etiqueta']);
            $this->assertSame('21 set', $estandar['SIRE']['vence']);
            $this->assertSame('22 set', $estandar['PDT 621']['vence']);
            $this->assertSame('22 set', $estandar['PDT 601']['vence']);
            $this->assertSame(
                'Ago-26 (Vence: 21 set)',
                collect($alertas)->firstWhere('cliente.id', $clienteEstandar->id)['meses'][0]['presentacion']['texto']
            );
            $this->assertStringContainsString('registros electrónicos SIRE', $estandar['SIRE']['titulo']);
            $this->assertStringContainsString('PLAME', $estandar['PDT 601']['titulo']);

            $this->assertSame(
                'punto',
                collect($alertas)->firstWhere('cliente.id', $clienteEstandar->id)['meses'][0]['presentacion']['marca']
            );

            $bueno = collect(collect($alertas)->firstWhere('cliente.id', $clienteBueno->id)['faltantes'])->keyBy('nombre');
            $this->assertSame('22 set', $bueno['SIRE']['vence']);
            $this->assertSame('23 set', $bueno['PDT 621']['vence']);
            $this->assertSame('23 set', $bueno['PDT 601']['vence']);
            $this->assertSame(
                'Ago-26 (Vence: 22 set)',
                collect($alertas)->firstWhere('cliente.id', $clienteBueno->id)['meses'][0]['presentacion']['texto']
            );

            $sireListo = collect(collect($alertas)->firstWhere('cliente.id', $clienteSireListo->id)['faltantes'])->keyBy('nombre');
            $this->assertSame(['PDT 621'], $sireListo->keys()->all());
            $this->assertSame('22 set', $sireListo['PDT 621']['vence']);
            $this->assertSame(
                'Ago-26 (Vence: 22 set) (En proceso)',
                collect($alertas)->firstWhere('cliente.id', $clienteSireListo->id)['meses'][0]['presentacion']['texto']
            );
        });
    }

    public function test_tax_alert_chips_use_the_october_schedule_of_the_september_period(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        Cliente::create([
            'razon_social' => 'DIGITOS CERO SAC',
            'ruc' => '20999999990',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', 'America/Lima'), function () {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $alerta = collect($response->viewData('alertasVencimiento'))
                ->first(fn (array $item): bool => $item['cliente']->razon_social === 'DIGITOS CERO SAC');
            $faltantes = collect($alerta['faltantes'])->keyBy('nombre');

            $this->assertSame('SIRE', $faltantes['SIRE']['etiqueta']);
            $this->assertSame('15 oct', $faltantes['SIRE']['vence']);
            $this->assertSame('16 oct', $faltantes['PDT 621']['vence']);
            $this->assertSame('16 oct', $faltantes['PDT 601']['vence']);
            $septiembre = collect($alerta['meses'])->firstWhere('periodo', '09-2026');
            $enero = collect($alerta['meses'])->firstWhere('periodo', '01-2026');
            $this->assertSame('Set-26 (Vence: 15 oct)', $septiembre['presentacion']['texto']);
            $this->assertSame('punto', $septiembre['presentacion']['marca']);
            $this->assertSame('Enero 2026 (Vencido)', $enero['presentacion']['texto']);
            $this->assertSame('alerta', $enero['presentacion']['marca']);
            $response->assertSee('Set-26 (Vence: 15 oct)');
            $response->assertSee('Enero 2026 (Vencido)');
            $response->assertSee('⚠️', false);
            $response->assertDontSee('SIRE 15 oct');
            $response->assertDontSee('PDT 621 16 oct');
            $response->assertDontSee('PDT 601 16 oct');
            $response->assertDontSee('SIRE 13 feb');
        });
    }

    public function test_tax_alert_chip_adds_the_year_when_the_due_date_falls_in_another_year(): void
    {
        foreach (['sire' => '2027-01-05', 'pdt_621' => '2027-01-08'] as $tipo => $fechaDigito) {
            CronogramaSunat::create([
                'anio' => 2026,
                'tipo' => $tipo,
                'mes' => 11,
                'periodo' => '11-2026',
                'digito_0' => '2026-12-15',
                'digito_8_9' => $fechaDigito,
                'descripcion' => 'Prueba de año',
            ]);
        }

        Cliente::create([
            'razon_social' => 'CIERRE ANUAL SAC',
            'ruc' => '20111111118',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-12-10 10:00:00', 'America/Lima'), function () {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $alerta = collect($response->viewData('alertasVencimiento'))
                ->first(fn (array $item): bool => $item['cliente']->razon_social === 'CIERRE ANUAL SAC');
            $faltantes = collect($alerta['faltantes'])->keyBy('nombre');

            $this->assertSame('SIRE', $faltantes['SIRE']['etiqueta']);
            $this->assertSame('5 ene 27', $faltantes['SIRE']['vence']);
            $this->assertSame('8 ene 27', $faltantes['PDT 621']['vence']);
            $this->assertSame('8 ene 27', $faltantes['PDT 601']['vence']);
            $noviembre = collect($alerta['meses'])->firstWhere('periodo', '11-2026');
            $this->assertSame('Nov-26 (Vence: 5 ene 27)', $noviembre['presentacion']['texto']);
            $this->assertSame('punto', $noviembre['presentacion']['marca']);
            $response->assertSee('Nov-26 (Vence: 5 ene 27)');
            $response->assertDontSee('SIRE 5 ene 27');
            $response->assertDontSee('PDT 621 8 ene 27');
            $response->assertDontSee('PDT 601 8 ene 27');
        });
    }

    public function test_un_mes_en_proceso_dentro_del_cronograma_conserva_la_fecha_abreviada(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $cliente = Cliente::create([
            'razon_social' => 'EN PROCESO OCTUBRE SAC',
            'ruc' => '20444444440',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        foreach (range(1, 8) as $mes) {
            Operacion::create([
                'cliente_id' => $cliente->id,
                'periodo' => sprintf('%02d-2026', $mes),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }

        Operacion::create([
            'cliente_id' => $cliente->id,
            'periodo' => '09-2026',
            'estado_compras_ventas' => 'Procesando',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        $this->travelTo(Carbon::parse('2026-10-01 09:00:00', 'America/Lima'), function () use ($cliente) {
            $response = $this->actingAs($this->usuarioDelEstudio('Sebastian'))
                ->get(route('dashboard'));

            $alerta = collect($response->viewData('alertasVencimiento'))
                ->first(fn (array $item): bool => $item['cliente']->id === $cliente->id);

            $this->assertSame('Set-26 (Vence: 15 oct) (En proceso)', $alerta['meses'][0]['presentacion']['texto']);
            $this->assertSame('punto', $alerta['meses'][0]['presentacion']['marca']);
            $this->assertSame(
                ['SIRE', 'PDT 621', 'PDT 601'],
                collect($alerta['faltantes'])->pluck('etiqueta')->all()
            );
            $response->assertSee('Set-26 (Vence: 15 oct) (En proceso)');
            $response->assertDontSee('SIRE 15 oct');
        });
    }

    private function declararMesesAnteriores(Cliente $cliente): void
    {
        $mesActual = (int) now()->format('n');
        $anio = now()->format('Y');

        for ($mes = 1; $mes < $mesActual; $mes++) {
            Operacion::create([
                'cliente_id' => $cliente->id,
                'periodo' => sprintf('%02d-%s', $mes, $anio),
                'estado_compras_ventas' => 'Declarado',
                'estado_pdt621' => 'Declarado',
                'estado_ple' => 'Declarado',
            ]);
        }
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
