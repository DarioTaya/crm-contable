<?php

namespace Tests\Feature;

use App\Http\Controllers\ComprobanteController;
use App\Jobs\DownloadSunatXmlJob;
use App\Models\Cliente;
use App\Models\Comprobante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ComprobanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_comprobante_model_creation_and_relations(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA PRUEBA SIRE SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000123',
            'ruc_tercero' => '20987654321',
            'razon_social_tercero' => 'PROVEEDOR CENTRAL SAC',
            'moneda' => 'PEN',
            'base_imponible' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'estado_sire' => 'Propuesta',
            'ruta_xml' => 'comprobantes/20123456789/2026/09/RCE/01-F001-00000123.xml',
            'ruta_pdf' => 'comprobantes/20123456789/2026/09/RCE/01-F001-00000123.pdf',
            'ruta_cdr' => 'comprobantes/20123456789/2026/09/RCE/R-01-F001-00000123.zip',
        ]);

        $this->assertDatabaseHas('comprobantes', [
            'id' => $comprobante->id,
            'cliente_id' => $cliente->id,
            'serie' => 'F001',
            'correlativo' => '00000123',
            'tipo_registro' => 'RCE',
            'total' => 1180.00,
        ]);

        // Verificación de relaciones
        $this->assertEquals($cliente->id, $comprobante->cliente->id);
        $this->assertTrue($cliente->comprobantes->contains($comprobante));
        $this->assertEquals('F001-00000123', $comprobante->numero_comprobante);
        $this->assertEquals('Factura', $comprobante->descripcion_tipo_comprobante);
    }

    public function test_storage_path_strict_format_and_directory_creation(): void
    {
        $ruc = '20999888777';
        $periodo = '2026-09';
        $tipoRegistro = 'RCE';

        $rutaGenerada = Comprobante::storagePathFor($ruc, $periodo, $tipoRegistro);

        // Debe respetar estrictamente: comprobantes/{ruc_cliente}/{anio}/{mes}/{tipo_registro}
        $this->assertEquals("comprobantes/{$ruc}/2026/09/RCE", $rutaGenerada);

        $rutaVentas = Comprobante::storagePathFor($ruc, $periodo, 'rvie');
        $this->assertEquals("comprobantes/{$ruc}/2026/09/RVIE", $rutaVentas);

        // Helper de asegurar directorio en storage local
        $absDir = ComprobanteController::asegurarRutaStorage($ruc, $periodo, $tipoRegistro);
        $this->assertDirectoryExists($absDir);
        $this->assertStringContainsString("comprobantes/{$ruc}/2026/09/RCE", str_replace('\\', '/', $absDir));

        // Limpieza del directorio de prueba si se desea
        @rmdir($absDir);
    }

    public function test_global_comprobantes_index_view_and_combined_filters(): void
    {
        $clienteA = Cliente::create([
            'razon_social' => 'CLIENTE ALFA SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $clienteB = Cliente::create([
            'razon_social' => 'CLIENTE BETA SAC',
            'ruc' => '20222222222',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        // Comprobante 1: Cliente A, RCE (Compras), 2026-09
        Comprobante::create([
            'cliente_id' => $clienteA->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '100',
            'ruc_tercero' => '20555555555',
            'razon_social_tercero' => 'PROVEEDOR LOGISTICA SAC',
            'base_imponible' => 2000.00,
            'igv' => 360.00,
            'total' => 2360.00,
            'estado_sire' => 'Aceptado',
        ]);

        // Comprobante 2: Cliente A, RVIE (Ventas), 2026-09
        Comprobante::create([
            'cliente_id' => $clienteA->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RVIE',
            'tipo_comprobante' => '01',
            'serie' => 'F002',
            'correlativo' => '200',
            'ruc_tercero' => '20666666666',
            'razon_social_tercero' => 'CLIENTE FINAL SAC',
            'base_imponible' => 5000.00,
            'igv' => 900.00,
            'total' => 5900.00,
            'estado_sire' => 'Propuesta',
        ]);

        // Comprobante 3: Cliente B, RCE, 2026-08
        Comprobante::create([
            'cliente_id' => $clienteB->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '03',
            'serie' => 'B001',
            'correlativo' => '300',
            'ruc_tercero' => '10444444444',
            'razon_social_tercero' => 'TERCERO MENOR',
            'base_imponible' => 100.00,
            'igv' => 18.00,
            'total' => 118.00,
            'estado_sire' => 'Aceptado',
        ]);

        // 1. Acceso a vista global sin filtros: Debe sumar los 3 comprobantes
        $response = $this->get(route('comprobantes.index'));
        $response->assertStatus(200);
        $response->assertSee('Bóveda SIRE');
        $response->assertSee('Total Base Imponible');
        $response->assertSee('Total IGV');
        $response->assertSee('Total Facturado');
        $response->assertSee('7,100.00'); // 2000 + 5000 + 100
        $response->assertSee('1,278.00'); // 360 + 900 + 18
        $response->assertSee('8,378.00'); // 2360 + 5900 + 118
        $response->assertSee('Importar desde SUNAT');
        $response->assertSee('Descargar Paquete ZIP');
        $response->assertSee('Exportar Excel');

        // 2. Filtro por tipo_registro = RCE
        $responseRce = $this->get(route('comprobantes.index', ['tipo_registro' => 'RCE']));
        $responseRce->assertStatus(200);
        $responseRce->assertSee('2,100.00'); // 2000 + 100
        $responseRce->assertDontSee('5,900.00');

        // 3. Filtro por cliente_id
        $responseClienteA = $this->get(route('comprobantes.index', ['cliente_id' => $clienteA->id]));
        $responseClienteA->assertStatus(200);
        $responseClienteA->assertSee('7,000.00'); // 2000 + 5000
        $responseClienteA->assertDontSee('TERCERO MENOR');

        // 4. Filtro por periodo = 2026-09
        $responsePeriodo = $this->get(route('comprobantes.index', ['periodo' => '2026-09']));
        $responsePeriodo->assertStatus(200);
        $responsePeriodo->assertSee('7,000.00');
        $responsePeriodo->assertDontSee('2026-08');
    }

    public function test_download_file_endpoint_handles_existing_and_missing_files(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA DESCARGA SAC',
            'ruc' => '20333333333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000001',
            'total' => 100.00,
            'ruta_xml' => 'comprobantes/20333333333/2026/09/RCE/test.xml',
        ]);

        // Caso 1: Archivo físico aún no existe en disco local -> debe redirigir con error
        $resMissing = $this->get(route('comprobantes.download', [$comprobante, 'xml']));
        $resMissing->assertSessionHas('error');

        // Caso 2: Crear archivo en disco local y descargar
        $absPath = storage_path('app/'.$comprobante->ruta_xml);
        @mkdir(dirname($absPath), 0755, true);
        file_put_contents($absPath, '<Invoice>Test XML</Invoice>');

        $resFound = $this->get(route('comprobantes.download', [$comprobante, 'xml']));
        $resFound->assertStatus(200);
        $this->assertStringContainsString('attachment', $resFound->headers->get('content-disposition'));

        // Limpieza
        @unlink($absPath);
    }

    public function test_download_zip_packages_comprobantes(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA ZIP SAC',
            'ruc' => '20444444444',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RVIE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '999',
            'total' => 1500.00,
        ]);

        $response = $this->get(route('comprobantes.zip', ['cliente_id' => $cliente->id]));
        $response->assertStatus(200);
        $this->assertStringContainsString('.zip', $response->headers->get('content-disposition'));
    }

    public function test_export_excel_csv_returns_stream_with_bom(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA EXCEL SAC',
            'ruc' => '20555555555',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '777',
            'ruc_tercero' => '20888888888',
            'razon_social_tercero' => 'TERCERO DE PRUEBA SAC',
            'base_imponible' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'estado_sire' => 'Aceptado',
        ]);

        $response = $this->get(route('comprobantes.export', ['cliente_id' => $cliente->id]));
        $response->assertStatus(200);
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('content-type'));

        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringStartsWith(chr(0xEF).chr(0xBB).chr(0xBF), $content);
        $this->assertStringContainsString('TERCERO DE PRUEBA SAC', $content);
        $this->assertStringContainsString('1180.00', $content);
    }

    public function test_cliente_expediente_shows_sire_tab_with_client_comprobantes(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA EXPEDIENTE SIRE SAC',
            'ruc' => '20666666666',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '555',
            'ruc_tercero' => '20777777777',
            'razon_social_tercero' => 'PROVEEDOR EXPEDIENTE SAC',
            'base_imponible' => 800.00,
            'igv' => 144.00,
            'total' => 944.00,
            'estado_sire' => 'Propuesta',
        ]);

        $response = $this->get(route('clientes.sire', $cliente));
        $response->assertStatus(200);
        $response->assertSee('SIRE / Comprobantes');
        $response->assertSee('Bóveda SIRE — Expediente Digital');
        $response->assertSee('PROVEEDOR EXPEDIENTE SAC');
        $response->assertSee('944.00');
        $response->assertSee('XML');
        $response->assertSee('CDR');
        $response->assertSee('PDF');
    }

    protected function crearZipMockSire(string $nombreTxt, string $contenidoTxt): string
    {
        $tempZip = tempnam(sys_get_temp_dir(), 'sire_test_');
        $zip = new \ZipArchive;
        $zip->open($tempZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString($nombreTxt, $contenidoTxt);
        $zip->close();
        $bytes = (string) file_get_contents($tempZip);
        @unlink($tempZip);

        return $bytes;
    }

    public function test_can_import_rce_via_ajax_endpoint(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE AJAX IMPORT SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'client-id-test',
            'sunat_client_secret' => 'client-secret-test',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        $txtContent = "F001|00009999|01|20444555666|PROVEEDOR IMPORTADO SAC|PEN|2000.00|360.00|2360.00\n";
        $zipBytes = $this->crearZipMockSire('20111111111-RCE.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-ajax',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'numTicket' => '20260900009999',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900009999',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20111111111-RCE.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RCE',
            'periodo' => '2026-09',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'completado',
                'recarga' => true,
            ])
            ->assertJsonPath('data.importados', 1);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'F001',
            'correlativo' => '00009999',
            'ruc_tercero' => '20444555666',
            'tipo_registro' => 'RCE',
            'periodo' => '2026-09',
            'total' => 2360.00,
        ]);
    }

    public function test_can_import_rvie_via_ajax_endpoint(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE VENTAS SIRE SAC',
            'ruc' => '20777777777',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'client-id-rvie-test',
            'sunat_client_secret' => 'client-secret-rvie-test',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        $txtContent = "F001|00008888|01|20999000111|COMPRADOR CLIENTE SAC|PEN|4000.00|720.00|4720.00\n";
        $zipBytes = $this->crearZipMockSire('20777777777-RVIE.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-rvie-ajax',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvie/propuesta/web/propuesta/202609/exportapropuesta*' => Http::response([
                'numTicket' => '20260900008888',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900008888',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20777777777-RVIE.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RVIE',
            'periodo' => '2026-09',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'completado',
                'recarga' => true,
            ])
            ->assertJsonPath('data.importados', 1);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'F001',
            'correlativo' => '00008888',
            'ruc_tercero' => '20999000111',
            'tipo_registro' => 'RVIE',
            'periodo' => '2026-09',
            'total' => 4720.00,
        ]);
    }

    public function test_ajax_endpoint_returns_processing_status_when_ticket_is_in_process(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE ESPERA SAC',
            'ruc' => '20888888888',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'client-id-wait',
            'sunat_client_secret' => 'client-secret-wait',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-wait',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'numTicket' => '20260900007777',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900007777',
                'desEstadoProceso' => 'En proceso',
                'nomArchivo' => null,
            ], 200),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RCE',
            'periodo' => '2026-09',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'procesando',
                'mensaje' => 'Ticket generado en SUNAT. Procesando descarga...',
                'ticket' => '20260900007777',
            ]);
    }

    public function test_ajax_endpoint_polling_with_ticket_parameter_completes_download(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE POLLING SAC',
            'ruc' => '20999999999',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'client-id-poll',
            'sunat_client_secret' => 'client-secret-poll',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        $txtContent = "F001|00007777|01|20333444555|TERCERO POLLING SAC|PEN|1000.00|180.00|1180.00\n";
        $zipBytes = $this->crearZipMockSire('20999999999-RCE.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-poll',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900005555',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20999999999-RCE.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RCE',
            'periodo' => '2026-09',
            'ticket' => '20260900005555',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'completado',
                'recarga' => true,
            ])
            ->assertJsonPath('data.importados', 1);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'F001',
            'correlativo' => '00007777',
            'ruc_tercero' => '20333444555',
            'total' => 1180.00,
        ]);
    }

    public function test_import_endpoint_validates_required_fields(): void
    {
        $response = $this->postJson(route('comprobantes.importar'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id', 'registro', 'periodo']);
    }

    public function test_import_endpoint_rejects_unsupported_or_generic_registros(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE RECHAZO SAC',
            'ruc' => '20222222222',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        // Envío de 'Todos'
        $responseTodos = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'Todos',
            'periodo' => '2026-09',
        ]);

        $responseTodos->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Por favor, selecciona específicamente RCE o RVIE para importar.',
            ]);

        // Envío de valor no admitido
        $responseInvalido = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'INVENTARIOS',
            'periodo' => '2026-09',
        ]);

        $responseInvalido->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Por favor, selecciona específicamente RCE o RVIE para importar.',
            ]);
    }

    public function test_import_handles_sunat_api_exception_with_error_message(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE ERROR SAC',
            'ruc' => '20333333333',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'bad-client-id',
            'sunat_client_secret' => 'bad-secret',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'error' => 'invalid_client',
                'error_description' => 'Cliente no autorizado por SUNAT',
            ], 401),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RCE',
            'periodo' => '2026-09',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
            ]);

        $this->assertStringStartsWith('Error SUNAT 401:', (string) $response->json('message'));
        $this->assertStringContainsString('Cliente no autorizado por SUNAT', (string) $response->json('message'));
    }

    public function test_download_file_generates_documents_on_demand_when_routes_empty(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA ON DEMAND SAC',
            'ruc' => '20777888999',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000888',
            'ruc_tercero' => '20123456789',
            'razon_social_tercero' => 'PROVEEDOR ON DEMAND SAC',
            'moneda' => 'PEN',
            'base_imponible' => 500.00,
            'igv' => 90.00,
            'total' => 590.00,
            'estado_sire' => 'Propuesta',
            'ruta_xml' => null,
            'ruta_pdf' => null,
            'ruta_cdr' => null,
        ]);

        // Descarga de PDF on demand
        $resPdf = $this->get(route('comprobantes.download', [$comprobante, 'pdf']));
        $resPdf->assertOk();
        $this->assertStringContainsString('attachment', (string) $resPdf->headers->get('content-disposition'));
        $this->assertStringContainsString('.PDF', strtoupper((string) $resPdf->headers->get('content-disposition')));

        // Descarga de XML on demand
        $resXml = $this->get(route('comprobantes.download', [$comprobante, 'xml']));
        $resXml->assertOk();
        $this->assertStringContainsString('.XML', strtoupper((string) $resXml->headers->get('content-disposition')));

        // Descarga de CDR on demand
        $resCdr = $this->get(route('comprobantes.download', [$comprobante, 'cdr']));
        $resCdr->assertOk();
        $this->assertStringContainsString('.ZIP', strtoupper((string) $resCdr->headers->get('content-disposition')));

        // Comprobante debe tener actualizadas las rutas en BD
        $comprobante->refresh();
        $this->assertNotNull($comprobante->ruta_pdf);
        $this->assertNotNull($comprobante->ruta_xml);
        $this->assertNotNull($comprobante->ruta_cdr);
        $this->assertTrue($comprobante->tiene_pdf);
        $this->assertTrue($comprobante->tiene_xml);
        $this->assertTrue($comprobante->tiene_cdr);

        // Limpieza de archivos creados en storage
        @unlink(storage_path('app/'.$comprobante->ruta_pdf));
        @unlink(storage_path('app/'.$comprobante->ruta_xml));
        @unlink(storage_path('app/'.$comprobante->ruta_cdr));
    }

    public function test_sire_import_automatically_links_and_creates_all_three_documents(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE IMPORT DOCS SAC',
            'ruc' => '20888999000',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'client-id-docs',
            'sunat_client_secret' => 'client-secret-docs',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        $txtContent = "F001|00003333|01|20555666777|PROVEEDOR COMPLETO SAC|PEN|100.00|18.00|118.00\n";
        $zipBytes = $this->crearZipMockSire('20888999000-RCE.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-docs',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'numTicket' => '20260900003333',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900003333',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20888999000-RCE.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $response = $this->postJson(route('comprobantes.importar'), [
            'cliente_id' => $cliente->id,
            'registro' => 'RCE',
            'periodo' => '2026-09',
        ]);

        $response->assertOk();

        $comp = Comprobante::where('cliente_id', $cliente->id)->where('correlativo', '00003333')->first();
        $this->assertNotNull($comp);
        $this->assertNotNull($comp->ruta_pdf);
        $this->assertNotNull($comp->ruta_xml);
        $this->assertNotNull($comp->ruta_cdr);
        $this->assertFileExists(storage_path('app/'.$comp->ruta_pdf));
        $this->assertFileExists(storage_path('app/'.$comp->ruta_xml));
        $this->assertFileExists(storage_path('app/'.$comp->ruta_cdr));

        // Limpieza
        @unlink(storage_path('app/'.$comp->ruta_pdf));
        @unlink(storage_path('app/'.$comp->ruta_xml));
        @unlink(storage_path('app/'.$comp->ruta_cdr));
    }

    public function test_sync_status_endpoint_returns_reactive_state(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE SYNC STATUS SAC',
            'ruc' => '20777666555',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comp = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '777',
            'ruc_tercero' => '20111222333',
            'total' => 100,
            'estado_descarga' => 'disponible',
            'estado_xml' => 'disponible',
            'estado_cdr' => 'disponible',
            'estado_pdf' => 'disponible',
        ]);

        $response = $this->getJson(route('comprobantes.sync-status', ['ids' => [$comp->id]]));
        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('comprobantes.0.id', $comp->id);
        $response->assertJsonPath('comprobantes.0.estado_descarga', 'disponible');
    }

    public function test_reintentar_descarga_endpoint_dispatches_job(): void
    {
        Queue::fake();

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE REINTENTO SAC',
            'ruc' => '20666555444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comp = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '888',
            'ruc_tercero' => '20111222333',
            'total' => 100,
            'estado_descarga' => 'error',
        ]);

        $response = $this->postJson(route('comprobantes.reintentar-descarga', $comp));
        $response->assertOk();
        $response->assertJsonPath('success', true);

        Queue::assertPushed(DownloadSunatXmlJob::class, function ($job) use ($comp) {
            return $job->comprobante->id === $comp->id;
        });

        $comp->refresh();
        $this->assertEquals('pendiente', $comp->estado_descarga);
    }

    public function test_sincronizar_lote_endpoint_dispatches_pending_jobs(): void
    {
        Queue::fake();

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE LOTE SAC',
            'ruc' => '20555444333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comp1 = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '101',
            'ruc_tercero' => '20111222333',
            'total' => 100,
            'estado_descarga' => 'pendiente',
        ]);

        $comp2 = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '102',
            'ruc_tercero' => '20111222333',
            'total' => 200,
            'estado_descarga' => 'pendiente',
        ]);

        $response = $this->postJson(route('comprobantes.sincronizar-lote'), [
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
        ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('total', 2);

        Queue::assertPushed(DownloadSunatXmlJob::class, 2);
    }
}
