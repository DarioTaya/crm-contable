<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ValidezComprobanteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Estudio $estudio;

    private Cliente $clienteConCpe;

    private Cliente $clienteSinCpe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Contable Test',
            'ruc' => '20100000000',
        ]);

        $this->admin = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('admin123'),
            'rol' => 'admin',
            'estudio_id' => $this->estudio->id,
            'activo' => true,
        ]);

        $this->actingAs($this->admin);

        $this->clienteConCpe = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'EMPRESA TEST CON CPE S.A.C.',
            'ruc' => '20100000001',
            'regimen_tributario' => 'RG',
            'estado' => 'activo',
            'sunat_cpe_client_id' => 'test-cpe-client-id-123',
            'sunat_cpe_client_secret' => 'test-cpe-secret-xyz==',
        ]);

        $this->clienteSinCpe = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'EMPRESA TEST SIN CPE S.A.C.',
            'ruc' => '20100000002',
            'regimen_tributario' => 'RER',
            'estado' => 'activo',
        ]);
    }

    /**
     * Verifica que la pantalla principal del módulo de Validez CPE se renderiza correctamente.
     */
    public function test_validez_comprobantes_screen_can_be_rendered(): void
    {
        $response = $this->get(route('validez-cpe.index'));

        $response->assertOk()
            ->assertSee('Consulta de Validez de Comprobantes de Pago')
            ->assertSee('Validez CPE (API)')
            ->assertSee('EMPRESA TEST CON CPE S.A.C.');
    }

    /**
     * Verifica que las credenciales CPE se guardan y se encriptan correctamente vía AJAX.
     */
    public function test_can_update_cpe_credentials_via_ajax(): void
    {
        $response = $this->putJson(route('clientes.cpe-credentials.update', $this->clienteSinCpe), [
            'sunat_cpe_client_id' => 'nuevo-cpe-id-456',
            'sunat_cpe_client_secret' => 'nueva-clave-cpe==',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Credenciales API SUNAT (Validez de Comprobantes) actualizadas correctamente.',
            ]);

        $this->clienteSinCpe->refresh();
        $this->assertSame('nuevo-cpe-id-456', $this->clienteSinCpe->sunat_cpe_client_id);
        $this->assertSame('nueva-clave-cpe==', $this->clienteSinCpe->sunat_cpe_client_secret);
        $this->assertTrue($this->clienteSinCpe->tieneCredencialesCpe());
    }

    /**
     * Verifica la consulta individual con respuesta exitosa y diagnóstico tributario Apto.
     */
    public function test_validar_individual_endpoint_returns_sunat_result(): void
    {
        Http::fake([
            'api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-jwt-12345',
                'token_type' => 'JWT',
                'expires_in' => 3600,
            ], 200),
            'api.sunat.gob.pe/*' => Http::response([
                'success' => true,
                'message' => 'Operation Success! ',
                'data' => [
                    'estadoCp' => '1',
                    'estadoRuc' => '00',
                    'condDomiRuc' => '00',
                    'observaciones' => [],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('validez-cpe.validar-individual'), [
            'cliente_id' => $this->clienteConCpe->id,
            'numRuc' => '20100047218',
            'codComp' => '01',
            'numeroSerie' => 'F001',
            'numero' => '1234',
            'fechaEmision' => '14/10/2026',
            'monto' => '1500.00',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'estado_tributario' => 'APTO',
                'severidad' => 'success',
                'es_valido_credito_fiscal' => true,
                'estado_cp' => [
                    'codigo' => '1',
                    'label' => 'ACEPTADO',
                ],
                'estado_ruc' => [
                    'codigo' => '00',
                    'label' => 'ACTIVO',
                ],
                'condicion_domicilio' => [
                    'codigo' => '00',
                    'label' => 'HABIDO',
                ],
            ]);
    }

    /**
     * Verifica el diagnóstico tributario de advertencia si el proveedor es NO HABIDO.
     */
    public function test_validar_individual_diagnoses_warning_when_emisor_is_no_habido(): void
    {
        Http::fake([
            'api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-jwt-12345',
            ], 200),
            'api.sunat.gob.pe/*' => Http::response([
                'success' => true,
                'message' => 'Operation Success! ',
                'data' => [
                    'estadoCp' => '1',
                    'estadoRuc' => '00',
                    'condDomiRuc' => '12', // NO HABIDO
                    'observaciones' => [],
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('validez-cpe.validar-individual'), [
            'cliente_id' => $this->clienteConCpe->id,
            'numRuc' => '20100047218',
            'codComp' => '01',
            'numeroSerie' => 'F001',
            'numero' => '1234',
            'fechaEmision' => '14/10/2026',
            'monto' => '1500.00',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'estado_tributario' => 'OBSERVADO_NO_HABIDO',
                'severidad' => 'warning',
                'es_valido_credito_fiscal' => false,
            ]);
    }

    /**
     * Verifica que validar lote procesa un array de comprobantes y devuelve sus diagnósticos.
     */
    public function test_validar_lote_endpoint(): void
    {
        Http::fake([
            'api-seguridad.sunat.gob.pe/*' => Http::response(['access_token' => 'mock-token'], 200),
            'api.sunat.gob.pe/*' => Http::response([
                'success' => true,
                'data' => [
                    'estadoCp' => '1',
                    'estadoRuc' => '00',
                    'condDomiRuc' => '00',
                ],
            ], 200),
        ]);

        $response = $this->postJson(route('validez-cpe.validar-lote'), [
            'cliente_id' => $this->clienteConCpe->id,
            'comprobantes' => [
                [
                    'item' => 1,
                    'numRuc' => '20100047218',
                    'codComp' => '01',
                    'numeroSerie' => 'F001',
                    'numero' => '100',
                    'fechaEmision' => '14/10/2026',
                    'monto' => '250.00',
                ],
                [
                    'item' => 2,
                    'numRuc' => '20504787010',
                    'codComp' => '01',
                    'numeroSerie' => 'F002',
                    'numero' => '200',
                    'fechaEmision' => '14/10/2026',
                    'monto' => '120.00',
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'resultados' => [
                    '*' => [
                        'item',
                        'success',
                        'estado_tributario',
                        'es_valido_credito_fiscal',
                        'estado_cp',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('resultados'));
    }

    /**
     * Verifica la carga y parseo en memoria de un archivo CSV con comprobantes.
     */
    public function test_cargar_archivo_csv_parses_rows(): void
    {
        $csvContent = "RUC Emisor,Tipo CPE,Serie,Número,Fecha Emisión,Total\n"
            ."20100047218,01,F001,500,10/10/2026,100.00\n"
            ."20504787010,03,B001,800,11/10/2026,45.50\n";

        $file = UploadedFile::fake()->createWithContent('registro_compras.csv', $csvContent);

        $response = $this->postJson(route('validez-cpe.cargar-archivo'), [
            'cliente_id' => $this->clienteConCpe->id,
            'archivo' => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total' => 2,
            ]);

        $filas = $response->json('filas');
        $this->assertCount(2, $filas);
        $this->assertSame('20100047218', $filas[0]['numRuc']);
        $this->assertSame('F001', $filas[0]['numeroSerie']);
        $this->assertSame('500', $filas[0]['numero']);
    }

    /**
     * Verifica que el parser procesa archivos reales exportados de New Contasis con formato moneda original.
     */
    public function test_cargar_archivo_new_contasis_format_parses_real_rows(): void
    {
        $newContasisCsv = ",,,,,,,,,,,,,,,,,,,,\n"
            .",IMPORTACIONES VIRGEN DE CHAPI SRL,,,,,,,,,,,,,,,,,,,\n"
            .",COMERCIO DE NEUMATICOS Y ACCESORIOS,,,,,,,,,,,,,,,,,,,\n"
            .",R.U.C.: 20498311700,,,,,,,,,,,,,,,,,,,\n"
            .",PERIODO: SEPTIEMBRE 2026,,,,,,,,,,,,,,,,,,,\n"
            .",REGISTRO DE COMPRAS (MONEDA ORIGINAL),,,,,,,,,,,,,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,\n"
            .",F.EMIS.,F.VENCIM,F.IGV RET,F.IMP.BIE,D.,NUMERO,R.U.C.,RAZON SOCIAL,No gravado,Otros T y C,Base Imp. 1,IGV 1,Total,C.NO DOMI.,D. DETRAC.,F.DETRAC.,N° NC/ND,,GLOSA,T/C\n"
            .",2026-08-01,2026-08-05,,,01,00F007-0000000472035,20498456856,EMPRESA DE TRANSPORTES Y SERVICIOS GENERALES TRANSMOTAR S.A.C.,0,0,525.42,94.58,620,,,,,,,T/C 3.4000 S/.,3.4\n"
            .",2026-08-25,,,,01,00F001-0000000577369,20140441083,TIRESOL SAC,0,0,2852.85,513.51,3366.36,,,,,,,T/C 3.3560 US$.,3.356\n"
            .",2026-09-04,,2026-09-02,,07,00E001-0000000000053,20609720400,AMICARGO EXPRESS E.I.R.L.,0,0,-220.34,-39.66,-260,,,,E001-0003368,,T/C 3.3680 S/.,3.368\n"
            .",,,,,,,,TOTAL REGISTRO: ,0,0,110719.57999999994,19910.460000000003,130630.03999999996,,,,,,,\n"
            .",,,,,,,,,,,,,,,,,,,,\n"
            .",Generado automáticamente por GESTIÓN CONTABLE FINANCIERO PREMIUM 26.00 - NewContaSis el 14/10/2026,,,,,,,,,,,,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('compras_new_contasis.csv', $newContasisCsv);

        $response = $this->postJson(route('validez-cpe.cargar-archivo'), [
            'cliente_id' => $this->clienteConCpe->id,
            'archivo' => $file,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'total' => 3,
            ]);

        $filas = $response->json('filas');
        $this->assertCount(3, $filas);

        // Fila 1: Factura Transmotar con serie F007 y correlativo 472035
        $this->assertSame('20498456856', $filas[0]['numRuc']);
        $this->assertSame('01', $filas[0]['codComp']);
        $this->assertSame('F007', $filas[0]['numeroSerie']);
        $this->assertSame('472035', $filas[0]['numero']);
        $this->assertSame('01/08/2026', $filas[0]['fechaEmision']);
        $this->assertSame('620.00', $filas[0]['monto']);

        // Fila 2: Factura Tiresol en Dólares (moneda original)
        $this->assertSame('20140441083', $filas[1]['numRuc']);
        $this->assertSame('F001', $filas[1]['numeroSerie']);
        $this->assertSame('577369', $filas[1]['numero']);
        $this->assertSame('3366.36', $filas[1]['monto']);

        // Fila 3: Nota de Crédito (código 07, monto negativo convertido a positivo para SUNAT)
        $this->assertSame('07', $filas[2]['codComp']);
        $this->assertSame('E001', $filas[2]['numeroSerie']);
        $this->assertSame('53', $filas[2]['numero']);
        $this->assertSame('260.00', $filas[2]['monto']);
    }

    /**
     * Verifica la descarga de la plantilla oficial en formatos XLSX y CSV.
     */
    public function test_descargar_plantilla_returns_xlsx_and_csv_files(): void
    {
        // Descarga por defecto en XLSX
        $responseXlsx = $this->get(route('validez-cpe.plantilla'));
        $responseXlsx->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Descarga explícita en CSV
        $responseCsv = $this->get(route('validez-cpe.plantilla', ['formato' => 'csv']));
        $responseCsv->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('RUC Emisor')
            ->assertSee('Tipo CPE');
    }

    /**
     * Verifica la exportación de resultados auditados a CSV.
     */
    public function test_exportar_reporte_returns_csv(): void
    {
        $response = $this->post(route('validez-cpe.exportar-reporte'), [
            'cliente_id' => $this->clienteConCpe->id,
            'resultados' => [
                [
                    'comprobante' => [
                        'numRuc' => '20100047218',
                        'codComp' => '01',
                        'numeroSerie' => 'F001',
                        'numero' => '1234',
                        'fechaEmision' => '14/10/2026',
                        'monto' => '1500.00',
                    ],
                    'estado_cp' => ['label' => 'ACEPTADO'],
                    'estado_ruc' => ['label' => 'ACTIVO'],
                    'condicion_domicilio' => ['label' => 'HABIDO'],
                    'diagnostico' => 'Apto para crédito fiscal',
                    'es_valido_credito_fiscal' => true,
                    'observaciones' => [],
                ],
            ],
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('20100047218')
            ->assertSee('ACEPTADO')
            ->assertSee('HABIDO')
            ->assertSee('Apto para crédito fiscal');
    }

    /**
     * Verifica la prueba de conexión en vivo con SUNAT vía testCpeApiConnection.
     */
    public function test_test_cpe_api_connection_endpoint(): void
    {
        Http::fake([
            'api-seguridad.sunat.gob.pe/*' => Http::response([
                'access_token' => 'mock-token-live',
                'expires_in' => 3600,
            ], 200),
        ]);

        $response = $this->postJson(route('clientes.api-cpe.test', $this->clienteConCpe));

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => '¡Conexión exitosa con la API de Validez de Comprobantes de SUNAT!',
            ]);
    }
}
