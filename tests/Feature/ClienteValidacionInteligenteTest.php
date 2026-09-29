<?php

namespace Tests\Feature;

use App\Http\Controllers\ClienteController;
use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClienteValidacionInteligenteTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        // Limpiar cualquier mock fijado para los tests
        ClienteController::setCustomSolValidator(null);
        parent::tearDown();
    }

    public function test_validar_ruc_returns_success_for_valid_ruc(): void
    {
        // RUCs reales peruanos con Módulo 11 correcto
        $rucsValidos = [
            '20605435191', // EL CHALACO MAJEÑO EIRL
            '10297388580',
            '10307603344',
            '10415279251',
            '10430028320',
            '10435462095',
        ];

        foreach ($rucsValidos as $ruc) {
            $response = $this->postJson(route('clientes.validar-ruc'), [
                'ruc' => $ruc,
            ]);

            $response->assertOk();
            $response->assertJson([
                'valid' => true,
                'duplicate' => false,
            ]);
        }
    }

    public function test_validar_ruc_rejects_invalid_checksum(): void
    {
        // RUC con dígito verificador alterado
        $response = $this->postJson(route('clientes.validar-ruc'), [
            'ruc' => '20605435199', // Debería terminar en 1, no en 9
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
        ]);
        $this->assertStringContainsString('dígito verificador incorrecto', $response->json('message'));
    }

    public function test_validar_ruc_rejects_invalid_prefix_or_length(): void
    {
        // RUC menor a 11 dígitos
        $res1 = $this->postJson(route('clientes.validar-ruc'), ['ruc' => '206054351']);
        $res1->assertStatus(422);

        // RUC con prefijo no tributario (ej. 30...)
        $res2 = $this->postJson(route('clientes.validar-ruc'), ['ruc' => '30605435191']);
        $res2->assertStatus(422);
        $this->assertStringContainsString('iniciar con 10, 20, 15, 16 o 17', $res2->json('message'));
    }

    public function test_validar_ruc_detects_duplicate_client_in_crm(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero=20605435191' => Http::response([
                'nombre' => 'EMPRESA REGISTRADA S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'AV. PRUEBA 123',
                'ubigeo' => '150101',
                'distrito' => 'LIMA',
                'provincia' => 'LIMA',
                'departamento' => 'LIMA',
            ], 200),
        ]);

        Cliente::create([
            'razon_social' => 'EMPRESA REGISTRADA S.A.C.',
            'ruc' => '20605435191',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->postJson(route('clientes.validar-ruc'), [
            'ruc' => '20605435191',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'duplicate' => true,
            'razon_social' => 'EMPRESA REGISTRADA S.A.C.',
            'estado_sunat' => 'ACTIVO',
            'condicion_sunat' => 'HABIDO',
            'direccion_sunat' => 'AV. PRUEBA 123',
            'ubigeo' => '150101',
            'distrito' => 'LIMA',
            'provincia' => 'LIMA',
            'departamento' => 'LIMA',
        ]);
        $this->assertStringContainsString('EMPRESA REGISTRADA S.A.C.', $response->json('message'));
        Http::assertSentCount(1);
    }

    public function test_validar_sol_returns_success_when_credentials_are_valid(): void
    {
        ClienteController::setCustomSolValidator(function ($ruc, $usuario, $clave) {
            return [
                'success' => true,
                'valid' => true,
                'message' => 'Credenciales Clave SOL verificadas exitosamente con SUNAT.',
                'razon_social' => 'EMPRESA MOCK VALIDADA S.A.C.',
            ];
        });

        $response = $this->postJson(route('clientes.validar-sol'), [
            'ruc' => '20605435191',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveValida2026',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'valid' => true,
            'razon_social' => 'EMPRESA MOCK VALIDADA S.A.C.',
        ]);
    }

    public function test_validar_sol_returns_error_when_credentials_are_invalid(): void
    {
        ClienteController::setCustomSolValidator(function ($ruc, $usuario, $clave) {
            return [
                'success' => true,
                'valid' => false,
                'message' => 'El Usuario o Clave SOL no corresponden a este RUC en SUNAT.',
            ];
        });

        $response = $this->postJson(route('clientes.validar-sol'), [
            'ruc' => '20605435191',
            'usuario_sol' => 'WRONGUSER',
            'clave_sol' => 'WRONGPASS',
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'valid' => false,
            'message' => 'El Usuario o Clave SOL no corresponden a este RUC en SUNAT.',
        ]);
    }

    public function test_validar_ruc_returns_razon_social_from_sunat(): void
    {
        Http::fake([
            'https://api.apis.net.pe/*' => Http::response([
                'nombre' => 'EMPRESA PRUEBA S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'CALLE REAL 123',
            ], 200),
        ]);

        $response = $this->postJson(route('clientes.validar-ruc'), [
            'ruc' => '20605435191',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'razon_social' => 'EMPRESA PRUEBA S.A.C.',
            'estado_sunat' => 'ACTIVO',
            'condicion_sunat' => 'HABIDO',
        ]);
    }

    public function test_create_view_loads_successfully(): void
    {
        $response = $this->get(route('clientes.create'));
        $response->assertOk();
        $response->assertSee('Onboarding — Nuevo Cliente');
        $response->assertSee('onboardingApp');
        $response->assertSee('Comprobando Acceso con SUNAT');
        $response->assertSee('Registrar Cliente');
        $response->assertDontSee('Probar conexión Clave SOL');
    }
}
