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
     * Verifica la descarga de la plantilla oficial en CSV.
     */
    public function test_descargar_plantilla_returns_csv_file(): void
    {
        $response = $this->get(route('validez-cpe.plantilla'));

        $response->assertOk()
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
