<?php

namespace Tests\Feature;

use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Services\CronogramaSunatService;
use Carbon\Carbon;
use Database\Seeders\CronogramaSunatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CronogramaSunatTest extends TestCase
{
    use RefreshDatabase;

    protected CronogramaSunatService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CronogramaSunatService::class);
    }

    public function test_seeder_populates_official_2026_cronograma(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $this->assertDatabaseCount('cronogramas_sunat', 37);

        // Validar fechas exactas de la Resolución SUNAT para Enero 2026 (PDT 621)
        $pdtEne = CronogramaSunat::query()
            ->where('tipo', 'pdt_621')
            ->where('periodo', '01-2026')
            ->first();

        $this->assertNotNull($pdtEne);
        $this->assertEquals('2026-02-16', $pdtEne->digito_0->format('Y-m-d'));
        $this->assertEquals('2026-02-17', $pdtEne->digito_1->format('Y-m-d'));
        $this->assertEquals('2026-02-18', $pdtEne->digito_2_3->format('Y-m-d'));
        $this->assertEquals('2026-02-19', $pdtEne->digito_4_5->format('Y-m-d'));
        $this->assertEquals('2026-02-20', $pdtEne->digito_6_7->format('Y-m-d'));
        $this->assertEquals('2026-02-23', $pdtEne->digito_8_9->format('Y-m-d'));
        $this->assertEquals('2026-02-24', $pdtEne->buenos_contribuyentes->format('Y-m-d'));

        // Validar fechas exactas del SIRE para Enero 2026 (1 día hábil antes)
        $sireEne = CronogramaSunat::query()
            ->where('tipo', 'sire')
            ->where('periodo', '01-2026')
            ->first();

        $this->assertNotNull($sireEne);
        // Si PDT dígito 0 vencía lunes 16/02, SIRE vence viernes 13/02 (salta fin de semana)
        $this->assertEquals('2026-02-13', $sireEne->digito_0->format('Y-m-d'));
        $this->assertEquals('2026-02-16', $sireEne->digito_1->format('Y-m-d'));
        $this->assertEquals('2026-02-20', $sireEne->digito_8_9->format('Y-m-d'));
        $this->assertEquals('2026-02-23', $sireEne->buenos_contribuyentes->format('Y-m-d'));

        // Validar período Agosto 2026 (vence en Setiembre 2026)
        $pdtAgo = CronogramaSunat::query()
            ->where('tipo', 'pdt_621')
            ->where('periodo', '08-2026')
            ->first();

        $this->assertNotNull($pdtAgo);
        $this->assertEquals('2026-09-15', $pdtAgo->digito_0->format('Y-m-d'));
        $this->assertEquals('2026-09-16', $pdtAgo->digito_1->format('Y-m-d'));
        $this->assertEquals('2026-09-22', $pdtAgo->digito_8_9->format('Y-m-d'));
        $this->assertEquals('2026-09-23', $pdtAgo->buenos_contribuyentes->format('Y-m-d'));
    }

    public function test_obtener_periodo_en_curso_resolves_august_in_september(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // Simular fecha 18 de Setiembre de 2026
        $fechaSeptiembre = Carbon::create(2026, 9, 18, 10, 0, 0);

        $periodoEnCurso = $this->service->obtenerPeriodoEnCurso('pdt_621', $fechaSeptiembre);

        // Debe resolver 08-2026 (las operaciones de agosto vencen en setiembre)
        $this->assertEquals('08-2026', $periodoEnCurso);
    }

    public function test_monthly_balance_period_closes_three_months_after_the_operations_month(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $junio = $this->service->obtenerPeriodoEnCurso('balance_mensual', Carbon::create(2026, 9, 23));
        $octubre = $this->service->obtenerPeriodoEnCurso('balance_mensual', Carbon::create(2027, 1, 15));

        $this->assertSame('06-2026', $junio);
        $this->assertSame('10-2026', $octubre);
    }

    public function test_monthly_balance_period_falls_back_to_three_months_without_a_schedule(): void
    {
        $periodo = $this->service->obtenerPeriodoEnCurso('balance_mensual', Carbon::create(2026, 9, 23));

        $this->assertSame('06-2026', $periodo);
    }

    public function test_obtener_fecha_vencimiento_by_client_ruc_digit_and_buen_contribuyente(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // Cliente estándar terminado en dígito 0
        $clienteDigito0 = Cliente::create([
            'razon_social' => 'CLIENTE DIGITO CERO SAC',
            'ruc' => '20123456780',
            'regimen_tributario' => 'MYPE',
            'es_buen_contribuyente' => false,
            'estado' => true,
        ]);

        $fechaPdt = $this->service->obtenerFechaVencimiento('pdt_621', '08-2026', $clienteDigito0);
        $this->assertEquals('2026-09-15', $fechaPdt->format('Y-m-d'));

        // Cliente estándar terminado en dígito 5 (dígito 4 y 5)
        $clienteDigito5 = Cliente::create([
            'razon_social' => 'CLIENTE DIGITO CINCO SAC',
            'ruc' => '20123456785',
            'regimen_tributario' => 'MYPE',
            'es_buen_contribuyente' => false,
            'estado' => true,
        ]);

        $fechaPdt5 = $this->service->obtenerFechaVencimiento('pdt_621', '08-2026', $clienteDigito5);
        $this->assertEquals('2026-09-18', $fechaPdt5->format('Y-m-d'));

        // Cliente Buen Contribuyente (sin importar el dígito, tiene fecha extendida)
        $clienteBueno = Cliente::create([
            'razon_social' => 'BUEN CONTRIBUYENTE SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'General',
            'es_buen_contribuyente' => true,
            'estado' => true,
        ]);

        $fechaBueno = $this->service->obtenerFechaVencimiento('pdt_621', '08-2026', $clienteBueno);
        $this->assertEquals('2026-09-23', $fechaBueno->format('Y-m-d'));
    }

    public function test_calcular_estado_vencimiento_evaluates_correctly(): void
    {
        $hoy = Carbon::create(2026, 9, 18, 0, 0, 0);

        // Vence hoy
        $estadoHoy = $this->service->calcularEstadoVencimiento(Carbon::create(2026, 9, 18), $hoy);
        $this->assertEquals('hoy', $estadoHoy['estado']);
        $this->assertStringContainsString('Vence Hoy', $estadoHoy['badge_texto']);

        // Vencido hace 3 días
        $estadoVencido = $this->service->calcularEstadoVencimiento(Carbon::create(2026, 9, 15), $hoy);
        $this->assertEquals('vencido', $estadoVencido['estado']);
        $this->assertEquals(3, $estadoVencido['dias']);

        // Próximo a vencer (en 2 días)
        $estadoProximo = $this->service->calcularEstadoVencimiento(Carbon::create(2026, 9, 20), $hoy);
        $this->assertEquals('proximo', $estadoProximo['estado']);

        // En plazo futuro (en 5 días)
        $estadoFuturo = $this->service->calcularEstadoVencimiento(Carbon::create(2026, 9, 23), $hoy);
        $this->assertEquals('al_dia', $estadoFuturo['estado']);
    }

    public function test_autogenerar_sire_desde_pdt_subtracts_business_days(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // Eliminar SIRE existente para regenerar
        CronogramaSunat::query()->where('tipo', 'sire')->delete();
        $this->assertEquals(0, CronogramaSunat::query()->where('tipo', 'sire')->count());

        $actualizados = $this->service->autogenerarSireDesdePdt(2026);
        $this->assertEquals(12, $actualizados);

        // Validar que lunes 16/02/2026 retrocedió a viernes 13/02/2026
        $sireEne = CronogramaSunat::query()
            ->where('tipo', 'sire')
            ->where('periodo', '01-2026')
            ->first();

        $this->assertEquals('2026-02-13', $sireEne->digito_0->format('Y-m-d'));
    }

    public function test_cronogramas_web_routes_and_views(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // GET index PDT 621
        $responsePdt = $this->get(route('cronogramas.index'));
        $responsePdt->assertOk();
        $responsePdt->assertSee('Cronograma Oficial SUNAT 2026');
        $responsePdt->assertSee('Obligaciones Mensuales (PDT 621 / IGV - Renta)');

        // GET index SIRE
        $responseSire = $this->get(route('cronogramas.index', ['tipo' => 'sire']));
        $responseSire->assertOk();
        $responseSire->assertSee('Registros Electrónicos (SIRE - RCE y RVIE)');

        // GET index Balances Mensuales
        $responseBalances = $this->get(route('cronogramas.index', ['tipo' => 'balance_mensual']));
        $responseBalances->assertOk();
        $responseBalances->assertSee('Balances Mensuales (Atraso Máximo 3 Meses)');

        // POST update dates
        $registro = CronogramaSunat::first();
        $responseUpdate = $this->post(route('cronogramas.update'), [
            'cronogramas' => [
                [
                    'id' => $registro->id,
                    'digito_0' => '2026-02-28',
                ],
            ],
        ]);

        $responseUpdate->assertRedirect();
        $this->assertEquals('2026-02-28', $registro->fresh()->digito_0->format('Y-m-d'));

        // POST autogenerar-sire
        $responseAuto = $this->post(route('cronogramas.autogenerar-sire'), [
            'anio' => 2026,
        ]);
        $responseAuto->assertRedirect();
    }

    public function test_cliente_update_datos_fiscales_saves_es_buen_contribuyente(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CONTRIBUYENTE TEST SAC',
            'ruc' => '20777777777',
            'regimen_tributario' => 'MYPE',
            'es_buen_contribuyente' => false,
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.datos-fiscales.update', $cliente), [
            'regimen_tributario' => 'General',
            'correo' => 'fiscal@test.pe',
            'telefono' => '999111222',
            'es_buen_contribuyente' => '1',
        ]);

        $response->assertRedirect();
        $this->assertTrue($cliente->fresh()->es_buen_contribuyente);
        $this->assertSame(RegimenTributario::General, $cliente->fresh()->regimen_tributario);
    }

    public function test_dashboard_uses_cronograma_period_and_shows_alertas(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        // Cliente activo con RUC terminado en 0
        $cliente = Cliente::create([
            'razon_social' => 'CARLOS ALBERTO FLORES VEGA',
            'ruc' => '10444555660',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $this->travelTo(Carbon::parse('2026-09-15 10:00:00', 'America/Lima'), function () {
            $response = $this->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee('Periodo a declarar:');
            $response->assertSee('08-2026'); // Período que vence en Setiembre
            $response->assertSee('Cronograma SUNAT');
            $response->assertSee('CARLOS ALBERTO FLORES VEGA');
            $response->assertSee('Dígito');
        });
    }
}
