<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\PadronRiesgoSunat;
use App\Models\User;
use App\Services\SunatEscudoFiscalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EscudoFiscalTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $admin;

    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Contable Auditoría SAC',
            'ruc' => '20601234567',
            'estado' => 'activo',
        ]);

        $this->admin = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'IMPORTACIONES VIRGEN DE CHAPI SRL',
            'ruc' => '20498311700',
            'regimen_tributario' => 'RG',
            'estado' => 'activo',
            'sunat_cpe_client_id' => 'client-id-test',
            'sunat_cpe_client_secret' => 'secret-test',
        ]);
    }

    protected function tearDown(): void
    {
        SunatEscudoFiscalService::setCustomScraper(null);
        parent::tearDown();
    }

    public function test_usuario_autenticado_puede_ver_modulo_escudo_fiscal(): void
    {
        PadronRiesgoSunat::create([
            'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_FIRME,
            'ruc' => '20610602771',
            'razon_social' => 'REPRESENTACIONES VALDERRAMA E.I.R.L.',
            'resolucion_atribucion' => 'Resolución N° 024-024-0080171/SUNAT',
            'fecha_firme_resolucion' => '11/11/2024',
            'fecha_publicacion' => '27/12/2024',
            'doc_representante' => '10460556584',
            'nombre_representante' => 'JARA VALDERRAMA ENMA',
            'corte_sunat' => 'Información actualizada al 30 de setiembre de 2026',
        ]);

        $response = $this->actingAs($this->admin)->get(route('escudo-fiscal.index'));

        $response->assertOk();
        $response->assertSee('Escudo Fiscal 360°');
        $response->assertSee('30 de setiembre de 2026');
    }

    public function test_detecta_proveedor_ssco_firme_con_riesgo_critico_100(): void
    {
        PadronRiesgoSunat::create([
            'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_FIRME,
            'ruc' => '20610602771',
            'razon_social' => 'REPRESENTACIONES VALDERRAMA E.I.R.L.',
            'resolucion_atribucion' => 'Resolución de Intendencia N° 024-024-0080171/SUNAT',
            'fecha_firme_resolucion' => '11/11/2024',
            'fecha_publicacion' => '27/12/2024',
            'doc_representante' => '10460556584',
            'nombre_representante' => 'JARA VALDERRAMA ENMA',
            'corte_sunat' => 'Información actualizada al 30 de setiembre de 2026',
        ]);

        SunatEscudoFiscalService::setCustomScraper(fn (string $ruc) => [
            'success' => true,
            'ruc' => $ruc,
            'rawFields' => [
                'Número de RUC:' => '20610602771 - REPRESENTACIONES VALDERRAMA E.I.R.L.',
                'Tipo Contribuyente:' => 'EMPRESA INDIVIDUAL DE RESP. LTDA',
                'Fecha de Inscripción:' => '14/02/2023',
                'Fecha de Inicio de Actividades:' => '01/02/2023',
                'Estado del Contribuyente:' => 'BAJA DE OFICIO Fecha de Baja: 31/03/2026',
                'Condición del Contribuyente:' => 'NO HABIDO',
                'Domicilio Fiscal:' => 'JR. PUNO NRO. 466 LIMA - LIMA - LIMA',
                'Actividad(es) Económica(s):' => 'Principal - 4663 - VENTA AL POR MAYOR DE MATERIALES DE CONSTRUCCIÓN',
            ],
            'subResults' => [
                'formRepLeg' => [
                    'tables' => [[
                        ['Documento', 'Nro. Documento', 'Nombre', 'Cargo', 'Fecha Desde'],
                        ['DNI', '46055658', 'JARA VALDERRAMA ENMA', 'TITULAR-GERENTE', '07/02/2023'],
                    ]],
                ],
                'formInfoDeudaCoactiva' => [
                    'tables' => [[
                        ['Monto de la Deuda', 'Período Tributario', 'Fecha de Inicio', 'Entidad'],
                        ['6410.00', '2025 - 04', '29/05/2025', 'Tesoro Público'],
                    ]],
                ],
                'formNumTrabajd' => [
                    'tables' => [],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('escudo-fiscal.auditar-ruc'), [
            'ruc' => '20610602771',
            'concepto_operacion' => 'Servicio de desarrollo de software y programación',
            'monto' => 5800.00,
            'moneda' => 'PEN',
            'cci_proveedor' => '99900000000000000000',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('padrones_negros.es_ssco_firme', true)
            ->assertJsonPath('scoring.score', 100)
            ->assertJsonPath('scoring.nivel', 'CRITICO')
            ->assertJsonPath('congruencia_giro.nivel', 'incongruente')
            ->assertJsonPath('bancarizacion.exige_bancarizacion', true)
            ->assertJsonPath('bancarizacion.cci_validacion.valido', false);
    }

    public function test_detecta_representante_legal_clonado_en_otra_empresa_ssco(): void
    {
        // Otra empresa sancionada como SSCO cuyo representante tiene DNI 47641919
        PadronRiesgoSunat::create([
            'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_FIRME,
            'ruc' => '20612928429',
            'razon_social' => 'DISTRIBUIDORA STARH E.I.R.L.',
            'resolucion_atribucion' => 'RI 024-024-0099999/SUNAT',
            'doc_representante' => '47641919',
            'nombre_representante' => 'CHERO GASTELU JHOSSELYN YAZMIN',
            'fecha_publicacion' => '30/09/2026',
        ]);

        // Auditamos un RUC distinto (20609720400) pero simulando que su gerente tiene el mismo DNI 47641919
        SunatEscudoFiscalService::setCustomScraper(fn (string $ruc) => [
            'success' => true,
            'ruc' => $ruc,
            'rawFields' => [
                'Número de RUC:' => '20609720400 - EMPRESA NUEVA CASCARON E.I.R.L.',
                'Tipo Contribuyente:' => 'EMPRESA INDIVIDUAL DE RESP. LTDA',
                'Fecha de Inscripción:' => '01/09/2026',
                'Fecha de Inicio de Actividades:' => '01/09/2026',
                'Estado del Contribuyente:' => 'ACTIVO',
                'Condición del Contribuyente:' => 'HABIDO',
                'Domicilio Fiscal:' => 'AV. LIMA 123',
                'Actividad(es) Económica(s):' => 'Principal - 4923 - TRANSPORTE DE CARGA POR CARRETERA',
            ],
            'subResults' => [
                'formRepLeg' => [
                    'tables' => [[
                        ['Documento', 'Nro. Documento', 'Nombre', 'Cargo', 'Fecha Desde'],
                        ['DNI', '47641919', 'CHERO GASTELU JHOSSELYN YAZMIN', 'GERENTE GENERAL', '01/09/2026'],
                    ]],
                ],
                'formNumTrabajd' => [
                    'tables' => [],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('escudo-fiscal.auditar-ruc'), [
            'ruc' => '20609720400',
            'monto' => 4500,
            'moneda' => 'PEN',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('scoring.nivel', 'CRITICO')
            ->assertJsonPath('padrones_negros.alertas_representantes_ssco.0.ruc_vinculado', '20612928429');
    }

    public function test_califica_proveedor_operativo_con_planilla_y_giro_congruente_como_confiable(): void
    {
        Http::fake([
            '*' => Http::response([
                'nombre' => 'AMICARGO EXPRESS E.I.R.L.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'AV. RAMON CASTILLA NRO 903',
            ], 200),
        ]);

        SunatEscudoFiscalService::setCustomScraper(fn (string $ruc) => [
            'success' => true,
            'ruc' => $ruc,
            'rawFields' => [
                'Número de RUC:' => '20609720400 - AMICARGO EXPRESS E.I.R.L.',
                'Tipo Contribuyente:' => 'EMPRESA INDIVIDUAL DE RESP. LTDA',
                'Fecha de Inscripción:' => '11/07/2022',
                'Fecha de Inicio de Actividades:' => '15/07/2022',
                'Estado del Contribuyente:' => 'ACTIVO',
                'Condición del Contribuyente:' => 'HABIDO',
                'Domicilio Fiscal:' => 'AV. RAMON CASTILLA NRO. 903 AREQUIPA',
                'Actividad(es) Económica(s):' => 'Principal - 4923 - TRANSPORTE DE CARGA POR CARRETERA',
            ],
            'subResults' => [
                'formRepLeg' => [
                    'tables' => [[
                        ['Documento', 'Nro. Documento', 'Nombre', 'Cargo', 'Fecha Desde'],
                        ['DNI', '40380240', 'ESPINOZA QUILLA JEANETH LIZZY', 'TITULAR-GERENTE', '27/05/2022'],
                    ]],
                ],
                'formNumTrabajd' => [
                    'tables' => [[
                        ['Período', 'N° de Trabajadores', 'N° de Pensionistas', 'N° de Prestadores de Servicio'],
                        ['2026-08', '5', '0', '1'],
                    ]],
                ],
            ],
        ]);

        $response = $this->actingAs($this->admin)->postJson(route('escudo-fiscal.auditar-ruc'), [
            'ruc' => '20609720400',
            'concepto_operacion' => 'Servicio de transporte de carga y flete de mercadería',
            'monto' => 1500,
            'moneda' => 'PEN',
            'cci_proveedor' => '00219100123456789012',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('scoring.score', 0)
            ->assertJsonPath('scoring.nivel', 'CONFIABLE')
            ->assertJsonPath('congruencia_giro.congruente', true)
            ->assertJsonPath('bancarizacion.cci_validacion.valido', true);
    }

    public function test_puede_listar_y_exportar_padron_ssco(): void
    {
        PadronRiesgoSunat::create([
            'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_FIRME,
            'ruc' => '10002179496',
            'razon_social' => 'RUIZ CRUZ FIDEL ENRIQUE',
            'resolucion_atribucion' => 'Resolución N.º 254-024-0001296/SUNAT',
            'doc_representante' => '10002179496',
            'nombre_representante' => 'RUIZ CRUZ FIDEL ENRIQUE',
            'fecha_publicacion' => '30/09/2026',
        ]);

        $listResponse = $this->actingAs($this->admin)->getJson(route('escudo-fiscal.padron', [
            'tipo' => 'ssco_firme',
            'buscar' => 'RUIZ',
        ]));

        $listResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('total', 1)
            ->assertJsonPath('items.0.ruc', '10002179496');

        $csvResponse = $this->actingAs($this->admin)->get(route('escudo-fiscal.exportar', [
            'tipo' => 'ssco_firme',
        ]));

        $csvResponse->assertOk();
        $this->assertStringContainsString('10002179496', $csvResponse->getContent());
    }
}
