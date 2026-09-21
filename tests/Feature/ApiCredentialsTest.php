<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiCredentialsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verifica que las credenciales API se guardan exitosamente por AJAX.
     */
    public function test_can_save_api_credentials_via_ajax(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'TEST API S.A.C.',
            'ruc' => '20100000001',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'abc-123-uuid',
            'sunat_client_secret' => 'super-secret-value',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Credenciales API SUNAT actualizadas correctamente.',
            ]);

        $cliente->refresh();
        $this->assertEquals('abc-123-uuid', $cliente->sunat_client_id);
        // El secret se desencripta automáticamente por el cast 'encrypted'
        $this->assertEquals('super-secret-value', $cliente->sunat_client_secret);
    }

    /**
     * Verifica que el Client Secret se almacena cifrado en la base de datos.
     */
    public function test_client_secret_is_stored_encrypted_in_database(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'ENCRYPTED TEST S.A.C.',
            'ruc' => '20100000002',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'id-plain',
            'sunat_client_secret' => 'mi-clave-secreta',
        ]);

        // Verificar que el valor en bruto en DB NO es el texto plano
        $rawRow = \DB::table('clientes')->where('id', $cliente->id)->first();
        $this->assertNotEquals('mi-clave-secreta', $rawRow->sunat_client_secret);
        $this->assertNotEmpty($rawRow->sunat_client_secret);

        // Pero al leerlo vía Eloquent, se desencripta automáticamente
        $cliente->refresh();
        $this->assertEquals('mi-clave-secreta', $cliente->sunat_client_secret);
    }

    /**
     * Verifica que se pueden actualizar credenciales parcialmente (solo Client ID).
     */
    public function test_can_save_partial_credentials_only_client_id(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'PARTIAL TEST S.A.C.',
            'ruc' => '20100000003',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);

        $response = $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'solo-id',
            'sunat_client_secret' => null,
        ]);

        $response->assertOk();

        $cliente->refresh();
        $this->assertEquals('solo-id', $cliente->sunat_client_id);
        $this->assertNull($cliente->sunat_client_secret);
    }

    /**
     * Verifica que si el sunat_client_secret viene vacío o nulo, NO se sobrescribe la clave actual.
     */
    public function test_empty_or_null_client_secret_does_not_overwrite_existing_secret(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'PERSIST SECRET TEST S.A.C.',
            'ruc' => '20100000004',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'existing-id',
            'sunat_client_secret' => 'existing-secret',
        ]);

        $response = $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'updated-id',
            'sunat_client_secret' => '',
        ]);

        $response->assertOk();

        $cliente->refresh();
        $this->assertEquals('updated-id', $cliente->sunat_client_id);
        $this->assertEquals('existing-secret', $cliente->sunat_client_secret);
    }

    /**
     * Verifica que la pestaña de Credenciales API aparece en el expediente del cliente.
     */
    public function test_api_credentials_tab_is_visible_in_expediente(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'TAB TEST S.A.C.',
            'ruc' => '20100000005',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('Credenciales API');
        $response->assertSee("tab = 'api_credentials'", false);
        $response->assertSee('Client ID');
        $response->assertSee('Client Secret');
    }

    /**
     * Verifica que la pestaña muestra badge "OK" cuando hay Client ID configurado.
     */
    public function test_tab_shows_ok_badge_when_configured(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'BADGE TEST S.A.C.',
            'ruc' => '20100000006',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'configured-id',
            'sunat_client_secret' => 'configured-secret',
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('Configurado');
    }

    /**
     * Verifica que la pestaña muestra "Sin configurar" cuando no hay credenciales.
     */
    public function test_tab_shows_unconfigured_badge_when_empty(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPTY BADGE S.A.C.',
            'ruc' => '20100000007',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('Sin configurar');
    }

    /**
     * Verifica que la pestaña SIRE / Comprobantes y su bóveda se renderizan correctamente.
     */
    public function test_sire_tab_is_rendered_and_properly_positioned_in_expediente(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'SIRE EXPEDIENTE TEST S.A.C.',
            'ruc' => '20100000008',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.show', ['cliente' => $cliente, 'tab' => 'sire']));

        $response->assertOk();
        $response->assertSee('SIRE / Comprobantes');
        $response->assertSee("tab === 'sire'", false);
        $response->assertSee('Bóveda SIRE — Expediente Digital');
        $response->assertSee('Total Base Imponible');
        $response->assertSee('Total IGV');
        $response->assertSee('Total Facturado');
        $response->assertSee('Importar desde SUNAT');
        $response->assertSee('Sin comprobantes para este cliente');

        // Verificar que tab sire no está anidado dentro de operaciones
        $content = $response->getContent();
        $posOperaciones = strpos($content, "tab === 'operaciones'");
        $posSire = strpos($content, "tab === 'sire'");
        $posApi = strpos($content, "tab === 'api_credentials'");

        $this->assertNotFalse($posOperaciones);
        $this->assertNotFalse($posSire);
        $this->assertNotFalse($posApi);
        $this->assertTrue($posOperaciones < $posSire);
        $this->assertTrue($posSire < $posApi);
    }

    /**
     * Verifica que la validación rechaza un Client ID demasiado largo.
     */
    public function test_validation_rejects_oversized_client_id(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'VALIDATION TEST S.A.C.',
            'ruc' => '20100000008',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => str_repeat('x', 256),
            'sunat_client_secret' => 'valid',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('sunat_client_id');
    }

    /**
     * Verifica que la card de ayuda muestra las instrucciones de SUNAT SOL.
     */
    public function test_help_card_shows_sunat_sol_instructions(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'HELP TEST S.A.C.',
            'ruc' => '20100000009',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('SUNAT SOL');
        $response->assertSee('Gestión Credenciales de API SUNAT', false);
        $response->assertSee('MIGE RCE y RVIE - SIRE', false);
        $response->assertSee('AES-256-CBC');
    }

    /**
     * Verifica que el input de Client Secret muestra los puntos cuando existe una clave configurada.
     */
    public function test_client_secret_input_shows_masked_placeholder_when_configured(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'MASKED TEST S.A.C.',
            'ruc' => '20100000010',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'test-id',
            'sunat_client_secret' => 'secret-value',
        ]);

        $response = $this->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('••••••••••••••••');
        $response->assertSee('Deje este campo vacío si no desea modificar');
    }

    /**
     * Verifica que si sunat_client_secret contiene asteriscos o viñetas o la palabra password,
     * se hace unset() y se preserva la clave encriptada anterior en la base de datos.
     */
    public function test_masked_or_password_word_client_secret_does_not_overwrite_existing_secret(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'MASK PRESERVE S.A.C.',
            'ruc' => '20100000088',
            'regimen_tributario' => 'General',
            'estado' => true,
            'sunat_client_id' => 'existing-id',
            'sunat_client_secret' => 'my-original-secret==',
        ]);

        // 1. Envío con asteriscos
        $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'updated-id',
            'sunat_client_secret' => '********',
        ])->assertOk();

        $cliente->refresh();
        $this->assertEquals('updated-id', $cliente->sunat_client_id);
        $this->assertEquals('my-original-secret==', $cliente->sunat_client_secret);

        // 2. Envío con la palabra 'password'
        $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'updated-id-2',
            'sunat_client_secret' => 'password',
        ])->assertOk();

        $cliente->refresh();
        $this->assertEquals('updated-id-2', $cliente->sunat_client_id);
        $this->assertEquals('my-original-secret==', $cliente->sunat_client_secret);

        // 3. Envío con viñetas
        $this->putJson(route('clientes.api-credentials.update', $cliente), [
            'sunat_client_id' => 'updated-id-3',
            'sunat_client_secret' => '••••••••••••••••',
        ])->assertOk();

        $cliente->refresh();
        $this->assertEquals('updated-id-3', $cliente->sunat_client_id);
        $this->assertEquals('my-original-secret==', $cliente->sunat_client_secret);
    }

    /**
     * Verifica que los accesores usuario_sol y clave_sol en Cliente retornan los valores de credencial.
     */
    public function test_cliente_accessors_for_usuario_sol_and_clave_sol(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'ACCESSOR TEST S.A.C.',
            'ruc' => '20100000099',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'USUARIOTEST',
            'clave_sol' => 'ClaveSecreta123',
        ]);

        $this->assertEquals('USUARIOTEST', $cliente->usuario_sol);
        $this->assertEquals('ClaveSecreta123', $cliente->clave_sol);
    }
}
