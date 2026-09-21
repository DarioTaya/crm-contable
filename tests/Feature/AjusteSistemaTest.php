<?php

namespace Tests\Feature;

use App\Models\AjusteSistema;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Credencial;
use App\Services\SunatBuzonSyncService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AjusteSistemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_settings_creates_default_row_if_none_exists(): void
    {
        $this->assertDatabaseCount('ajustes_sistema', 0);

        $ajustes = AjusteSistema::getSettings();

        $this->assertEquals(1, $ajustes->id);
        $this->assertEquals('Grupo DC Soluciones Contables SAC', $ajustes->razon_social);
        $this->assertEquals('20601234567', $ajustes->ruc);
        $this->assertEquals('Sebastián Taya Yucra', $ajustes->representante);
        $this->assertEquals('contacto@grupodc.pe', $ajustes->correo_corporativo);
        $this->assertEquals('987654321', $ajustes->telefono_whatsapp);
        $this->assertEquals(8, $ajustes->frecuencia_cron_horas);
        $this->assertFalse($ajustes->notificar_whatsapp);
        $this->assertEquals('Solo Altas', $ajustes->nivel_alerta_whatsapp);
        $this->assertNull($ajustes->ultima_sincronizacion_at);

        $this->assertDatabaseCount('ajustes_sistema', 1);
    }

    public function test_get_settings_retrieves_existing_singleton_record(): void
    {
        AjusteSistema::create([
            'id' => 1,
            'razon_social' => 'ESTUDIO TEST PERSONALIZADO SAC',
            'ruc' => '20999999999',
            'representante' => 'Admin Test',
            'correo_corporativo' => 'admin@test.pe',
            'telefono_whatsapp' => '999888777',
            'frecuencia_cron_horas' => 4,
            'notificar_whatsapp' => true,
            'nivel_alerta_whatsapp' => 'Todas',
            'ultima_sincronizacion_at' => null,
        ]);

        $ajustes = AjusteSistema::getSettings();

        $this->assertEquals('ESTUDIO TEST PERSONALIZADO SAC', $ajustes->razon_social);
        $this->assertEquals(4, $ajustes->frecuencia_cron_horas);
        $this->assertTrue($ajustes->notificar_whatsapp);
        $this->assertDatabaseCount('ajustes_sistema', 1);
    }

    public function test_ajustes_index_page_loads_successfully_with_data(): void
    {
        $response = $this->get(route('ajustes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('ajustes.index');
        $response->assertSee('Ajustes del Sistema');
        $response->assertSee('Perfil del Estudio Contable');
        $response->assertSee('Motor de Automatización (RPA)');
        $response->assertSee('Integración WhatsApp');
        $response->assertSee('Beta');
        $response->assertSee('Grupo DC Soluciones Contables SAC');
    }

    public function test_ajustes_update_via_ajax_persists_changes_and_returns_json(): void
    {
        $payload = [
            'razon_social' => 'DC CONTADORES & ASOCIADOS SAC',
            'ruc' => '20123456789',
            'representante' => 'Carlos Mendoza Ruiz',
            'correo_corporativo' => 'gerencia@dccontadores.pe',
            'telefono_whatsapp' => '912345678',
            'frecuencia_cron_horas' => 4,
            'notificar_whatsapp' => '1',
            'nivel_alerta_whatsapp' => 'Todas',
        ];

        $response = $this->postJson(route('ajustes.update'), $payload);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Ajustes del sistema actualizados correctamente.',
            'ajustes' => [
                'id' => 1,
                'razon_social' => 'DC CONTADORES & ASOCIADOS SAC',
                'ruc' => '20123456789',
                'frecuencia_cron_horas' => 4,
                'notificar_whatsapp' => true,
                'nivel_alerta_whatsapp' => 'Todas',
            ],
        ]);

        $this->assertDatabaseHas('ajustes_sistema', [
            'id' => 1,
            'razon_social' => 'DC CONTADORES & ASOCIADOS SAC',
            'ruc' => '20123456789',
            'frecuencia_cron_horas' => 4,
            'notificar_whatsapp' => true,
            'nivel_alerta_whatsapp' => 'Todas',
        ]);
    }

    public function test_ajustes_update_via_standard_form_redirects_with_flash_message(): void
    {
        $payload = [
            'razon_social' => 'DC CONTADORES SAC',
            'ruc' => '20123456789',
            'representante' => 'Carlos Mendoza',
            'correo_corporativo' => 'gerencia@dccontadores.pe',
            'telefono_whatsapp' => '912345678',
            'frecuencia_cron_horas' => 12,
            'notificar_whatsapp' => '0',
            'nivel_alerta_whatsapp' => 'Solo Altas',
        ];

        $response = $this->post(route('ajustes.update'), $payload);

        $response->assertRedirect(route('ajustes.index'));
        $response->assertSessionHas('success', 'Ajustes del sistema actualizados correctamente.');

        $this->assertDatabaseHas('ajustes_sistema', [
            'id' => 1,
            'frecuencia_cron_horas' => 12,
            'notificar_whatsapp' => false,
        ]);
    }

    public function test_ajustes_update_validation_fails_with_invalid_data(): void
    {
        $invalidPayload = [
            'razon_social' => '',
            'ruc' => '12345',
            'representante' => '',
            'correo_corporativo' => 'not-an-email',
            'frecuencia_cron_horas' => 5,
            'nivel_alerta_whatsapp' => 'Invalido',
        ];

        $response = $this->postJson(route('ajustes.update'), $invalidPayload);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'razon_social',
            'ruc',
            'representante',
            'correo_corporativo',
            'frecuencia_cron_horas',
            'nivel_alerta_whatsapp',
        ]);
    }

    public function test_sync_sunat_buzon_skips_when_frecuencia_hours_have_not_elapsed(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'frecuencia_cron_horas' => 8,
            'ultima_sincronizacion_at' => Carbon::now()->subHours(2),
        ]);

        $mockSyncService = $this->createMock(SunatBuzonSyncService::class);
        $mockSyncService->expects($this->never())->method('syncAll');
        $this->app->instance(SunatBuzonSyncService::class, $mockSyncService);

        $exitCode = Artisan::call('buzon:sync-sunat');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Sincronización programada omitida', $output);
        $this->assertStringContainsString('frecuencia configurada es de cada 8 horas', $output);
    }

    public function test_sync_sunat_buzon_runs_when_force_option_is_passed_even_if_frequency_not_elapsed(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'frecuencia_cron_horas' => 8,
            'ultima_sincronizacion_at' => Carbon::now()->subHours(1),
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST FORCE SAC',
            'ruc' => '20987654321',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'FORCEUSER',
            'clave_sol' => 'FORCEPASS',
        ]);

        $syncService = new SunatBuzonSyncService;
        $syncService->setCustomRunner(function () {
            return [];
        });
        $this->app->instance(SunatBuzonSyncService::class, $syncService);

        $exitCode = Artisan::call('buzon:sync-sunat', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Iniciando sincronización masiva', $output);

        $ajustes->refresh();
        $this->assertNotNull($ajustes->ultima_sincronizacion_at);
        $this->assertTrue($ajustes->ultima_sincronizacion_at->isToday());
    }

    public function test_sync_sunat_buzon_runs_when_frecuencia_hours_have_elapsed(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'frecuencia_cron_horas' => 4,
            'ultima_sincronizacion_at' => Carbon::now()->subHours(5),
        ]);

        $syncService = new SunatBuzonSyncService;
        $syncService->setCustomRunner(function () {
            return [];
        });
        $this->app->instance(SunatBuzonSyncService::class, $syncService);

        $exitCode = Artisan::call('buzon:sync-sunat');

        $this->assertEquals(0, $exitCode);
        $output = Artisan::output();
        $this->assertStringContainsString('Iniciando sincronización masiva', $output);

        $ajustes->refresh();
        $this->assertTrue($ajustes->ultima_sincronizacion_at->diffInMinutes(now()) <= 1);
    }

    public function test_whatsapp_start_endpoint_returns_success(): void
    {
        $mockWs = $this->createMock(WhatsAppService::class);
        $mockWs->expects($this->once())
            ->method('iniciarServicio')
            ->willReturn(['success' => true, 'message' => 'Microservicio iniciado']);
        $this->app->instance(WhatsAppService::class, $mockWs);

        $response = $this->postJson(route('ajustes.whatsapp-start'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Microservicio iniciado',
        ]);
    }

    public function test_whatsapp_stop_endpoint_returns_success(): void
    {
        $mockWs = $this->createMock(WhatsAppService::class);
        $mockWs->expects($this->once())
            ->method('detenerServicio')
            ->willReturn(['success' => true, 'message' => 'Microservicio detenido']);
        $this->app->instance(WhatsAppService::class, $mockWs);

        $response = $this->postJson(route('ajustes.whatsapp-stop'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Microservicio detenido',
        ]);
    }

    public function test_whatsapp_restart_endpoint_returns_success(): void
    {
        $mockWs = $this->createMock(WhatsAppService::class);
        $mockWs->expects($this->once())
            ->method('reiniciarServicio')
            ->willReturn(['success' => true, 'message' => 'Microservicio reiniciado']);
        $this->app->instance(WhatsAppService::class, $mockWs);

        $response = $this->postJson(route('ajustes.whatsapp-restart'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Microservicio reiniciado',
        ]);
    }

    public function test_despachar_nuevas_alertas_ensures_strict_idempotency(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'notificar_whatsapp' => true,
            'telefono_whatsapp' => '987654321',
            'nivel_alerta_whatsapp' => 'Todas',
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA PRUEBA IDEMPOTENCIA SAC',
            'ruc' => '20111222333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $alertaYaNotificada = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'NOTIFICACION PREVIA',
            'contenido' => 'Detalle previo',
            'fecha_recepcion' => now()->subDay(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
            'notificado_whatsapp_at' => now()->subDay(),
        ]);

        $alertaNueva = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'NOTIFICACION NUEVA CRITICA',
            'contenido' => 'Detalle nuevo',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
            'notificado_whatsapp_at' => null,
        ]);

        $wsService = new WhatsAppService;
        $mensajesDespachados = [];
        $wsService->setCustomRunner(function (string $telefono, string $mensaje) use (&$mensajesDespachados) {
            $mensajesDespachados[] = ['telefono' => $telefono, 'mensaje' => $mensaje];

            return ['success' => true, 'message' => 'OK'];
        });

        $despachadas = $wsService->despacharNuevasAlertas([$alertaYaNotificada, $alertaNueva]);

        $this->assertEquals(1, $despachadas);
        $this->assertCount(1, $mensajesDespachados);
        $this->assertStringContainsString('NOTIFICACION NUEVA CRITICA', $mensajesDespachados[0]['mensaje']);

        $alertaNueva->refresh();
        $this->assertNotNull($alertaNueva->notificado_whatsapp_at);
        $this->assertTrue($alertaNueva->yaFueNotificadaWhatsApp());

        // Segunda llamada con la misma alerta: idempotencia total, 0 despachadas
        $segundaVuelta = $wsService->despacharNuevasAlertas([$alertaNueva]);
        $this->assertEquals(0, $segundaVuelta);
        $this->assertCount(1, $mensajesDespachados);
    }

    public function test_whatsapp_reset_session_endpoint_returns_success(): void
    {
        $mockWs = $this->createMock(WhatsAppService::class);
        $mockWs->expects($this->once())
            ->method('restablecerSesion')
            ->willReturn(['success' => true, 'message' => 'Sesión restablecida exitosamente.']);
        $this->app->instance(WhatsAppService::class, $mockWs);

        $response = $this->postJson(route('ajustes.whatsapp-reset-session'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Sesión restablecida exitosamente.',
        ]);
    }
}
