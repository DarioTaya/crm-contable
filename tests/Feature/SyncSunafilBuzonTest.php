<?php

namespace Tests\Feature;

use App\Models\AjusteSistema;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Credencial;
use App\Services\SunafilBuzonSyncService;
use App\Services\WhatsAppService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncSunafilBuzonTest extends TestCase
{
    use RefreshDatabase;

    protected SunafilBuzonSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SunafilBuzonSyncService;
        $this->app->instance(SunafilBuzonSyncService::class, $this->service);
    }

    public function test_sunafil_buzon_sync_service_processes_alerts_and_deduplicates(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA SUNAFIL TEST SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'MODUSUARIO',
            'clave_sol' => 'MODCLAVE123',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'REQUERIMIENTO DE COMPARECENCIA INSPECTIVA N° 456-2026',
                    'fecha_recepcion' => '2026-09-10 09:30:00',
                    'contenido' => '<div class="cuerpo-notif">Se cita al empleador a comparecencia inspectiva virtual por verificación del cumplimiento sociolaboral.</div>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Alta',
                    'plataforma' => 'SUNAFIL',
                ],
                [
                    'asunto' => 'AVISO INFORMATIVO SOBRE REGISTRO DE TRABAJADORES',
                    'fecha_recepcion' => '2026-09-12 14:00:00',
                    'contenido' => '<p>Estimado empleador, recuerde que el alta de trabajadores debe efectuarse el primer día de labores.</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAFIL',
                ],
            ];
        });

        // 1ra Sincronización: Debe insertar 2 alertas con plataforma SUNAFIL
        $res1 = $this->service->syncCliente($cliente);

        $this->assertTrue($res1['success']);
        $this->assertEquals(2, $res1['nuevas']);
        $this->assertEquals(2, $res1['total']);

        $this->assertDatabaseCount('alertas_buzon', 2);
        $this->assertDatabaseHas('alertas_buzon', [
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'REQUERIMIENTO DE COMPARECENCIA INSPECTIVA N° 456-2026',
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
        ]);

        $alertaGuardada = AlertaBuzon::where('asunto', 'REQUERIMIENTO DE COMPARECENCIA INSPECTIVA N° 456-2026')->first();
        $this->assertNotNull($alertaGuardada);
        $this->assertEquals('SUNAFIL', $alertaGuardada->plataforma);
        $this->assertStringContainsString('cuerpo-notif', $alertaGuardada->contenido);

        // 2da Sincronización: Con los mismos datos, NO debe duplicar registros
        $res2 = $this->service->syncCliente($cliente);
        $this->assertTrue($res2['success']);
        $this->assertEquals(0, $res2['nuevas']);
        $this->assertEquals(2, $res2['total']);
        $this->assertDatabaseCount('alertas_buzon', 2);
    }

    public function test_sunafil_garbage_headers_are_strictly_ignored(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA GARBAGE TEST SAC',
            'ruc' => '20999888777',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'SPAMUSER',
            'clave_sol' => 'SPAMPASS',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                ['asunto' => 'Nro', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Número', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Fecha', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Asunto', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Total', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                // 1 sola alerta legítima
                [
                    'asunto' => 'NOTIFICACIÓN DE ACTA DE INFRACCIÓN LABORAL N° 099',
                    'fecha_recepcion' => '2026-09-03 10:00:00',
                    'contenido' => '<p>Estimado empleador, se notifica la resolución de multa...</p>',
                ],
            ];
        });

        $res = $this->service->syncCliente($cliente);

        $this->assertTrue($res['success']);
        $this->assertEquals(1, $res['total']);
        $this->assertEquals(1, $res['nuevas']);

        $this->assertDatabaseCount('alertas_buzon', 1);
        $this->assertDatabaseHas('alertas_buzon', [
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'NOTIFICACIÓN DE ACTA DE INFRACCIÓN LABORAL N° 099',
            'prioridad' => 'Alta',
        ]);
    }

    public function test_sunafil_labor_dictionary_marks_all_critical_keywords_as_high_priority(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA DICCIONARIO LABORAL SAC',
            'ruc' => '20888777666',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'DICTUSER',
            'clave_sol' => 'DICTPASS',
        ]);

        $palabrasAlta = [
            'CARTAS DISUASIVAS GENERAL',
            'CARTA INDUCTIVA POR REGISTRO T-REGISTRO',
            'Resolución Subdirectoral N° 120-2026',
            'Acta de Infracción Laboral Grave',
            'Acción Previa de Formalización',
            'ALERTA PREVENTIVA SST EN EMPRESA',
            'Cédula de Comparecencia Inspectiva',
            'Notificación de Infracción Grave',
            'Orden de Diligencia Inspectiva',
            'Resolución de Multa N° 450-2026-SUNAFIL',
            'Requerimiento de Adopción de Medida Inspectiva',
            'Resolución Subdirectoral de Sanción',
            'Medida Inspectiva de Requerimiento',
        ];

        $this->service->setCustomRunner(function () use ($palabrasAlta) {
            $items = [];
            foreach ($palabrasAlta as $index => $asunto) {
                $items[] = [
                    'asunto' => $asunto,
                    'fecha_recepcion' => sprintf('2026-09-%02d 10:00:00', ($index + 1)),
                    'contenido' => "<p>Contenido oficial para {$asunto}</p>",
                ];
            }

            return $items;
        });

        $res = $this->service->syncCliente($cliente);

        $this->assertTrue($res['success']);
        $this->assertEquals(count($palabrasAlta), $res['total']);

        foreach ($palabrasAlta as $asunto) {
            $this->assertDatabaseHas('alertas_buzon', [
                'cliente_id' => $cliente->id,
                'plataforma' => 'SUNAFIL',
                'asunto' => $asunto,
                'prioridad' => 'Alta',
            ]);
        }
    }

    public function test_sunafil_table_headers_and_sidebar_menus_are_strictly_filtered_out(): void
    {
        $this->assertTrue($this->service->esSpamOBasura('Tipo de Requerimiento'));
        $this->assertTrue($this->service->esSpamOBasura('Fecha de Depósito'));
        $this->assertTrue($this->service->esSpamOBasura('Registro'));
        $this->assertTrue($this->service->esSpamOBasura('Fecha Acuse de Recibo'));
        $this->assertTrue($this->service->esSpamOBasura('Página de inicio'));
        $this->assertTrue($this->service->esSpamOBasura('Sunafil te Orienta'));
        $this->assertTrue($this->service->esSpamOBasura('Actos Administrativos'));
        $this->assertTrue($this->service->esSpamOBasura('Acciones Previas'));
        $this->assertTrue($this->service->esSpamOBasura('Notificaciones de Fiscalización'));
        $this->assertTrue($this->service->esSpamOBasura('Ningún dato disponible en esta tabla'));
        $this->assertFalse($this->service->esSpamOBasura('CARTAS DISUASIVAS GENERAL'));
        $this->assertFalse($this->service->esSpamOBasura('CARTA INDUCTIVA'));
    }

    public function test_sunafil_scraper_script_contains_sniper_navigation_and_filters(): void
    {
        $scriptPath = base_path('scripts/sunafil_buzon.cjs');
        $this->assertFileExists($scriptPath);
        $scriptContent = file_get_contents($scriptPath);

        $this->assertStringContainsString('Actos Administrativos', $scriptContent);
        $this->assertStringContainsString('Acciones Previas', $scriptContent);
        $this->assertStringContainsString('Notificaciones de Fiscalización', $scriptContent);
        $this->assertStringContainsString('Notificaciones de Cobranza', $scriptContent);
        $this->assertStringContainsString('Formalización', $scriptContent);
        $this->assertStringContainsString('Seguridad y Salud en el Trabajo', $scriptContent);
        $this->assertStringContainsString('te orienta', $scriptContent);
        $this->assertStringContainsString('pagina de inicio', $scriptContent);
        $this->assertStringContainsString('Fecha Acuse de Recibo', $scriptContent);
    }

    public function test_sunafil_pdf_fallback_content_assigned_when_body_is_empty_or_short(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA PDF SUNAFIL SAC',
            'ruc' => '20777666555',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'PDFUSER',
            'clave_sol' => 'PDFPASS',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'NOTIFICACIÓN CASILLA SUNAFIL CON ANEXO PDF',
                    'fecha_recepcion' => '2026-09-05 15:00:00',
                    'contenido' => '<span>Ver PDF</span>',
                ],
            ];
        });

        $res = $this->service->syncCliente($cliente);
        $this->assertTrue($res['success']);

        $alerta = AlertaBuzon::where('asunto', 'NOTIFICACIÓN CASILLA SUNAFIL CON ANEXO PDF')->first();
        $this->assertNotNull($alerta);
        $this->assertEquals('SUNAFIL', $alerta->plataforma);
        $this->assertStringContainsString('Casilla Electrónica de SUNAFIL', $alerta->contenido);
    }

    public function test_sunafil_sync_all_iterates_active_clients(): void
    {
        $cliente1 = Cliente::create([
            'razon_social' => 'CLIENTE SUNAFIL ACTIVO 1 SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente1->id,
            'usuario_sol' => 'USER1',
            'clave_sol' => 'PASS1',
        ]);

        $cliente2 = Cliente::create([
            'razon_social' => 'CLIENTE SUNAFIL ACTIVO 2 SRL',
            'ruc' => '20222222222',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente2->id,
            'usuario_sol' => 'USER2',
            'clave_sol' => 'PASS2',
        ]);

        $this->service->setCustomRunner(function ($ruc) {
            return [
                [
                    'asunto' => "ALERTA SUNAFIL PARA RUC {$ruc}",
                    'fecha_recepcion' => '2026-09-14 10:00:00',
                    'contenido' => "<p>Detalle para RUC {$ruc}</p>",
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAFIL',
                ],
            ];
        });

        $resAll = $this->service->syncAll();

        $this->assertEquals(2, $resAll['total_clientes']);
        $this->assertEquals(2, $resAll['clientes_procesados']);
        $this->assertEquals(2, $resAll['nuevas_alertas']);
        $this->assertEmpty($resAll['errores']);
        $this->assertDatabaseCount('alertas_buzon', 2);
    }

    public function test_artisan_command_buzon_sync_sunafil_executes_successfully(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE CMD SUNAFIL SAC',
            'ruc' => '20444444444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'CMDUSER',
            'clave_sol' => 'CMDPASS',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'NOTIFICACIÓN COMANDO SUNAFIL',
                    'fecha_recepcion' => '2026-09-15 12:00:00',
                    'contenido' => '<p>Contenido comando SUNAFIL</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Baja',
                    'plataforma' => 'SUNAFIL',
                ],
            ];
        });

        $exitCode = Artisan::call('buzon:sync-sunafil');
        $this->assertEquals(0, $exitCode);

        $ajustes = AjusteSistema::first();
        $this->assertNotNull($ajustes->ultima_sincronizacion_sunafil_at);

        // Prueba con RUC específico
        $exitCodeRuc = Artisan::call('buzon:sync-sunafil', ['ruc' => '20444444444']);
        $this->assertEquals(0, $exitCodeRuc);
    }

    public function test_post_alertas_sync_sunafil_endpoint_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE AJAX SUNAFIL SAC',
            'ruc' => '20555555555',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'AJAXUSER',
            'clave_sol' => 'AJAXPASS',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'NOTIFICACIÓN AJAX SUNAFIL',
                    'fecha_recepcion' => '2026-09-16 08:00:00',
                    'contenido' => '<p>Contenido AJAX SUNAFIL</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Alta',
                    'plataforma' => 'SUNAFIL',
                ],
            ];
        });

        $response = $this->postJson(route('alertas.sync-sunafil'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'total_clientes',
                'clientes_procesados',
                'nuevas_alertas',
            ],
        ]);
        $response->assertJson([
            'success' => true,
        ]);
    }

    public function test_post_cliente_sync_sunafil_endpoint_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE SINGLE SUNAFIL SAC',
            'ruc' => '20666666666',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente->id,
            'usuario_sol' => 'SINGLEUSER',
            'clave_sol' => 'SINGLEPASS',
        ]);

        $this->service->setCustomRunner(function () {
            return [
                [
                    'asunto' => 'NOTIFICACIÓN SINGLE SUNAFIL',
                    'fecha_recepcion' => '2026-09-16 11:30:00',
                    'contenido' => '<p>Contenido único SUNAFIL</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAFIL',
                ],
            ];
        });

        $response = $this->postJson(route('clientes.sunafil.sync', $cliente));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nuevas' => 1,
            'total' => 1,
        ]);
    }

    public function test_scheduler_registers_buzon_sync_sunafil_hourly_at_30(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasSunafilSync = $events->contains(function ($event) {
            return str_contains($event->command, 'buzon:sync-sunafil') && $event->expression === '30 * * * *';
        });

        $this->assertTrue($hasSunafilSync, 'El comando buzon:sync-sunafil debe estar programado con expresión 30 * * * * (hourlyAt(30)).');
    }

    public function test_whatsapp_message_formatting_for_sunafil(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA NOTIFICADA SUNAFIL SAC',
            'ruc' => '20777888999',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $alerta = AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAFIL',
            'asunto' => 'REQUERIMIENTO INSPECTIVO N° 999-2026',
            'contenido' => '<p>Contenido laboral</p>',
            'fecha_recepcion' => '2026-09-16 10:00:00',
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
        ]);

        /** @var WhatsAppService $whatsappService */
        $whatsappService = app(WhatsAppService::class);
        $mensaje = $whatsappService->formatearMensajeAlerta($alerta);

        $this->assertStringContainsString('🚨 ALERTA SUNAFIL DETECTADA', $mensaje);
        $this->assertStringContainsString('EMPRESA NOTIFICADA SUNAFIL SAC', $mensaje);
        $this->assertStringContainsString('REQUERIMIENTO INSPECTIVO N° 999-2026', $mensaje);
        $this->assertStringContainsString('Alta', $mensaje);
    }
}
