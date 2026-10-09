<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Credencial;
use App\Services\SunatBuzonSyncService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SyncSunatBuzonTest extends TestCase
{
    use RefreshDatabase;

    protected SunatBuzonSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new SunatBuzonSyncService;
        $this->app->instance(SunatBuzonSyncService::class, $this->service);
    }

    public function test_sunat_buzon_sync_service_processes_alerts_and_deduplicates(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA AUTOMATIZADA SAC',
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
                    'asunto' => 'ORDEN DE PAGO N° 011-001-0099881',
                    'fecha_recepcion' => '2026-09-10 09:30:00',
                    'contenido' => '<div class="cuerpo-resolucion">Se emite la presente <strong>Orden de Pago</strong> conforme al Art. 78 del Código Tributario por concepto de IGV Agosto 2026 por un monto de S/ 1,450.00 más intereses moratorios.</div>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Alta',
                    'plataforma' => 'SUNAT',
                ],
                [
                    'asunto' => 'RECORDATORIO DE VENCIMIENTO SIRE',
                    'fecha_recepcion' => '2026-09-12 14:00:00',
                    'contenido' => '<p>Estimado contribuyente, se recuerda que el cronograma de vencimiento para la propuesta de compras y ventas SIRE culmina el 18 de Setiembre.</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAT',
                ],
            ];
        });

        // 1ra Sincronización: Debe insertar 2 alertas
        $res1 = $this->service->syncCliente($cliente);

        $this->assertTrue($res1['success']);
        $this->assertEquals(2, $res1['nuevas']);
        $this->assertEquals(2, $res1['total']);

        $this->assertDatabaseCount('alertas_buzon', 2);
        $this->assertDatabaseHas('alertas_buzon', [
            'cliente_id' => $cliente->id,
            'asunto' => 'ORDEN DE PAGO N° 011-001-0099881',
            'prioridad' => 'Alta',
            'estado' => 'Pendiente',
        ]);

        $alertaGuardada = AlertaBuzon::where('asunto', 'ORDEN DE PAGO N° 011-001-0099881')->first();
        $this->assertNotNull($alertaGuardada);
        $this->assertStringContainsString('cuerpo-resolucion', $alertaGuardada->contenido);

        // 2da Sincronización: Con los mismos datos, NO debe duplicar registros
        $res2 = $this->service->syncCliente($cliente);
        $this->assertTrue($res2['success']);
        $this->assertEquals(0, $res2['nuevas']);
        $this->assertEquals(2, $res2['total']);
        $this->assertDatabaseCount('alertas_buzon', 2);
    }

    public function test_anti_spam_filter_and_garbage_headers_are_strictly_ignored(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA ANTI SPAM TEST SAC',
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
                ['asunto' => 'Periodo', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Tributo', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Total :', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Pago', 'fecha_recepcion' => '2026-09-01 08:00:00', 'contenido' => null],
                ['asunto' => 'Aviso de Propuesta RVIE generada', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'RVIE'],
                ['asunto' => 'Actualización de RCE del mes', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'RCE'],
                ['asunto' => 'Plataforma de confirmación de facturas', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'conf'],
                ['asunto' => 'Reporte Resumen diario', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'reporte'],
                ['asunto' => 'Emisión de Comprobantes de pago', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'comp'],
                ['asunto' => 'Nueva Factura Electrónica recibida', 'fecha_recepcion' => '2026-09-02 09:00:00', 'contenido' => 'fe'],
                // 1 sola alerta legítima
                [
                    'asunto' => 'NOTIFICACIÓN DE ESQUELA N° 0012345678',
                    'fecha_recepcion' => '2026-09-03 10:00:00',
                    'contenido' => '<p>Estimado contribuyente, se le notifica esquela de citación...</p>',
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
            'asunto' => 'NOTIFICACIÓN DE ESQUELA N° 0012345678',
            'prioridad' => 'Alta',
        ]);
    }

    public function test_tributary_dictionary_marks_all_critical_keywords_as_high_priority(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA DICCIONARIO SAC',
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
            'Esquela de Citación N° 100',
            'Comunicación de Omiso a Presentación',
            'Resolución de Multa Tributaria N° 200',
            'Notificación de Embargo en Forma de Retención',
            'Emisión de Valor de Cobranza',
            'Resolución Coactiva de Ejecución',
            'Requerimiento de Fiscalización Parcial',
            'Orden de Pago por Deuda Tributaria',
        ];

        $this->service->setCustomRunner(function () use ($palabrasAlta) {
            $items = [];
            foreach ($palabrasAlta as $index => $asunto) {
                $items[] = [
                    'asunto' => $asunto,
                    'fecha_recepcion' => "2026-09-0{$index} 10:00:00",
                    'contenido' => "<p>Contenido para {$asunto}</p>",
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
                'asunto' => $asunto,
                'prioridad' => 'Alta',
            ]);
        }
    }

    public function test_pdf_fields_and_faithful_content_preserved_on_sync(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA PDF TEST SAC',
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
                    'asunto' => 'NOTIFICACIÓN RESOLUCIÓN COACTIVA ANEXO PDF',
                    'numero_notificacion' => '0230010987654',
                    'fecha_recepcion' => '2026-09-05 15:00:00',
                    'contenido' => '<button>Ver PDF</button>',
                    'tiene_pdf' => true,
                    'ruta_pdf' => 'alertas/SUNAT/20777666555/0230010987654.pdf',
                    'nombre_archivo_pdf' => 'REC-0230010987654.pdf',
                ],
            ];
        });

        $res = $this->service->syncCliente($cliente);
        $this->assertTrue($res['success']);

        $alerta = AlertaBuzon::where('asunto', 'NOTIFICACIÓN RESOLUCIÓN COACTIVA ANEXO PDF')->first();
        $this->assertNotNull($alerta);
        $this->assertEquals('0230010987654', $alerta->numero_notificacion);
        $this->assertTrue($alerta->tiene_pdf);
        $this->assertEquals('alertas/SUNAT/20777666555/0230010987654.pdf', $alerta->ruta_pdf);
        $this->assertStringContainsString('<button>Ver PDF</button>', $alerta->contenido);
    }

    public function test_sync_all_iterates_all_active_clients_with_sol_credentials(): void
    {
        $cliente1 = Cliente::create([
            'razon_social' => 'CLIENTE ACTIVO 1 SAC',
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
            'razon_social' => 'CLIENTE ACTIVO 2 SRL',
            'ruc' => '20222222222',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        Credencial::create([
            'cliente_id' => $cliente2->id,
            'usuario_sol' => 'USER2',
            'clave_sol' => 'PASS2',
        ]);

        // Cliente inactivo (no debe sincronizarse)
        $clienteInactivo = Cliente::create([
            'razon_social' => 'CLIENTE INACTIVO EIRL',
            'ruc' => '20333333333',
            'regimen_tributario' => 'RUS',
            'estado' => false,
        ]);
        Credencial::create([
            'cliente_id' => $clienteInactivo->id,
            'usuario_sol' => 'USER3',
            'clave_sol' => 'PASS3',
        ]);

        $this->service->setCustomRunner(function ($ruc) {
            return [
                [
                    'asunto' => "ALERTA NOTIFICACIÓN PARA RUC {$ruc}",
                    'fecha_recepcion' => '2026-09-14 10:00:00',
                    'contenido' => "<p>Contenido detallado para RUC {$ruc}</p>",
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAT',
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

    public function test_artisan_command_buzon_sync_sunat_executes_successfully(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE COMANDO SAC',
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
                    'asunto' => 'NOTIFICACIÓN COMANDO TEST',
                    'fecha_recepcion' => '2026-09-15 12:00:00',
                    'contenido' => '<p>Contenido de prueba comando detallado</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Baja',
                    'plataforma' => 'SUNAT',
                ],
            ];
        });

        $exitCode = Artisan::call('buzon:sync-sunat');
        $this->assertEquals(0, $exitCode);

        // Prueba con RUC específico
        $exitCodeRuc = Artisan::call('buzon:sync-sunat', ['ruc' => '20444444444']);
        $this->assertEquals(0, $exitCodeRuc);
    }

    public function test_post_alertas_sync_all_endpoint_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE AJAX SAC',
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
                    'asunto' => 'NOTIFICACIÓN AJAX ENDPOINT',
                    'fecha_recepcion' => '2026-09-16 08:00:00',
                    'contenido' => '<p>Contenido AJAX detallado</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Alta',
                    'plataforma' => 'SUNAT',
                ],
            ];
        });

        $response = $this->postJson(route('alertas.sync-all'));

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

    public function test_post_cliente_sync_buzon_endpoint_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE SINGLE AJAX SAC',
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
                    'asunto' => 'NOTIFICACIÓN SINGLE CLIENTE',
                    'fecha_recepcion' => '2026-09-16 11:30:00',
                    'contenido' => '<p>Contenido único detallado</p>',
                    'estado' => 'Pendiente',
                    'prioridad' => 'Media',
                    'plataforma' => 'SUNAT',
                ],
            ];
        });

        $response = $this->postJson(route('clientes.buzon.sync', $cliente));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'nuevas' => 1,
            'total' => 1,
        ]);
    }

    public function test_scheduler_registers_buzon_sync_sunat_hourly(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $hasBuzonSync = $events->contains(function ($event) {
            return str_contains($event->command, 'buzon:sync-sunat') && $event->expression === '0 * * * *';
        });

        $this->assertTrue($hasBuzonSync, 'El comando buzon:sync-sunat debe estar programado con expresión 0 * * * * (hourly).');
    }
}
