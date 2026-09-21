<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClientePadronSunatTest extends TestCase
{
    use RefreshDatabase;

    public function test_validar_ruc_returns_full_padron_sunat_data(): void
    {
        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero=20614054230' => Http::response([
                'nombre' => 'QORI PORACOTA S.A.C.',
                'tipoDocumento' => '6',
                'numeroDocumento' => '20614054230',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
                'ubigeo' => '040409',
                'distrito' => 'ORCOPAMPA',
                'provincia' => 'CASTILLA',
                'departamento' => 'AREQUIPA',
            ], 200),
        ]);

        $response = $this->postJson(route('clientes.validar-ruc'), [
            'ruc' => '20614054230',
        ]);

        $response->assertOk();
        $response->assertJson([
            'valid' => true,
            'razon_social' => 'QORI PORACOTA S.A.C.',
            'estado_sunat' => 'ACTIVO',
            'condicion_sunat' => 'HABIDO',
            'direccion_sunat' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
            'ubigeo' => '040409',
            'distrito' => 'ORCOPAMPA',
            'provincia' => 'CASTILLA',
            'departamento' => 'AREQUIPA',
            'tipo_persona' => 'Persona Jurídica (Empresa / Sociedad)',
        ]);
    }

    public function test_cliente_store_persists_padron_sunat_fields(): void
    {
        $response = $this->post(route('clientes.store'), [
            'ruc' => '20614054230',
            'razon_social' => 'QORI PORACOTA S.A.C.',
            'regimen_tributario' => 'MYPE',
            'estado_contribuyente' => 'ACTIVO',
            'condicion_domicilio' => 'HABIDO',
            'tipo_contribuyente' => 'Persona Jurídica (Empresa / Sociedad)',
            'direccion_fiscal' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
            'ubigeo' => '040409',
            'distrito' => 'ORCOPAMPA',
            'provincia' => 'CASTILLA',
            'departamento' => 'AREQUIPA',
            'correo' => 'contacto@qoriporacota.pe',
            'telefono' => '925147601',
            'usuario_sol' => '43757702',
            'clave_sol' => 'Qori6140',
        ]);

        $response->assertRedirect(route('clientes.index'));

        $this->assertDatabaseHas('clientes', [
            'ruc' => '20614054230',
            'razon_social' => 'QORI PORACOTA S.A.C.',
            'estado_contribuyente' => 'ACTIVO',
            'condicion_domicilio' => 'HABIDO',
            'direccion_fiscal' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
            'ubigeo' => '040409',
            'distrito' => 'ORCOPAMPA',
        ]);

        $cliente = Cliente::where('ruc', '20614054230')->first();
        $this->assertNotNull($cliente->fecha_sincronizacion_sunat);
    }

    public function test_actualizar_estado_sunat_endpoint_refreshes_client_data(): void
    {
        $cliente = Cliente::create([
            'ruc' => '20614054230',
            'razon_social' => 'QORI ANTIGUO S.A.C.',
            'regimen_tributario' => 'General',
            'estado_contribuyente' => 'SUSPENSION TEMPORAL',
            'condicion_domicilio' => 'NO HABIDO',
            'estado' => true,
        ]);

        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero=20614054230' => Http::response([
                'nombre' => 'QORI PORACOTA S.A.C.',
                'tipoDocumento' => '6',
                'numeroDocumento' => '20614054230',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
                'ubigeo' => '040409',
                'distrito' => 'ORCOPAMPA',
                'provincia' => 'CASTILLA',
                'departamento' => 'AREQUIPA',
            ], 200),
        ]);

        $response = $this->postJson(route('clientes.actualizar-estado-sunat', $cliente));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'cliente' => [
                'razon_social' => 'QORI PORACOTA S.A.C.',
                'estado_contribuyente' => 'ACTIVO',
                'condicion_domicilio' => 'HABIDO',
                'direccion_fiscal' => 'CAL. 4 MZA. G LOTE 1 URB. LA LAGUNA',
                'distrito' => 'ORCOPAMPA',
            ],
        ]);

        $cliente->refresh();
        $this->assertEquals('ACTIVO', $cliente->estado_contribuyente);
        $this->assertEquals('HABIDO', $cliente->condicion_domicilio);
        $this->assertEquals('QORI PORACOTA S.A.C.', $cliente->razon_social);
        $this->assertNotNull($cliente->fecha_sincronizacion_sunat);
    }

    public function test_artisan_command_actualizar_estados_sunat(): void
    {
        $cliente = Cliente::create([
            'ruc' => '20614054230',
            'razon_social' => 'QORI INICIAL',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        Http::fake([
            'https://api.apis.net.pe/v1/ruc?numero=20614054230' => Http::response([
                'nombre' => 'QORI PORACOTA S.A.C.',
                'estado' => 'ACTIVO',
                'condicion' => 'HABIDO',
                'direccion' => 'CAL. 4 MZA. G LOTE 1',
                'ubigeo' => '040409',
                'distrito' => 'ORCOPAMPA',
                'provincia' => 'CASTILLA',
                'departamento' => 'AREQUIPA',
            ], 200),
        ]);

        $exitCode = Artisan::call('sunat:actualizar-estados', ['ruc' => '20614054230']);

        $this->assertEquals(0, $exitCode);

        $cliente->refresh();
        $this->assertEquals('ACTIVO', $cliente->estado_contribuyente);
        $this->assertEquals('HABIDO', $cliente->condicion_domicilio);
        $this->assertEquals('ORCOPAMPA', $cliente->distrito);
    }
}
