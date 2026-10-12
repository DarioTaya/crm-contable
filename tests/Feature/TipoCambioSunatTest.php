<?php

namespace Tests\Feature;

use App\Models\Estudio;
use App\Models\TipoCambioSunat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TipoCambioSunatTest extends TestCase
{
    use RefreshDatabase;

    public function test_exchange_rate_page_shows_the_official_buy_and_sell_rates(): void
    {
        $this->publicar('23/09/2026|3.356|3.362|');

        $response = $this->actingAs($this->usuario())
            ->get(route('tipo-cambio.index'));

        $response->assertOk();
        $response->assertSee('Tipo de cambio SUNAT');
        $response->assertSee('23 de setiembre de 2026');
        $response->assertSee('3.356');
        $response->assertSee('3.362');
        $response->assertSee('Compra');
        $response->assertSee('Venta');
        $response->assertSee('Sincronizar mes');
        $response->assertDontSee('Consulta oficial SUNAT');

        $guardado = TipoCambioSunat::query()->first();
        $this->assertNotNull($guardado);
        $this->assertSame('2026-09-23', $guardado->fecha->toDateString());
        $this->assertEquals(3.356, (float) $guardado->compra);
        $this->assertEquals(3.362, (float) $guardado->venta);

        $this->actingAs($this->usuario())->get(route('tipo-cambio.index'))->assertOk();
        Http::assertSentCount(1);
    }

    public function test_dashboard_shows_the_day_buy_and_sell_rates(): void
    {
        $this->publicar('23/09/2026|3.356|3.362|');

        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Periodo a declarar:', 'Tipo de cambio', 'Cronograma SUNAT', 'Ver Clientes'])
            ->assertSee('3.356')
            ->assertSee('3.362')
            ->assertSee(route('tipo-cambio.index'));
    }

    public function test_dashboard_shows_todays_lima_rate_when_the_daily_file_still_has_yesterday(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 05:10:00', 'America/Lima'));
        $this->publicarMes('02/10/2026|3.350|3.360|', [
            ['fecPublica' => '02/10/2026', 'valTipo' => '3.350', 'codTipo' => 'C'],
            ['fecPublica' => '02/10/2026', 'valTipo' => '3.360', 'codTipo' => 'V'],
            ['fecPublica' => '03/10/2026', 'valTipo' => '3.370', 'codTipo' => 'C'],
            ['fecPublica' => '03/10/2026', 'valTipo' => '3.380', 'codTipo' => 'V'],
        ]);

        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('03/10/2026')
            ->assertSee('3.370')
            ->assertSee('3.380')
            ->assertDontSee('3.350');
    }

    public function test_dashboard_ignores_a_fresh_cache_from_the_previous_lima_day(): void
    {
        $this->travelTo(Carbon::parse('2026-10-03 00:05:00', 'America/Lima'));
        $this->publicar('02/10/2026|3.350|3.360|');
        TipoCambioSunat::query()->create([
            'fecha' => '2026-10-03',
            'compra' => 3.370,
            'venta' => 3.380,
        ]);
        Cache::put('sunat.tipo-cambio.vigente', [
            'fecha' => '2 de octubre de 2026',
            'fecha_corta' => '02/10/2026',
            'fecha_iso' => '2026-10-02',
            'compra' => 3.350,
            'venta' => 3.360,
            'compra_texto' => '3.350',
            'venta_texto' => '3.360',
            'diferencia_texto' => '0.010',
            'moneda' => 'USD/PEN',
            'consultado_en' => now()->toIso8601String(),
        ], now()->addMinutes(15));

        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('03/10/2026')
            ->assertSee('3.370')
            ->assertDontSee('3.350');

        Http::assertNothingSent();
    }

    public function test_dashboard_stays_available_when_sunat_does_not_publish(): void
    {
        config(['services.sunat.tipo_cambio_url' => 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt']);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.sunat.gob.pe/a/txt/tipoCambio.txt' => Http::response('no disponible', 500),
        ]);

        $this->actingAs($this->usuario())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sin cotización');

        $this->assertSame(0, TipoCambioSunat::query()->count());
    }

    public function test_malformed_official_file_does_not_invent_a_rate(): void
    {
        $this->publicar('sin-formato');

        $this->actingAs($this->usuario())
            ->getJson(route('tipo-cambio.actual'))
            ->assertOk()
            ->assertJson(['disponible' => false]);

        $this->assertSame(0, TipoCambioSunat::query()->count());
    }

    public function test_guest_is_redirected_from_the_exchange_rate_page(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->get(route('tipo-cambio.index'))
            ->assertRedirect(route('login'));
    }

    public function test_chart_keeps_the_last_year_and_leaves_out_older_rates(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicar('23/09/2026|3.356|3.362|');

        TipoCambioSunat::query()->create([
            'fecha' => '2026-08-15',
            'compra' => 3.401,
            'venta' => 3.411,
        ]);
        TipoCambioSunat::query()->create([
            'fecha' => '2025-01-01',
            'compra' => 3.700,
            'venta' => 3.710,
        ]);

        $this->actingAs($this->usuario())
            ->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSee('Comportamiento del Dólar')
            ->assertSeeInOrder(['1M', '3M', '6M', '1A'])
            ->assertSee('2026-08-15')
            ->assertDontSee('2025-01-01')
            ->assertDontSee('3.700');
    }

    public function test_past_month_stays_empty_until_it_is_synced(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicarMes('23/09/2026|3.356|3.362|', [
            ['fecPublica' => '01/08/2026', 'valTipo' => '3.400', 'codTipo' => 'C'],
            ['fecPublica' => '01/08/2026', 'valTipo' => '3.410', 'codTipo' => 'V'],
        ]);

        $this->actingAs($this->usuario())
            ->get(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 8]))
            ->assertOk()
            ->assertSee('Historial de Agosto de 2026')
            ->assertSee('Mes anterior', false)
            ->assertSee('Mes siguiente', false)
            ->assertSee(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 7]))
            ->assertSee(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 9]))
            ->assertSee('Mes actual')
            ->assertSee('Este mes no se descarga solo')
            ->assertSee('Este mes está vacío')
            ->assertDontSee('3.400');

        Http::assertNotSent(function (Request $request) {
            return str_contains($request->url(), 'listarTipoCambio')
                && (int) ($request->data()['mes'] ?? -1) === 7;
        });
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'listarTipoCambio')
                && (int) $request->data()['anio'] === 2026
                && (int) $request->data()['mes'] === 8;
        });
        $this->assertFalse(
            TipoCambioSunat::query()->whereYear('fecha', 2026)->whereMonth('fecha', 8)->exists()
        );
    }

    public function test_sync_button_copies_the_official_month_and_ignores_other_days(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicarMes('23/09/2026|3.356|3.362|', [
            ['fecPublica' => '01/08/2026', 'valTipo' => '3.400', 'codTipo' => 'C'],
            ['fecPublica' => '01/08/2026', 'valTipo' => '3.410', 'codTipo' => 'V'],
            ['fecPublica' => '02/08/2026', 'valTipo' => '3.401', 'codTipo' => 'C'],
            ['fecPublica' => '02/08/2026', 'valTipo' => '3.411', 'codTipo' => 'V'],
            ['fecPublica' => '03/08/2026', 'valTipo' => '3.500', 'codTipo' => 'C'],
            ['fecPublica' => '03/08/2026', 'valTipo' => '3.100', 'codTipo' => 'V'],
            ['fecPublica' => '01/09/2026', 'valTipo' => '9.000', 'codTipo' => 'C'],
            ['fecPublica' => '01/09/2026', 'valTipo' => '9.100', 'codTipo' => 'V'],
        ]);

        $respuesta = $this->actingAs($this->usuario())
            ->post(route('tipo-cambio.sincronizar'), ['anio' => 2026, 'mes' => 8]);

        $respuesta->assertRedirect(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 8]));
        $respuesta->assertSessionHas('success', 'Se copiaron 2 cotizaciones de agosto de 2026.');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio'
                && $request->data()['anio'] === 2026
                && $request->data()['mes'] === 7
                && $request->data()['token'] !== '';
        });

        $fechas = TipoCambioSunat::query()->orderBy('fecha')->get()
            ->map(fn (TipoCambioSunat $fila) => $fila->fecha->toDateString())
            ->all();

        $this->assertSame(['2026-08-01', '2026-08-02'], $fechas);

        $this->actingAs($this->usuario())
            ->get(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 8]))
            ->assertOk()
            ->assertSeeInOrder(['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'])
            ->assertSeeInOrder(['Compra 3.400', 'Venta 3.410'])
            ->assertSee('3.411')
            ->assertDontSee('9.000')
            ->assertDontSee('3.500');
    }

    public function test_current_month_fills_itself_through_today_and_does_not_repeat_the_download(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicarMes('23/09/2026|3.356|3.362|', [
            ['fecPublica' => '22/09/2026', 'valTipo' => '3.350', 'codTipo' => 'C'],
            ['fecPublica' => '22/09/2026', 'valTipo' => '3.362', 'codTipo' => 'V'],
            ['fecPublica' => '23/09/2026', 'valTipo' => '3.356', 'codTipo' => 'C'],
            ['fecPublica' => '23/09/2026', 'valTipo' => '3.362', 'codTipo' => 'V'],
            ['fecPublica' => '24/09/2026', 'valTipo' => '9.999', 'codTipo' => 'C'],
            ['fecPublica' => '24/09/2026', 'valTipo' => '9.999', 'codTipo' => 'V'],
        ]);

        $usuario = $this->usuario();

        $this->actingAs($usuario)
            ->get(route('tipo-cambio.index'))
            ->assertOk()
            ->assertSee('Historial de Setiembre de 2026')
            ->assertSee('table-fixed', false)
            ->assertSee('data-reserva-dia', false)
            ->assertSee('Este mes se completa solo')
            ->assertSeeInOrder(['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', '30', '31'])
            ->assertSeeInOrder(['Compra 3.350', 'Venta 3.362'])
            ->assertSee('3.350')
            ->assertDontSee('24/09/2026')
            ->assertDontSee('Octubre');

        Http::assertSentCount(1);

        $this->actingAs($usuario)->get(route('tipo-cambio.index'))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_future_month_cannot_be_synced(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicarMes('23/09/2026|3.356|3.362|', [
            ['fecPublica' => '01/10/2026', 'valTipo' => '9.000', 'codTipo' => 'C'],
            ['fecPublica' => '01/10/2026', 'valTipo' => '9.100', 'codTipo' => 'V'],
        ]);

        $this->actingAs($this->usuario())
            ->post(route('tipo-cambio.sincronizar'), ['anio' => 2026, 'mes' => 10])
            ->assertRedirect(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 9]))
            ->assertSessionHas('error', 'Ese mes todavía no tiene tipo de cambio publicado.');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'listarTipoCambio'));
        $this->assertSame(0, TipoCambioSunat::query()->count());
    }

    public function test_failed_month_download_does_not_invent_rates(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        config([
            'services.sunat.tipo_cambio_url' => 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt',
            'services.sunat.tipo_cambio_mes_url' => 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.sunat.gob.pe/a/txt/tipoCambio.txt' => Http::response('23/09/2026|3.356|3.362|', 200),
            'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio' => Http::response('no-es-una-lista', 500),
        ]);

        $this->actingAs($this->usuario())
            ->post(route('tipo-cambio.sincronizar'), ['anio' => 2026, 'mes' => 8])
            ->assertRedirect(route('tipo-cambio.index', ['anio' => 2026, 'mes' => 8]))
            ->assertSessionHas('error', 'No se pudo copiar el tipo de cambio de SUNAT. Inténtalo de nuevo en unos minutos.');

        $this->assertSame(0, TipoCambioSunat::query()->count());
    }

    public function test_empty_month_reports_that_sunat_published_nothing(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 15:00:00', 'UTC'));
        $this->publicarMes('23/09/2026|3.356|3.362|', []);

        $this->actingAs($this->usuario())
            ->post(route('tipo-cambio.sincronizar'), ['anio' => 2026, 'mes' => 8])
            ->assertSessionHas('error', 'SUNAT no publicó cotizaciones para ese mes.');

        $this->assertSame(0, TipoCambioSunat::query()->count());
    }

    public function test_guest_cannot_sync_a_month(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->post(route('tipo-cambio.sincronizar'), ['anio' => 2026, 'mes' => 8])
            ->assertRedirect(route('login'));
    }

    public function test_sync_command_stores_the_published_rate(): void
    {
        $this->publicar('23/09/2026|3.356|3.362|');

        $this->artisan('sunat:sincronizar-tipo-cambio')
            ->expectsOutputToContain('compra 3.356, venta 3.362')
            ->assertSuccessful();
    }

    public function test_endpoint_mes_json_returns_calendar_structure_and_rates(): void
    {
        TipoCambioSunat::query()->create([
            'fecha' => '2026-10-05',
            'compra' => 3.750,
            'venta' => 3.758,
        ]);

        $response = $this->actingAs($this->usuario())
            ->getJson(route('tipo-cambio.mes', ['anio' => 2026, 'mes' => 10]));

        $response->assertOk();
        $response->assertJsonStructure([
            'anio',
            'mes',
            'nombre_mes',
            'es_mes_actual',
            'mes_anterior',
            'mes_siguiente',
            'vigente',
            'semanas',
            'dias_semana',
            'tiene_datos',
            'url_completa',
        ]);
        $response->assertJson([
            'anio' => 2026,
            'mes' => 10,
            'tiene_datos' => true,
        ]);
    }

    public function test_ajax_sync_returns_json_and_updates_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 12:00:00', 'America/Lima'));
        $this->publicarMes('12/10/2026|3.750|3.760|', [
            ['fecPublica' => '10/10/2026', 'valTipo' => '3.748', 'codTipo' => 'C'],
            ['fecPublica' => '10/10/2026', 'valTipo' => '3.755', 'codTipo' => 'V'],
        ]);

        $response = $this->actingAs($this->usuario())
            ->postJson(route('tipo-cambio.sincronizar'), [
                'anio' => 2026,
                'mes' => 10,
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'dias' => 1,
        ]);
        $fila = TipoCambioSunat::query()->whereDate('fecha', '2026-10-10')->first();
        $this->assertNotNull($fila);
        $this->assertEquals(3.748, (float) $fila->compra);
        $this->assertEquals(3.755, (float) $fila->venta);
    }

    private function publicar(string $cuerpo): void
    {
        config([
            'services.sunat.tipo_cambio_url' => 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt',
            'services.sunat.tipo_cambio_mes_url' => '',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.sunat.gob.pe/a/txt/tipoCambio.txt' => Http::response($cuerpo, 200),
        ]);
    }

    /**
     * @param  list<array{fecPublica: string, valTipo: string, codTipo: string}>  $filas
     */
    private function publicarMes(string $cuerpo, array $filas): void
    {
        config([
            'services.sunat.tipo_cambio_url' => 'https://www.sunat.gob.pe/a/txt/tipoCambio.txt',
            'services.sunat.tipo_cambio_mes_url' => 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://www.sunat.gob.pe/a/txt/tipoCambio.txt' => Http::response($cuerpo, 200),
            'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias/listarTipoCambio' => Http::response($filas, 200),
        ]);
    }

    private function usuario(): User
    {
        $estudio = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Test', 'correo' => 'estudio@test.com', 'activo' => true]
        );

        return User::factory()->create([
            'estudio_id' => $estudio->id,
            'name' => 'Sebastian',
            'rol' => 'admin',
            'activo' => true,
        ]);
    }
}
