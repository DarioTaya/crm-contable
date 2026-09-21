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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WhatsAppService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WhatsAppService;
        $this->app->instance(WhatsAppService::class, $this->service);
    }

    public function test_normalizar_telefono_handles_various_formats(): void
    {
        // 9 dígitos estándar en Perú -> agrega +51
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('987654321'));

        // Con espacios y guiones
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('987 654 321'));
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('987-654-321'));

        // Ya con +51
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('+51987654321'));
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('+51 987 654 321'));

        // Con 51 al inicio sin +
        $this->assertEquals('+51987654321', $this->service->normalizarTelefono('51987654321'));

        // Internacional genérico
        $this->assertEquals('+12345678901', $this->service->normalizarTelefono('+1 (234) 567-8901'));

        // Números inválidos o vacíos
        $this->assertNull($this->service->normalizarTelefono(''));
        $this->assertNull($this->service->normalizarTelefono(null));
        $this->assertNull($this->service->normalizarTelefono('123'));
        $this->assertNull($this->service->normalizarTelefono('abcde'));
    }

    public function test_formatear_mensaje_alerta_contains_required_structure(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'COMERCIALIZADORA DEL SUR SAC',
            'ruc' => '20777888999',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $alerta = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'ORDEN DE PAGO N° 011-001-0987654',
            'contenido' => 'Deuda en cobranza por concepto de IGV.',
            'fecha_recepcion' => Carbon::parse('2026-09-16 10:30:00'),
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
        ]);

        $mensaje = $this->service->formatearMensajeAlerta($alerta);

        $this->assertStringContainsString('🚨 ALERTA SUNAT DETECTADA', $mensaje);
        $this->assertStringContainsString('Empresa: COMERCIALIZADORA DEL SUR SAC', $mensaje);
        $this->assertStringContainsString('Asunto: ORDEN DE PAGO N° 011-001-0987654', $mensaje);
        $this->assertStringContainsString('Prioridad: Alta', $mensaje);
        $this->assertStringContainsString('Fecha: 16/09/2026 10:30', $mensaje);
    }

    public function test_formatear_mensaje_prueba_contains_expected_fields(): void
    {
        $mensajePrueba = $this->service->formatearMensajePrueba('Grupo DC Soluciones Contables SAC');

        $this->assertStringContainsString('🚨 ALERTA SUNAT DETECTADA (PRUEBA)', $mensajePrueba);
        $this->assertStringContainsString('Empresa: Grupo DC Soluciones Contables SAC', $mensajePrueba);
        $this->assertStringContainsString('NOTIFICACIÓN DE PRUEBA', $mensajePrueba);
        $this->assertStringContainsString('Prioridad: Alta', $mensajePrueba);
    }

    public function test_enviar_mensaje_with_custom_runner(): void
    {
        $llamadas = [];
        $this->service->setCustomRunner(function ($destinatario, $mensaje) use (&$llamadas) {
            $llamadas[] = compact('destinatario', 'mensaje');

            return [
                'success' => true,
                'destinatario' => $destinatario,
                'message' => "Despachado a {$destinatario}",
            ];
        });

        $res = $this->service->enviarMensaje('987654321', 'Hola Mundo');

        $this->assertTrue($res['success']);
        $this->assertCount(1, $llamadas);
        $this->assertEquals('+51987654321', $llamadas[0]['destinatario']);
        $this->assertEquals('Hola Mundo', $llamadas[0]['mensaje']);
    }

    public function test_post_test_whatsapp_endpoint_success(): void
    {
        $this->service->setCustomRunner(function ($destinatario, $mensaje) {
            return [
                'success' => true,
                'destinatario' => $destinatario,
                'message' => 'Alerta de prueba despachada exitosamente.',
            ];
        });

        $response = $this->postJson(route('ajustes.test-whatsapp'), [
            'telefono_whatsapp' => '987654321',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Alerta de prueba despachada exitosamente.',
            'destinatario' => '+51987654321',
        ]);
    }

    public function test_post_test_whatsapp_endpoint_returns_422_if_no_phone_available(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update(['telefono_whatsapp' => null]);

        $response = $this->postJson(route('ajustes.test-whatsapp'), [
            'telefono_whatsapp' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
        ]);
        $this->assertStringContainsString('Debe ingresar o guardar un número de WhatsApp', $response->json('message'));
    }

    public function test_sync_sunat_buzon_dispatches_whatsapp_when_enabled_and_matches_criteria(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'notificar_whatsapp' => true,
            'nivel_alerta_whatsapp' => 'Solo Altas',
            'telefono_whatsapp' => '987654321',
            'frecuencia_cron_horas' => 2,
            'ultima_sincronizacion_at' => null,
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST WHATSAPP SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'USERWA',
            'clave_sol' => 'PASSWA',
        ]);

        // Simular scraper que devuelve 1 alerta Alta y 1 alerta Media
        $syncService = new SunatBuzonSyncService;
        $syncService->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'ORDEN DE PAGO COACTIVA URGENTE',
                    'fecha_recepcion' => '2026-09-16 12:00:00',
                    'contenido' => '<p>Detalle de orden de pago coactiva.</p>',
                ],
                [
                    'asunto' => 'RECORDATORIO CRONOGRAMA DECLARACIONES',
                    'fecha_recepcion' => '2026-09-16 13:00:00',
                    'contenido' => '<p>Aviso informativo de vencimiento.</p>',
                ],
            ];
        });
        $this->app->instance(SunatBuzonSyncService::class, $syncService);

        $despachos = [];
        $this->service->setCustomRunner(function ($destinatario, $mensaje) use (&$despachos) {
            $despachos[] = compact('destinatario', 'mensaje');

            return [
                'success' => true,
                'destinatario' => $destinatario,
                'message' => 'Despachado',
            ];
        });

        $exitCode = Artisan::call('buzon:sync-sunat', ['--force' => true]);

        $this->assertEquals(0, $exitCode);

        // Como el filtro es 'Solo Altas', solo debió despacharse la Orden de Pago (Alta)
        $this->assertCount(1, $despachos);
        $this->assertEquals('+51987654321', $despachos[0]['destinatario']);
        $this->assertStringContainsString('ORDEN DE PAGO COACTIVA URGENTE', $despachos[0]['mensaje']);
        $this->assertStringNotContainsString('RECORDATORIO CRONOGRAMA', $despachos[0]['mensaje']);
    }

    public function test_sync_sunat_buzon_dispatches_all_alerts_when_nivel_is_todas(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'notificar_whatsapp' => true,
            'nivel_alerta_whatsapp' => 'Todas',
            'telefono_whatsapp' => '987654321',
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TODAS ALERTAS SAC',
            'ruc' => '20999888111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'USERALL',
            'clave_sol' => 'PASSALL',
        ]);

        $syncService = new SunatBuzonSyncService;
        $syncService->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'RESOLUCIÓN DE COBRANZA COACTIVA',
                    'fecha_recepcion' => '2026-09-16 14:00:00',
                    'contenido' => '<p>Resolución coactiva</p>',
                ],
                [
                    'asunto' => 'AVISO INFORMATIVO SIRE',
                    'fecha_recepcion' => '2026-09-16 14:15:00',
                    'contenido' => '<p>Aviso informativo</p>',
                ],
            ];
        });
        $this->app->instance(SunatBuzonSyncService::class, $syncService);

        $despachos = [];
        $this->service->setCustomRunner(function ($destinatario, $mensaje) use (&$despachos) {
            $despachos[] = compact('destinatario', 'mensaje');

            return [
                'success' => true,
                'destinatario' => $destinatario,
                'message' => 'Despachado',
            ];
        });

        $exitCode = Artisan::call('buzon:sync-sunat', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
        // Cuando nivel_alerta_whatsapp == 'Todas', deben despacharse ambas alertas
        $this->assertCount(2, $despachos);
    }

    public function test_sync_sunat_buzon_does_not_dispatch_when_notificar_whatsapp_is_disabled(): void
    {
        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'notificar_whatsapp' => false,
            'telefono_whatsapp' => '987654321',
        ]);

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE WA DESACTIVADO SAC',
            'ruc' => '20555666777',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'USERDIS',
            'clave_sol' => 'PASSDIS',
        ]);

        $syncService = new SunatBuzonSyncService;
        $syncService->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'RESOLUCIÓN DE MULTA TRIBUTARIA',
                    'fecha_recepcion' => '2026-09-16 15:00:00',
                    'contenido' => '<p>Multa tributaria</p>',
                ],
            ];
        });
        $this->app->instance(SunatBuzonSyncService::class, $syncService);

        $despachos = [];
        $this->service->setCustomRunner(function ($destinatario, $mensaje) use (&$despachos) {
            $despachos[] = compact('destinatario', 'mensaje');

            return ['success' => true, 'destinatario' => $destinatario, 'message' => 'Despachado'];
        });

        $exitCode = Artisan::call('buzon:sync-sunat', ['--force' => true]);

        $this->assertEquals(0, $exitCode);
        $this->assertEmpty($despachos, 'No debe despacharse ningún WhatsApp si notificar_whatsapp está desactivado.');
    }

    public function test_enviar_mensaje_makes_http_post_to_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/send-message' => Http::response([
                'success' => true,
                'message' => 'Mensaje enviado correctamente vía WhatsApp',
                'phone' => '51987654321',
            ], 200),
        ]);

        $res = $this->service->enviarMensaje('987654321', 'Mensaje de prueba real');

        $this->assertTrue($res['success']);
        $this->assertEquals('+51987654321', $res['destinatario']);
        $this->assertStringContainsString('Mensaje enviado correctamente', $res['message']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:3001/send-message'
                && $request['phone'] === '+51987654321'
                && $request['message'] === 'Mensaje de prueba real';
        });
    }

    public function test_enviar_mensaje_gracefully_handles_offline_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/send-message' => fn () => throw new ConnectionException('Connection refused to localhost:3001'),
        ]);

        $res = $this->service->enviarMensaje('987654321', 'Mensaje');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('microservicio de WhatsApp (puerto 3001) no está disponible', $res['message']);
    }

    public function test_enviar_mensaje_handles_503_qr_not_scanned_error_from_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/send-message' => Http::response([
                'success' => false,
                'error' => 'El robot de WhatsApp aún no está listo o autenticado. Escanee el código QR en la consola terminal del servidor.',
            ], 503),
        ]);

        $res = $this->service->enviarMensaje('987654321', 'Mensaje');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('El robot de WhatsApp aún no está listo', $res['message']);
    }

    public function test_get_whatsapp_status_endpoint_returns_microservice_state(): void
    {
        Http::fake([
            'http://localhost:3001/status' => Http::response([
                'status' => 'qr_ready',
                'qr' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
                'ready' => false,
            ], 200),
        ]);

        $response = $this->getJson(route('ajustes.whatsapp-status'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'qr_ready',
            'qr' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...',
            'ready' => false,
        ]);
    }

    public function test_get_whatsapp_status_endpoint_handles_offline_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/status' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $response = $this->getJson(route('ajustes.whatsapp-status'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'offline',
            'qr' => null,
            'ready' => false,
        ]);
    }

    public function test_post_whatsapp_logout_endpoint_proxies_to_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/logout' => Http::response([
                'success' => true,
                'message' => 'Dispositivo desvinculado exitosamente.',
            ], 200),
        ]);

        $response = $this->postJson(route('ajustes.whatsapp-logout'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Dispositivo desvinculado exitosamente.',
        ]);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:3001/logout';
        });
    }

    public function test_post_whatsapp_logout_endpoint_handles_offline_error(): void
    {
        Http::fake([
            'http://localhost:3001/logout' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $response = $this->postJson(route('ajustes.whatsapp-logout'));

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
        ]);
    }

    public function test_detener_servicio_sends_shutdown_post_to_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/shutdown' => Http::response([
                'success' => true,
                'message' => 'Servidor de WhatsApp cerrándose...',
            ], 200),
        ]);

        $res = $this->service->detenerServicio();

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('detenido', $res['message']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:3001/shutdown';
        });
    }

    public function test_restablecer_sesion_sends_reset_session_post_to_microservice(): void
    {
        Http::fake([
            'http://localhost:3001/reset-session' => Http::response([
                'success' => true,
                'message' => 'Sesión restablecida exitosamente.',
            ], 200),
        ]);

        $res = $this->service->restablecerSesion();

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Sesión restablecida exitosamente.', $res['message']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'http://localhost:3001/reset-session';
        });
    }
}
