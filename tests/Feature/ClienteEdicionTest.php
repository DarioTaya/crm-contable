<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credencial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClienteEdicionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_datos_fiscales_successfully(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA ORIGINAL S.A.C.',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'correo' => 'original@empresa.pe',
            'telefono' => '999888777',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.datos-fiscales.update', $cliente), [
            'regimen_tributario' => 'General',
            'correo' => 'nuevo@empresa.pe',
            'telefono' => '911222333',
        ]);

        $response->assertRedirect(route('clientes.show', ['cliente' => $cliente, 'tab' => 'datos']));
        $response->assertSessionHas('success', 'Datos fiscales actualizados correctamente.');

        $cliente->refresh();
        $this->assertEquals('General', $cliente->regimen_tributario);
        $this->assertEquals('nuevo@empresa.pe', $cliente->correo);
        $this->assertEquals('911222333', $cliente->telefono);
    }

    public function test_update_datos_fiscales_strictly_protects_ruc_and_razon_social(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA INMUTABLE S.A.C.',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'correo' => 'info@empresa.pe',
            'telefono' => '999000111',
            'estado' => true,
        ]);

        // Intento de manipulación de RUC y Razón Social en el payload
        $this->put(route('clientes.datos-fiscales.update', $cliente), [
            'ruc' => '20999999999',
            'razon_social' => 'INTENTO DE CAMBIO FRAUDULENTO',
            'regimen_tributario' => 'RER',
            'correo' => 'nuevo@empresa.pe',
            'telefono' => '999000222',
        ]);

        $cliente->refresh();
        // RUC y Razón Social no deben haber cambiado bajo ninguna circunstancia
        $this->assertEquals('20123456789', $cliente->ruc);
        $this->assertEquals('EMPRESA INMUTABLE S.A.C.', $cliente->razon_social);
        $this->assertEquals('RER', $cliente->regimen_tributario);
    }

    public function test_update_datos_fiscales_validates_regimen(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'TEST S.A.C.',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.datos-fiscales.update', $cliente), [
            'regimen_tributario' => 'REGIMEN_INVALIDO',
        ]);

        $response->assertSessionHasErrors(['regimen_tributario']);
    }

    public function test_can_update_and_create_credenciales_with_cuenta_detracciones(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CON DETRACCIONES S.A.C.',
            'ruc' => '20555666777',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->put(route('clientes.credenciales.update', $cliente), [
            'usuario_sol' => 'MODDATOS2026',
            'clave_sol' => 'SolClave9988*',
            'usuario_afp' => 'AFPNET2026',
            'clave_afp' => 'AfpPass1234#',
            'dni_titular' => '12345678',
            'cuenta_detracciones' => '00-058-123456',
            'clave_detracciones' => 'BnClave5544!',
        ]);

        $response->assertRedirect(route('clientes.show', ['cliente' => $cliente, 'tab' => 'datos']));
        $response->assertSessionHas('success', 'Credenciales de acceso actualizadas correctamente.');

        $credencial = Credencial::where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($credencial);
        $this->assertEquals('MODDATOS2026', $credencial->usuario_sol);
        $this->assertEquals('SolClave9988*', $credencial->clave_sol);
        $this->assertEquals('12345678', $credencial->dni_titular);
        $this->assertEquals('00-058-123456', $credencial->cuenta_detracciones);
        $this->assertEquals('BnClave5544!', $credencial->clave_detracciones);

        // Verificar que en base de datos la clave se almacene cifrada
        $raw = DB::table('credenciales')->where('id', $credencial->id)->first();
        $this->assertNotEquals('SolClave9988*', $raw->clave_sol);
        $this->assertNotEquals('BnClave5544!', $raw->clave_detracciones);
    }

    public function test_clientes_index_displays_active_and_muted_detracciones_buttons(): void
    {
        // Cliente 1: Con cuenta de detracciones
        $clienteConDetraccion = Cliente::create([
            'razon_social' => 'EMPRESA DETRACCION S.A.C.',
            'ruc' => '20111111111',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        $clienteConDetraccion->credencial()->create([
            'cuenta_detracciones' => '00-012-345678',
        ]);

        // Cliente 2: Sin cuenta de detracciones
        Cliente::create([
            'razon_social' => 'EMPRESA SIN DETRACCION E.I.R.L.',
            'ruc' => '20222222222',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.index'));
        $response->assertOk();

        // Cliente 1 debe tener el enlace activo con https://bancaporinternet.bn.com.pe/BNWeb/Inicio
        $response->assertSee('https://bancaporinternet.bn.com.pe/BNWeb/Inicio');
        $response->assertSee('00-012-345678');
        $response->assertSee('DETRAC.');

        // Cliente 2 debe tener el botón apagado aludiendo sin cuenta
        $response->assertSee('Sin cuenta de detracciones registrada');
    }

    public function test_clientes_index_displays_active_and_muted_afp_buttons(): void
    {
        // Cliente 1: Con credencial AFP
        $clienteConAfp = Cliente::create([
            'razon_social' => 'EMPRESA CON AFP S.A.C.',
            'ruc' => '20333333333',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        $clienteConAfp->credencial()->create([
            'usuario_afp' => 'USERAFP2026',
            'clave_afp' => 'AfpSecret123',
        ]);

        // Cliente 2: Sin credencial AFP
        Cliente::create([
            'razon_social' => 'EMPRESA SIN AFP S.A.C.',
            'ruc' => '20444444444',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->get(route('clientes.index'));
        $response->assertOk();

        // Cliente 1 debe tener el enlace activo con https://www.afpnet.com.pe/
        $response->assertSee('https://www.afpnet.com.pe/');
        $response->assertSee('USERAFP2026');
        $response->assertSee('AFP');

        // Cliente 2 debe tener el botón apagado
        $response->assertSee('Sin credenciales AFPnet registradas');
    }

    public function test_can_create_cliente_with_cuenta_detracciones_via_store(): void
    {
        $response = $this->post(route('clientes.store'), [
            'razon_social' => 'NUEVA EMPRESA ONBOARDING S.A.C.',
            'ruc' => '20987654321',
            'regimen_tributario' => 'MYPE',
            'correo' => 'onboarding@empresa.pe',
            'telefono' => '987000111',
            'usuario_sol' => 'MODSOL',
            'clave_sol' => 'Clave123*',
            'dni_titular' => '87654321',
            'cuenta_detracciones' => '00-999-888777',
            'clave_detracciones' => 'ClaveBn123*',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $cliente = Cliente::where('ruc', '20987654321')->first();
        $this->assertNotNull($cliente);
        $this->assertNotNull($cliente->credencial);
        $this->assertEquals('00-999-888777', $cliente->credencial->cuenta_detracciones);
    }

    public function test_expediente_digital_displays_direct_links_for_afpnet_and_detracciones(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'EMPRESA CON EXPEDIENTE DIGITAL S.A.C.',
            'ruc' => '20777888999',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);
        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSol123*',
        ]);

        $response = $this->get(route('clientes.show', $cliente));
        $response->assertOk();

        // Enlace oficial de AFPnet en Expediente Digital
        $response->assertSee('https://www.afpnet.com.pe/');
        $response->assertSee('Abrir AFPnet');

        // Enlace oficial de Banco de la Nación Detracciones en Expediente Digital
        $response->assertSee('https://bancaporinternet.bn.com.pe/BNWeb/Inicio');
        $response->assertSee('Abrir BN por Internet');
    }
}
