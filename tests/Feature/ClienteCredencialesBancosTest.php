<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClienteCredencialesBancosTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_can_update_credenciales_with_additional_banking_accesses(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'COMERCIAL Y SERVICIOS ANDINOS S.A.C.',
            'ruc' => '20609988771',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.credenciales.update', $cliente), [
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSol123*',
            'dni_titular' => '44556677',
            'cuenta_detracciones' => '00-011-998877',
            'clave_detracciones' => 'BnClave9988!',
            // Bloque 1: BCP
            'banco_1_nombre' => 'Telecrédito BCP',
            'banco_1_usuario' => '4557 1122 3344 5566',
            'banco_1_clave' => 'BcpSecurePass2026',
            'banco_1_notas' => 'Token digital activo en celular gerencial',
            // Bloque 2: BBVA
            'banco_2_nombre' => 'BBVA Net Cash',
            'banco_2_usuario' => 'BBVA-USR-7788',
            'banco_2_clave' => 'BbvaMasterPass#1',
            'banco_2_notas' => 'Solo para descarga de extractos bancarios mensuales',
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHas('success', 'Credenciales de acceso actualizadas correctamente.');

        $credencial = Credencial::where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($credencial);

        // Validar que a través de Eloquent se lean los valores descifrados
        $this->assertEquals('Telecrédito BCP', $credencial->banco_1_nombre);
        $this->assertEquals('4557 1122 3344 5566', $credencial->banco_1_usuario);
        $this->assertEquals('BcpSecurePass2026', $credencial->banco_1_clave);
        $this->assertEquals('Token digital activo en celular gerencial', $credencial->banco_1_notas);

        $this->assertEquals('BBVA Net Cash', $credencial->banco_2_nombre);
        $this->assertEquals('BBVA-USR-7788', $credencial->banco_2_usuario);
        $this->assertEquals('BbvaMasterPass#1', $credencial->banco_2_clave);
        $this->assertEquals('Solo para descarga de extractos bancarios mensuales', $credencial->banco_2_notas);

        // Validar que en la base de datos cruda las contraseñas estén encriptadas (AES-256)
        $raw = DB::table('credenciales')->where('id', $credencial->id)->first();
        $this->assertNotEquals('BcpSecurePass2026', $raw->banco_1_clave);
        $this->assertNotEquals('BbvaMasterPass#1', $raw->banco_2_clave);
    }

    public function test_show_page_displays_empty_and_filled_additional_credentials(): void
    {
        // Cliente sin accesos adicionales configurados
        $clienteVacio = Cliente::create([
            'razon_social' => 'EMPRESA SIN BANCOS S.A.C.',
            'ruc' => '20112233445',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
        $clienteVacio->credencial()->create([]);

        $response = $this->get(route('clientes.show', $clienteVacio));
        $response->assertOk();
        $response->assertSee('Acceso Adicional 1 (Libre / Bancos)');
        $response->assertSee('Acceso Adicional 2 (Libre / Bancos)');

        // Cliente con accesos adicionales configurados
        $clienteConfigurado = Cliente::create([
            'razon_social' => 'EMPRESA CON BANCOS S.A.C.',
            'ruc' => '20998877665',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        $clienteConfigurado->credencial()->create([
            'banco_1_nombre' => 'Telecrédito BCP Personalizado',
            'banco_1_usuario' => 'BCP-USER-12345',
            'banco_1_clave' => 'MiClaveSecreta',
            'banco_1_notas' => 'Notas del banco 1',
        ]);

        $response2 = $this->get(route('clientes.show', $clienteConfigurado));
        $response2->assertOk();
        $response2->assertSee('Telecrédito BCP Personalizado');
        $response2->assertSee('BCP-USER-12345');
        $response2->assertSee('Notas del banco 1');
    }

    public function test_can_store_new_cliente_with_additional_credentials(): void
    {
        $response = $this->post(route('clientes.store'), [
            'razon_social' => 'NUEVO CLIENTE CON BCP S.A.C.',
            'ruc' => '20601234568',
            'regimen_tributario' => 'MYPE',
            'correo' => 'cliente.nuevo@ejemplo.com',
            'telefono' => '987654321',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSol123',
            'banco_1_nombre' => 'Portal BCP',
            'banco_1_usuario' => '4557999900001111',
            'banco_1_clave' => 'PassBcp777',
            'banco_1_notas' => 'Clave de 6 dígitos',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('ruc', '20601234568')->first();
        $this->assertNotNull($cliente);
        $this->assertNotNull($cliente->credencial);
        $this->assertEquals('Portal BCP', $cliente->credencial->banco_1_nombre);
        $this->assertEquals('4557999900001111', $cliente->credencial->banco_1_usuario);
        $this->assertEquals('PassBcp777', $cliente->credencial->banco_1_clave);
        $this->assertEquals('Clave de 6 dígitos', $cliente->credencial->banco_1_notas);
        $this->assertNull($cliente->credencial->banco_1_url);
    }

    public function test_store_guarda_los_enlaces_de_los_portales_al_crear_el_cliente(): void
    {
        $response = $this->post(route('clientes.store'), [
            'razon_social' => 'NUEVO CLIENTE CON ENLACES S.A.C.',
            'ruc' => '20603332221',
            'regimen_tributario' => 'MYPE',
            'url_afp' => Credencial::ENLACE_AFP,
            'url_bn' => 'https://nuevo-bn.example/login',
            'banco_1_nombre' => 'Telecrédito BCP',
            'banco_1_url' => 'https://www.viabcp.com/login',
            'banco_2_nombre' => 'BBVA Net Cash',
            'banco_2_url' => 'https://www.bbvanetcash.com/login',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('ruc', '20603332221')->first();
        $this->assertNotNull($cliente);
        $this->assertNull($cliente->credencial->url_afp);
        $this->assertEquals('https://nuevo-bn.example/login', $cliente->credencial->url_bn);
        $this->assertEquals('https://www.viabcp.com/login', $cliente->credencial->banco_1_url);
        $this->assertEquals('https://www.bbvanetcash.com/login', $cliente->credencial->banco_2_url);

        $pagina = $this->get(route('clientes.show', $cliente));
        $pagina->assertOk();
        $pagina->assertSee(Credencial::ENLACE_AFP, false);
        $pagina->assertSee('https://nuevo-bn.example/login', false);
        $pagina->assertSee('Abrir Telecrédito BCP', false);
        $pagina->assertSee('Abrir BBVA Net Cash', false);
    }

    public function test_store_rechaza_un_enlace_que_no_es_http(): void
    {
        $response = $this->from(route('clientes.create'))
            ->post(route('clientes.store'), [
                'razon_social' => 'CLIENTE CON ENLACE INVALIDO S.A.C.',
                'ruc' => '20604443331',
                'regimen_tributario' => 'MYPE',
                'banco_1_url' => 'javascript:alert(1)',
            ]);

        $response->assertRedirect(route('clientes.create'));
        $response->assertSessionHasErrors('banco_1_url');
        $this->assertNull(Cliente::where('ruc', '20604443331')->first());
    }

    public function test_update_credenciales_muestra_el_enlace_de_cada_acceso_adicional(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CON ENLACES BANCARIOS S.A.C.',
            'ruc' => '20608887771',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.credenciales.update', $cliente), [
            'banco_1_nombre' => 'Telecrédito BCP',
            'banco_1_url' => 'https://www.viabcp.com/login',
            'banco_2_nombre' => 'BBVA Net Cash',
            'banco_2_url' => 'https://www.bbvanetcash.com/login',
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));

        $credencial = $cliente->fresh()->credencial;
        $this->assertEquals('https://www.viabcp.com/login', $credencial->banco_1_url);
        $this->assertEquals('https://www.bbvanetcash.com/login', $credencial->banco_2_url);

        $pagina = $this->get(route('clientes.show', $cliente));
        $pagina->assertSee('https://www.viabcp.com/login', false);
        $pagina->assertSee('Abrir Telecrédito BCP', false);
        $pagina->assertSee('https://www.bbvanetcash.com/login', false);
        $pagina->assertSee('Abrir BBVA Net Cash', false);
    }

    public function test_update_credenciales_permite_cambiar_el_enlace_de_afpnet_y_del_banco_de_la_nacion(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CON PORTALES EDITABLES S.A.C.',
            'ruc' => '20607776661',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.credenciales.update', $cliente), [
            'url_afp' => Credencial::ENLACE_AFP,
            'url_bn' => 'https://nuevo-bn.example/login',
        ]);

        $response->assertRedirect(route('clientes.show', $cliente));

        $credencial = $cliente->fresh()->credencial;
        $this->assertNull($credencial->url_afp);
        $this->assertEquals('https://nuevo-bn.example/login', $credencial->url_bn);
        $this->assertEquals(Credencial::ENLACE_AFP, $credencial->enlaceAfp());
        $this->assertEquals('https://nuevo-bn.example/login', $credencial->enlaceBn());

        $pagina = $this->get(route('clientes.show', $cliente));
        $pagina->assertSee(Credencial::ENLACE_AFP, false);
        $pagina->assertSee('https://nuevo-bn.example/login', false);
        $pagina->assertSee('Abrir BN por Internet', false);
    }

    public function test_update_credenciales_rechaza_un_enlace_que_no_es_http(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CON ENLACE INVALIDO S.A.C.',
            'ruc' => '20606665551',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->from(route('clientes.show', $cliente))
            ->put(route('clientes.credenciales.update', $cliente), [
                'banco_1_url' => 'javascript:alert(1)',
            ]);

        $response->assertRedirect(route('clientes.show', $cliente));
        $response->assertSessionHasErrors('banco_1_url');
        $this->assertNull($cliente->fresh()->credencial);
    }
}
