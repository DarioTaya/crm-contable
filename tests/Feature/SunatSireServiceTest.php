<?php

namespace Tests\Feature;

use App\Exceptions\SunatApiException;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Services\SunatSireService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SunatSireServiceTest extends TestCase
{
    use RefreshDatabase;

    protected SunatSireService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SunatSireService;
        Cache::flush();
    }

    /**
     * Helper para crear un cliente con credenciales completas listas para API.
     */
    protected function crearClienteConCredenciales(array $overrides = []): Cliente
    {
        $cliente = Cliente::create(array_merge([
            'razon_social' => 'EMPRESA SERVICIO SIRE S.A.C.',
            'ruc' => '20601234567',
            'regimen_tributario' => 'General',
            'correo' => 'contacto@empresa.pe',
            'telefono' => '999888777',
            'estado' => true,
            'sunat_client_id' => 'client-id-uuid-test',
            'sunat_client_secret' => 'mi-secreto-encriptado-123',
        ], $overrides));

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
        ]);

        return $cliente;
    }

    /**
     * Verifica la obtención exitosa del token forzando una petición limpia a SUNAT según la directiva Fase 30.
     */
    public function test_can_get_access_token_successfully_forcing_clean_request(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'jwt-token-sunat-abc-12345',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->service->getAccessToken($cliente);

        $this->assertEquals('jwt-token-sunat-abc-12345', $token);
        Http::assertSentCount(1);
    }

    /**
     * Verifica que los datos sensibles se desencriptan y se inyectan correctamente en la petición a SUNAT según Sección 5.1 del Manual.
     */
    public function test_credentials_are_decrypted_and_sent_correctly_to_sunat(): void
    {
        $cliente = $this->crearClienteConCredenciales([
            'ruc' => '20555444333',
            'sunat_client_id' => 'my-api-client-id',
            'sunat_client_secret' => 'my-raw-secret-key',
        ]);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'token-test',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $this->service->getAccessToken($cliente);

        Http::assertSent(function (Request $request) use ($cliente) {
            $urlEsperada = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/'.$cliente->sunat_client_id.'/oauth2/token/';

            return $request->url() === $urlEsperada
                && $request->method() === 'POST'
                && $request['grant_type'] === 'password'
                && $request['scope'] === 'https://api-sire.sunat.gob.pe'
                && $request['client_id'] === 'my-api-client-id'
                && $request['client_secret'] === 'my-raw-secret-key'
                && $request['username'] === '20555444333MODDATOS'
                && $request['password'] === 'moddatos123';
        });
    }

    /**
     * Verifica que cualquier espacio accidental o caracter invisible en las credenciales sea recortado con trim.
     */
    public function test_credentials_are_trimmed_of_whitespace_before_request(): void
    {
        $cliente = $this->crearClienteConCredenciales([
            'ruc' => '  20601234567  ',
            'sunat_client_id' => " \t my-client-id-trimmed \n ",
            'sunat_client_secret' => "  my-secret-trimmed= \r\n ",
        ]);

        $credencial = $cliente->credencial;
        $credencial->usuario_sol = '  MODDATOS  ';
        $credencial->clave_sol = '  moddatos123  ';
        $credencial->save();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'token-clean',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->service->getAccessToken($cliente);
        $this->assertEquals('token-clean', $token);

        Http::assertSent(function (Request $request) {
            return $request['client_id'] === 'my-client-id-trimmed'
                && $request['client_secret'] === 'my-secret-trimmed='
                && $request['username'] === '20601234567MODDATOS'
                && $request['password'] === 'moddatos123';
        });
    }

    /**
     * Verifica que el parámetro $forzarRenovacion invalida la caché y solicita un token nuevo.
     */
    public function test_force_refresh_invalidates_cache_and_requests_new_token(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::sequence()
                ->push(['access_token' => 'token-primero', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'token-segundo', 'expires_in' => 3600], 200),
        ]);

        $token1 = $this->service->getAccessToken($cliente);
        $this->assertEquals('token-primero', $token1);
        Http::assertSentCount(1);

        // Forzar renovación
        $token2 = $this->service->getAccessToken($cliente, forzarRenovacion: true);
        $this->assertEquals('token-segundo', $token2);
        Http::assertSentCount(2);
    }

    /**
     * Verifica que clearTokenCache limpia la entrada de caché.
     */
    public function test_clear_token_cache_removes_entry(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Cache::put($this->service->getCacheKey($cliente), 'cached-token-val', 3600);
        $this->assertTrue($this->service->hasCachedToken($cliente));

        $this->service->clearTokenCache($cliente);
        $this->assertFalse($this->service->hasCachedToken($cliente));
    }

    /**
     * Verifica que lanza SunatApiException si el cliente no tiene sunat_client_id.
     */
    public function test_throws_exception_when_client_id_missing(): void
    {
        $cliente = $this->crearClienteConCredenciales([
            'sunat_client_id' => null,
        ]);

        $this->expectException(SunatApiException::class);
        $this->expectExceptionMessage('no tiene configurado el Client ID');

        $this->service->getAccessToken($cliente);
    }

    /**
     * Verifica que lanza SunatApiException si el cliente no tiene sunat_client_secret.
     */
    public function test_throws_exception_when_client_secret_missing(): void
    {
        $cliente = $this->crearClienteConCredenciales([
            'sunat_client_secret' => null,
        ]);

        $this->expectException(SunatApiException::class);
        $this->expectExceptionMessage('no tiene configurado el Client Secret');

        $this->service->getAccessToken($cliente);
    }

    /**
     * Verifica que lanza SunatApiException si el cliente no tiene credencial o usuario_sol.
     */
    public function test_throws_exception_when_usuario_sol_missing(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'SIN USUARIO SOL S.A.C.',
            'ruc' => '20111222333',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'valid-client-id',
            'sunat_client_secret' => 'valid-secret',
        ]);

        $this->expectException(SunatApiException::class);
        $this->expectExceptionMessage('no tiene registrado su Usuario SOL');

        $this->service->getAccessToken($cliente);
    }

    /**
     * Verifica que lanza SunatApiException si la clave_sol está vacía.
     */
    public function test_throws_exception_when_clave_sol_missing(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'SIN CLAVE SOL S.A.C.',
            'ruc' => '20111222334',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'valid-client-id',
            'sunat_client_secret' => 'valid-secret',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => null,
        ]);

        $this->expectException(SunatApiException::class);
        $this->expectExceptionMessage('no tiene registrada su Clave SOL');

        $this->service->getAccessToken($cliente);
    }

    /**
     * Verifica que procesa adecuadamente el error 400/401 con error_description de SUNAT.
     */
    public function test_handles_sunat_http_400_or_401_error_description(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'El usuario secundario o clave SOL ingresados no son válidos.',
            ], 400),
        ]);

        try {
            $this->service->getAccessToken($cliente);
            $this->fail('Se esperaba una SunatApiException');
        } catch (SunatApiException $e) {
            $this->assertStringStartsWith('Error SUNAT 400:', $e->getMessage());
            $this->assertStringContainsString('invalid_grant', $e->getMessage());
            $this->assertStringContainsString('El usuario secundario o clave SOL', $e->getMessage());
            $this->assertEquals(400, $e->getHttpStatus());
            $this->assertIsArray($e->getResponsePayload());
            $this->assertEquals('invalid_grant', $e->getResponsePayload()['error']);
        }
    }

    /**
     * Verifica que expone con precisión el cuerpo exacto devuelto por SUNAT en un HTTP 400.
     */
    public function test_handles_sunat_http_400_raw_body_exposure(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response('{"error":"invalid_request","error_description":"Missing parameter scope"}', 400),
        ]);

        try {
            $this->service->getAccessToken($cliente);
            $this->fail('Se esperaba una SunatApiException');
        } catch (SunatApiException $e) {
            $this->assertEquals(400, $e->getHttpStatus());
            $this->assertStringStartsWith('Error SUNAT 400:', $e->getMessage());
            $this->assertStringContainsString('Missing parameter scope', $e->getMessage());
        }
    }

    /**
     * Verifica que procesa adecuadamente un error 500 de servidor en SUNAT exponiendo el cuerpo de respuesta.
     */
    public function test_handles_sunat_http_500_server_error(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response('Internal Server Error', 500),
        ]);

        try {
            $this->service->getAccessToken($cliente);
            $this->fail('Se esperaba una SunatApiException');
        } catch (SunatApiException $e) {
            $this->assertStringStartsWith('Error SUNAT 500:', $e->getMessage());
            $this->assertStringContainsString('Internal Server Error', $e->getMessage());
            $this->assertEquals(500, $e->getHttpStatus());
        }
    }

    /**
     * Verifica que gestiona errores de timeout / fallo de red de forma amigable.
     */
    public function test_handles_connection_exception_or_timeout(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out after 30000 milliseconds');
        });

        try {
            $this->service->getAccessToken($cliente);
            $this->fail('Se esperaba una SunatApiException');
        } catch (SunatApiException $e) {
            $this->assertStringContainsString('No se pudo conectar con el servidor de autenticación de SUNAT', $e->getMessage());
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
        }
    }

    /**
     * Verifica que lanza excepción si SUNAT responde 200 pero sin la clave access_token.
     */
    public function test_throws_exception_when_response_lacks_access_token(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'status' => 'success',
                'other_info' => 'no_token_here',
            ], 200),
        ]);

        $this->expectException(SunatApiException::class);
        $this->expectExceptionMessage('no devolvió el access_token requerido');

        $this->service->getAccessToken($cliente);
    }

    /**
     * Helper para crear un archivo ZIP en memoria con un archivo de texto plano delimitado por pipes.
     */
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

    /**
     * Verifica la importación exitosa de la propuesta RCE mapeando datos de SUNAT mediante Ticket y ZIP.
     */
    public function test_can_import_rce_proposal_and_persist_comprobantes(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        $txtContent = "E001|00000100|01|20123456789|DISTRIBUIDORA LIMA S.A.C.|PEN|1000.00|180.00|1180.00\n".
                      "F002|00000200|01|20987654321|SERVICIOS INDUSTRIALES S.R.L.|PEN|500.00|90.00|590.00\n";
        $zipBytes = $this->crearZipMockSire('20601234567-RCE-202609.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token-rce',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'numTicket' => '20260900000001',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900000001',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20601234567-RCE-202609.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $resultado = $this->service->importarPropuestaRCE($cliente, '2026-09');

        $this->assertTrue($resultado['success']);
        $this->assertEquals(2, $resultado['importados']);
        $this->assertEquals(2, $resultado['total']);
        $this->assertEquals('2026-09', $resultado['periodo']);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'tipo_registro' => 'RCE',
            'periodo' => '2026-09',
            'serie' => 'E001',
            'correlativo' => '00000100',
            'ruc_tercero' => '20123456789',
            'razon_social_tercero' => 'DISTRIBUIDORA LIMA S.A.C.',
            'base_imponible' => 1000.00,
            'igv' => 180.00,
            'total' => 1180.00,
            'estado_sire' => 'Propuesta',
        ]);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'tipo_registro' => 'RCE',
            'periodo' => '2026-09',
            'serie' => 'F002',
            'correlativo' => '00000200',
            'ruc_tercero' => '20987654321',
            'razon_social_tercero' => 'SERVICIOS INDUSTRIALES S.R.L.',
            'base_imponible' => 500.00,
            'igv' => 90.00,
            'total' => 590.00,
            'estado_sire' => 'Propuesta',
        ]);
    }

    /**
     * Verifica que evita duplicados actualizando el registro existente con Serie, Correlativo y RUC Proveedor.
     */
    public function test_avoid_duplicates_by_updating_existing_comprobante(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        $txt1 = "F001|00001000|01|20111222333|PROVEEDOR ORIGINAL|PEN|100.00|18.00|118.00\n";
        $zip1 = $this->crearZipMockSire('20601234567-RCE-1.txt', $txt1);

        $txt2 = "F001|00001000|01|20111222333|PROVEEDOR MODIFICADO|PEN|200.00|36.00|236.00\n";
        $zip2 = $this->crearZipMockSire('20601234567-RCE-2.txt', $txt2);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token-rce',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::sequence()
                ->push(['numTicket' => '20260900000010'], 200)
                ->push(['numTicket' => '20260900000011'], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::sequence()
                ->push(['numTicket' => '20260900000010', 'desEstadoProceso' => 'Terminado', 'nomArchivo' => 'reporte1.zip'], 200)
                ->push(['numTicket' => '20260900000011', 'desEstadoProceso' => 'Terminado', 'nomArchivo' => 'reporte2.zip'], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::sequence()
                ->push($zip1, 200, ['Content-Type' => 'application/zip'])
                ->push($zip2, 200, ['Content-Type' => 'application/zip']),
        ]);

        // Primera importación
        $this->service->importarPropuestaRCE($cliente, '2026-09');
        $this->assertEquals(1, Comprobante::count());

        // Segunda importación del mismo comprobante con montos corregidos
        $this->service->importarPropuestaRCE($cliente, '2026-09');

        // Sigue habiendo un único registro en la base de datos (sin duplicados)
        $this->assertEquals(1, Comprobante::count());

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'F001',
            'correlativo' => '00001000',
            'ruc_tercero' => '20111222333',
            'razon_social_tercero' => 'PROVEEDOR MODIFICADO',
            'base_imponible' => 200.00,
            'total' => 236.00,
        ]);
    }

    /**
     * Verifica la importación exitosa de la propuesta RVIE mapeando ventas de SUNAT y guardando con tipo_registro = 'RVIE'.
     */
    public function test_can_import_rvie_proposal_and_persist_comprobantes(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        $txtContent = "F001|00005555|01|20777888999|CLIENTE VENTAS PERU S.A.C.|PEN|3000.00|540.00|3540.00\n";
        $zipBytes = $this->crearZipMockSire('20601234567-RVIE-202609.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token-rvie',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvie/propuesta/web/propuesta/202609/exportapropuesta*' => Http::response([
                'numTicket' => '20260900000020',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900000020',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20601234567-RVIE-202609.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $resultado = $this->service->importarPropuestaRVIE($cliente, '2026-09');

        $this->assertTrue($resultado['success']);
        $this->assertEquals(1, $resultado['importados']);
        $this->assertEquals('RVIE', $resultado['tipo_registro']);
        $this->assertEquals('2026-09', $resultado['periodo']);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'tipo_registro' => 'RVIE',
            'periodo' => '2026-09',
            'serie' => 'F001',
            'correlativo' => '00005555',
            'ruc_tercero' => '20777888999',
            'razon_social_tercero' => 'CLIENTE VENTAS PERU S.A.C.',
            'base_imponible' => 3000.00,
            'igv' => 540.00,
            'total' => 3540.00,
            'estado_sire' => 'Propuesta',
        ]);
    }

    /**
     * Verifica que retorna estado 'procesando' con ticket cuando el proceso aún no ha terminado.
     */
    public function test_returns_processing_status_when_ticket_is_not_finished(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'numTicket' => '20260900000030',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900000030',
                'desEstadoProceso' => 'En proceso',
                'nomArchivo' => null,
            ], 200),
        ]);

        $resultado = $this->service->importarPropuestaRCE($cliente, '2026-09');

        $this->assertTrue($resultado['success']);
        $this->assertEquals('procesando', $resultado['status']);
        $this->assertEquals('Ticket generado en SUNAT. Procesando descarga...', $resultado['mensaje']);
        $this->assertEquals('20260900000030', $resultado['ticket']);
    }

    /**
     * Verifica que procesarTicketPendiente concluye la descarga cuando el ticket pasa a Terminado.
     */
    public function test_procesar_ticket_pendiente_completes_when_ticket_becomes_terminado(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        $txtContent = "E001|00000999|01|20111333444|PROVEEDOR PENDIENTE SAC|PEN|800.00|144.00|944.00\n";
        $zipBytes = $this->crearZipMockSire('20601234567-RCE-PEND.txt', $txtContent);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket*' => Http::response([
                'numTicket' => '20260900000040',
                'desEstadoProceso' => 'Terminado',
                'nomArchivo' => '20601234567-RCE-PEND.zip',
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte*' => Http::response(
                $zipBytes,
                200,
                ['Content-Type' => 'application/zip']
            ),
        ]);

        $resultado = $this->service->procesarTicketPendiente($cliente, 'RCE', '2026-09', '20260900000040');

        $this->assertTrue($resultado['success']);
        $this->assertEquals('completado', $resultado['status']);
        $this->assertEquals(1, $resultado['importados']);

        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'E001',
            'correlativo' => '00000999',
            'ruc_tercero' => '20111333444',
            'total' => 944.00,
        ]);
    }

    /**
     * Verifica la importación fallback en caso de respuesta con registros JSON directos.
     */
    public function test_can_import_via_direct_json_fallback(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token-rce',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'registros' => [
                    [
                        'numSerie' => 'E001',
                        'numComprobante' => '00000100',
                        'numDocIdentidadProveedor' => '20123456789',
                        'desRazonSocialProveedor' => 'DISTRIBUIDORA LIMA S.A.C.',
                        'codTipoCdp' => '01',
                        'codMoneda' => 'PEN',
                        'mtoBaseImponible' => 1000.00,
                        'mtoIGV' => 180.00,
                        'mtoTotal' => 1180.00,
                    ],
                ],
            ], 200),
        ]);

        $resultado = $this->service->importarPropuestaRCE($cliente, '2026-09');

        $this->assertTrue($resultado['success']);
        $this->assertEquals(1, $resultado['importados']);
        $this->assertDatabaseHas('comprobantes', [
            'cliente_id' => $cliente->id,
            'serie' => 'E001',
            'correlativo' => '00000100',
        ]);
    }

    /**
     * Verifica la normalización de periodos a 6 dígitos para la API y formato canónico para BD.
     */
    public function test_normalizar_periodo_various_formats(): void
    {
        // Formato YYYY-MM
        $res1 = $this->service->normalizarPeriodo('2026-09');
        $this->assertEquals('202609', $res1['api']);
        $this->assertEquals('2026-09', $res1['db']);

        // Formato YYYYMM (6 dígitos)
        $res2 = $this->service->normalizarPeriodo('202608');
        $this->assertEquals('202608', $res2['api']);
        $this->assertEquals('2026-08', $res2['db']);

        // Formato con mes de 1 dígito YYYY-M
        $res3 = $this->service->normalizarPeriodo('2026-4');
        $this->assertEquals('202604', $res3['api']);
        $this->assertEquals('2026-04', $res3['db']);
    }

    /**
     * Verifica la concatenación exacta RUC + Usuario SOL sin espacios y el envío del secret terminado en ==.
     */
    public function test_client_secret_ending_with_equals_and_exact_username_concatenation(): void
    {
        $cliente = $this->crearClienteConCredenciales([
            'ruc' => '20601234567',
            'sunat_client_id' => 'client-id-uuid-test',
            'sunat_client_secret' => 'AbCdEf123456==',
        ]);

        $cliente->credencial->update([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSol123==',
        ]);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'token-equals-test',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->service->getAccessToken($cliente);

        $this->assertEquals('token-equals-test', $token);

        Http::assertSent(function (Request $request) use ($cliente) {
            return $request->isForm()
                && $request['username'] === '20601234567MODDATOS'
                && $request['client_secret'] === 'AbCdEf123456=='
                && $request['password'] === 'ClaveSol123=='
                && $request['client_id'] === $cliente->sunat_client_id
                && $request['grant_type'] === 'password'
                && $request['scope'] === 'https://api-sire.sunat.gob.pe';
        });
    }

    /**
     * Verifica que la desencriptación segura recupera strings con doble cifrado o raw fallback.
     */
    public function test_safe_decryption_handles_double_encrypted_and_raw_fallback(): void
    {
        $plainSecret = 'secret-realy-super-raw==';
        // Simular doble encriptación directamente en la columna de la base de datos
        $doubleEncrypted = Crypt::encryptString(Crypt::encryptString($plainSecret));

        $cliente = Cliente::create([
            'razon_social' => 'DOUBLE ENCRYPTED SAC',
            'ruc' => '20199887766',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'my-client-id',
            'sunat_client_secret' => 'temp',
        ]);

        \DB::table('clientes')->where('id', $cliente->id)->update([
            'sunat_client_secret' => $doubleEncrypted,
        ]);
        $cliente->refresh();

        $cliente->credencial()->create([
            'usuario_sol' => 'ADMINSOL',
            'clave_sol' => 'pass123',
        ]);

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'token-double-test',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
        ]);

        $token = $this->service->getAccessToken($cliente);

        $this->assertEquals('token-double-test', $token);

        Http::assertSent(function (Request $request) use ($plainSecret) {
            return $request['client_secret'] === $plainSecret
                && $request['username'] === '20199887766ADMINSOL';
        });
    }

    /**
     * Verifica que la importación limpia la caché de token antes de solicitar la propuesta.
     */
    public function test_import_clears_token_cache_before_requesting_new_token(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        // Colocar un token en caché
        Cache::put("sunat_token_{$cliente->id}", 'old-stale-corrupted-token', 3600);
        $this->assertTrue(Cache::has("sunat_token_{$cliente->id}"));

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'fresh-new-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response([
                'registros' => [
                    [
                        'numSerie' => 'E001',
                        'numComprobante' => '00000001',
                        'numDocIdentidadProveedor' => '20123456789',
                        'desRazonSocialProveedor' => 'TEST FRESH S.A.C.',
                        'codTipoCdp' => '01',
                        'codMoneda' => 'PEN',
                        'mtoBaseImponible' => 100.00,
                        'mtoIGV' => 18.00,
                        'mtoTotal' => 118.00,
                    ],
                ],
            ], 200),
        ]);

        $this->service->importarPropuestaRCE($cliente, '2026-09');

        // Se debió solicitar un nuevo token a la URL de autenticación de SUNAT porque la caché fue limpiada
        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'oauth2/token');
        });
    }

    /**
     * Verifica la consulta de estado de ticket con el endpoint oficial de procesos masivos de SUNAT SIRE.
     */
    public function test_can_query_ticket_status_using_official_sire_endpoint(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token-tickets',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets*' => Http::response([
                'paginacion' => ['page' => 1, 'perPage' => 20, 'totalRegistros' => 1],
                'registros' => [
                    [
                        'perTributario' => '202608',
                        'numTicket' => '20260300000018',
                        'codProceso' => '10',
                        'desProceso' => 'Generar archivo exportar propuesta',
                        'codEstadoProceso' => '06',
                        'desEstadoProceso' => 'Terminado',
                        'archivoReporte' => [
                            [
                                'codTipoAchivoReporte' => '00',
                                'nomArchivoReporte' => '20601234567-202608-propuesta.zip',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $estado = $this->service->consultarEstadoTicket($cliente, '20260300000018', '2026-08', 'RCE');

        $this->assertTrue($estado['terminado']);
        $this->assertEquals('Terminado', $estado['estado']);
        $this->assertEquals('20601234567-202608-propuesta.zip', $estado['nom_archivo']);
        $this->assertEquals('00', $estado['cod_tipo_archivo']);
        $this->assertEquals('10', $estado['cod_proceso']);

        Http::assertSent(function (Request $request) {
            return str_contains($request->url(), 'gestionprocesosmasivos/web/masivo/consultaestadotickets')
                && $request['perIni'] === '202608'
                && $request['perFin'] === '202608'
                && $request['numTicket'] === '20260300000018';
        });
    }

    /**
     * Verifica que mapea con total precisión el formato de 40 columnas oficial de SUNAT SIRE RVIE (Ventas).
     */
    public function test_mapear_columnas_sire_rvie_official_format(): void
    {
        $line = '20605435191|EL CHALACO MAJEÑO E.I.R.L.|202608|2060543519101E0010000000426|01/08/2026||01|E001|426||6|20498189718|SERVICIOS TURISTICOS BON GOURMET E.I.R.L|0|0|0|0|0|768.32|0|0|0|0|0|0|768.32|PEN|1.000|||||||1|0|0|1001||';
        $cols = explode('|', $line);

        $mapped = $this->service->mapearColumnasSire($cols, 'RVIE', '20605435191');

        $this->assertNotNull($mapped);
        $this->assertEquals('E001', $mapped['serie']);
        $this->assertEquals('426', $mapped['correlativo']);
        $this->assertEquals('20498189718', $mapped['ruc_tercero']);
        $this->assertEquals('SERVICIOS TURISTICOS BON GOURMET E.I.R.L', $mapped['razon_social_tercero']);
        $this->assertEquals('01', $mapped['tipo_comprobante']);
        $this->assertEquals('PEN', $mapped['moneda']);
        $this->assertEquals(768.32, $mapped['total']);
        $this->assertEquals(768.32, $mapped['base_imponible']);
        $this->assertEquals(0.0, $mapped['igv']);
    }

    /**
     * Verifica que mapea con total precisión el formato de 80 columnas oficial de SUNAT SIRE RCE (Compras)
     * incluyendo comprobantes emitidos con facturadores privados (series F001, FI01, etc.).
     */
    public function test_mapear_columnas_sire_rce_official_format_with_private_invoicing(): void
    {
        $line = '20605435191|EL CHALACO MAJENO EIRL|202608|2010004721801FI010020500133|05/08/2026|01/07/2026|01|FI01||20500133||6|20100047218|BANCO DE CREDITO DEL PERU|0.00|0.00|0.00|0.00|0.00|0.00|40.50|0.00|0.00|0.00|40.50|PEN|1.000||||||||0.00|||1|0|||||||||||||||||||||||||||||||||||||';
        $cols = explode('|', $line);

        $mapped = $this->service->mapearColumnasSire($cols, 'RCE', '20605435191');

        $this->assertNotNull($mapped);
        $this->assertEquals('FI01', $mapped['serie']);
        $this->assertEquals('20500133', $mapped['correlativo']);
        $this->assertEquals('20100047218', $mapped['ruc_tercero']);
        $this->assertEquals('BANCO DE CREDITO DEL PERU', $mapped['razon_social_tercero']);
        $this->assertEquals('01', $mapped['tipo_comprobante']);
        $this->assertEquals('PEN', $mapped['moneda']);
        $this->assertEquals(40.50, $mapped['total']);
        $this->assertEquals(40.50, $mapped['base_imponible']);
        $this->assertEquals(0.0, $mapped['igv']);
    }

    /**
     * Verifica que si SUNAT responde con error 500 o HTML, el mensaje sea amigable y sin etiquetas HTML.
     */
    public function test_handles_friendly_error_for_sunat_http_500(): void
    {
        $cliente = $this->crearClienteConCredenciales();

        Http::fake([
            'https://api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-jwt-token',
                'token_type' => 'bearer',
                'expires_in' => 3600,
            ], 200),
            'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/202609/exportacioncomprobantepropuesta*' => Http::response(
                '<html><head><title>500 Internal Server Error</title></head><body><h1>500 Internal Server Error</h1><hr>nginx</body></html>',
                500
            ),
        ]);

        try {
            $this->service->importarPropuestaRCE($cliente, '2026-09');
            $this->fail('Se esperaba SunatApiException');
        } catch (SunatApiException $e) {
            $this->assertStringContainsString('Error SUNAT 500', $e->getMessage());
            $this->assertStringContainsString('falla interna temporal al procesar la solicitud', $e->getMessage());
            $this->assertStringNotContainsString('<html>', $e->getMessage());
            $this->assertStringNotContainsString('nginx', $e->getMessage());
        }
    }
}
