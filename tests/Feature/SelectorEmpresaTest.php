<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use App\Services\Navegacion\SelectorEmpresa;
use App\Support\AccesosSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SelectorEmpresaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_receives_401_on_the_company_search(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->getJson(route('acceso-rapido.empresas'))
            ->assertUnauthorized();
    }

    public function test_top_bar_renders_the_selector_and_the_same_sunat_destinations(): void
    {
        $cartera = $this->cartera();

        $response = $this->actingAs($cartera['admin'])->get(route('perfil.edit'));

        $response->assertSee('data-selector-empresa', false);
        $response->assertSee('data-empresa-id=""', false);
        $response->assertSee('IMPORTACIONES VIRGEN DE CHAPI SRL', false);
        $response->assertSee('Seleccionar empresa');
        $response->assertSee('Buscar empresa...');
        $response->assertSee('+ Agregar empresa');
        $response->assertSee('Accesos SUNAT');
        $response->assertSee(route('clientes.create'), false);
        $response->assertDontSee($cartera['asignada']->ruc, false);

        foreach (AccesosSunat::destinos() as $destino) {
            $response->assertSee($destino['etiqueta']);
            $response->assertSee($destino['detalle']);
        }
    }

    public function test_search_returns_only_the_assistant_portfolio_ordered_by_name(): void
    {
        $cartera = $this->cartera();

        $response = $this->actingAs($cartera['asistente'])
            ->getJson(route('acceso-rapido.empresas'));

        $response->assertOk();
        $response->assertJsonPath('hay_mas', false);
        $response->assertJsonCount(1, 'empresas');
        $response->assertJsonPath('empresas.0.razon_social', 'ASIGNADA DEMO SAC');
        $response->assertJsonPath('empresas.0.ruc', $cartera['asignada']->ruc);
        $response->assertJsonPath('empresas.0.sunat', route('clientes.sunat', $cartera['asignada']));
        $response->assertJsonPath('empresas.0.sunafil', route('clientes.sunafil', $cartera['asignada']));
        $response->assertJsonPath('empresas.0.carpeta', route('clientes.show', $cartera['asignada']));
        $response->assertJsonMissing(['razon_social' => 'PRIVADA DEMO SAC']);
        $response->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_search_matches_name_or_ruc_and_ignores_like_wildcards(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->getJson(route('acceso-rapido.empresas', ['q' => 'asignada']))
            ->assertOk()
            ->assertJsonCount(1, 'empresas')
            ->assertJsonPath('empresas.0.id', $cartera['asignada']->id);

        $this->getJson(route('acceso-rapido.empresas', ['q' => $cartera['privada']->ruc]))
            ->assertOk()
            ->assertJsonCount(1, 'empresas')
            ->assertJsonPath('empresas.0.id', $cartera['privada']->id);

        $this->getJson(route('acceso-rapido.empresas', ['q' => '%']))
            ->assertOk()
            ->assertJsonCount(0, 'empresas');
    }

    public function test_search_includes_withdrawn_clients_and_hides_trashed_ones(): void
    {
        $cartera = $this->cartera();
        $cartera['asignada']->delete();

        $response = $this->actingAs($cartera['admin'])
            ->getJson(route('acceso-rapido.empresas', ['q' => 'BAJA']));

        $response->assertOk();
        $response->assertJsonCount(1, 'empresas');
        $response->assertJsonPath('empresas.0.razon_social', 'BAJA DEMO SAC');
        $response->assertJsonPath('empresas.0.estado_etiqueta', 'Baja');
        $response->assertJsonMissing(['razon_social' => 'ASIGNADA DEMO SAC']);
    }

    public function test_idle_list_returns_the_first_five_companies_and_search_finds_the_rest(): void
    {
        $estudio = Estudio::factory()->create();
        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);

        $empresas = collect(range(1, 6))->map(
            fn (int $numero): Cliente => Cliente::factory()->paraEstudio($estudio)->create([
                'razon_social' => sprintf('%02d EMPRESA DEMO SAC', $numero),
            ]),
        );

        $this->actingAs($admin)
            ->getJson(route('acceso-rapido.empresas'))
            ->assertOk()
            ->assertJsonPath('hay_mas', true)
            ->assertJsonCount(SelectorEmpresa::VISIBLES, 'empresas')
            ->assertJsonPath('empresas.0.id', $empresas[0]->id)
            ->assertJsonPath('empresas.4.id', $empresas[4]->id)
            ->assertJsonMissing(['id' => $empresas[5]->id]);

        $this->getJson(route('acceso-rapido.empresas', ['q' => '06 EMPRESA']))
            ->assertOk()
            ->assertJsonCount(1, 'empresas')
            ->assertJsonPath('empresas.0.id', $empresas[5]->id)
            ->assertJsonPath('empresas.0.fijada', false);
    }

    public function test_search_pages_matches_beyond_the_idle_window(): void
    {
        $estudio = Estudio::factory()->create();
        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);
        Cliente::factory()->count(SelectorEmpresa::LIMITE + 1)->paraEstudio($estudio)->create([
            'razon_social' => 'LOTE BUSQUEDA SAC',
        ]);

        $this->actingAs($admin)
            ->getJson(route('acceso-rapido.empresas', ['q' => 'LOTE BUSQUEDA']))
            ->assertOk()
            ->assertJsonPath('hay_mas', true)
            ->assertJsonCount(SelectorEmpresa::LIMITE, 'empresas');
    }

    public function test_pinning_a_company_keeps_it_in_the_first_five(): void
    {
        $estudio = Estudio::factory()->create();
        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);

        $empresas = collect(range(1, 6))->map(
            fn (int $numero): Cliente => Cliente::factory()->paraEstudio($estudio)->create([
                'razon_social' => sprintf('%02d FIJA DEMO SAC', $numero),
            ]),
        );

        $this->actingAs($admin)
            ->postJson(route('acceso-rapido.fijar'), ['cliente_id' => $empresas[5]->id])
            ->assertOk()
            ->assertJsonPath('fijada', true)
            ->assertJsonPath('empresa.id', $empresas[5]->id);

        $this->getJson(route('acceso-rapido.empresas'))
            ->assertOk()
            ->assertJsonCount(SelectorEmpresa::VISIBLES, 'empresas')
            ->assertJsonPath('empresas.0.id', $empresas[5]->id)
            ->assertJsonPath('empresas.0.fijada', true)
            ->assertJsonMissing(['id' => $empresas[4]->id]);

        $this->postJson(route('acceso-rapido.fijar'), ['cliente_id' => $empresas[5]->id])
            ->assertOk()
            ->assertJsonPath('fijada', false);

        $this->getJson(route('acceso-rapido.empresas'))
            ->assertOk()
            ->assertJsonPath('empresas.0.id', $empresas[0]->id)
            ->assertJsonMissing(['id' => $empresas[5]->id]);
    }

    public function test_pinning_a_sixth_company_returns_422(): void
    {
        $estudio = Estudio::factory()->create();
        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);

        $empresas = collect(range(1, 6))->map(
            fn (int $numero): Cliente => Cliente::factory()->paraEstudio($estudio)->create([
                'razon_social' => sprintf('%02d CUPO DEMO SAC', $numero),
            ]),
        );

        $this->actingAs($admin);

        foreach ($empresas->take(5) as $empresa) {
            $this->postJson(route('acceso-rapido.fijar'), ['cliente_id' => $empresa->id])
                ->assertOk()
                ->assertJsonPath('fijada', true);
        }

        $this->postJson(route('acceso-rapido.fijar'), ['cliente_id' => $empresas[5]->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id'])
            ->assertJsonPath('errors.cliente_id.0', 'Ya tienes 5 empresas fijas. Quita una para fijar otra.');

        $this->assertDatabaseMissing('empresa_fijas', [
            'user_id' => $admin->id,
            'cliente_id' => $empresas[5]->id,
        ]);
    }

    public function test_assistant_cannot_pin_a_company_outside_the_portfolio(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['asistente'])
            ->postJson(route('acceso-rapido.fijar'), [
                'cliente_id' => $cartera['privada']->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('empresa_fijas', 0);
    }

    public function test_pinning_a_company_from_another_firm_returns_404(): void
    {
        $cartera = $this->cartera();
        $ajena = Cliente::factory()->create([
            'razon_social' => 'FIJA AJENA SAC',
        ]);

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.fijar'), [
                'cliente_id' => $ajena->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('empresa_fijas', 0);
    }

    public function test_a_deleted_company_releases_its_pin(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.fijar'), [
                'cliente_id' => $cartera['asignada']->id,
            ])
            ->assertOk();

        $cartera['asignada']->delete();

        $this->getJson(route('acceso-rapido.empresas'))
            ->assertOk()
            ->assertJsonMissing(['id' => $cartera['asignada']->id]);

        $this->assertDatabaseMissing('empresa_fijas', [
            'cliente_id' => $cartera['asignada']->id,
        ]);
    }

    public function test_guest_cannot_pin_a_company(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->postJson(route('acceso-rapido.fijar'), ['cliente_id' => 1])
            ->assertUnauthorized();
    }

    public function test_selecting_a_company_remembers_it_for_the_rest_of_the_crm(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.seleccionar'), [
                'cliente_id' => $cartera['privada']->id,
            ])
            ->assertOk()
            ->assertJsonPath('empresa.id', $cartera['privada']->id)
            ->assertJsonPath('empresa.ruc', $cartera['privada']->ruc);

        $this->get(route('perfil.edit'))
            ->assertSee('data-empresa-id="'.$cartera['privada']->id.'"', false)
            ->assertSee($cartera['privada']->razon_social)
            ->assertSee($cartera['privada']->ruc);
    }

    public function test_opening_a_dossier_replaces_the_remembered_company(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.seleccionar'), [
                'cliente_id' => $cartera['privada']->id,
            ])
            ->assertOk();

        $this->get(route('clientes.show', $cartera['asignada']))
            ->assertSee('data-empresa-id="'.$cartera['asignada']->id.'"', false);

        $this->assertSame($cartera['asignada']->id, session(SelectorEmpresa::SESSION_KEY));

        $this->get(route('perfil.edit'))
            ->assertSee('data-empresa-id="'.$cartera['asignada']->id.'"', false);
    }

    public function test_launching_sunat_for_another_company_does_not_replace_the_open_dossier(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])->get(route('clientes.show', $cartera['asignada']));

        $this->getJson(route('clientes.sunat', $cartera['privada']))->assertUnprocessable();

        $this->assertSame($cartera['asignada']->id, session(SelectorEmpresa::SESSION_KEY));
    }

    public function test_assistant_cannot_select_a_company_outside_the_portfolio(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['asistente'])
            ->postJson(route('acceso-rapido.seleccionar'), [
                'cliente_id' => $cartera['privada']->id,
            ])
            ->assertNotFound();

        $this->assertNull(session(SelectorEmpresa::SESSION_KEY));
    }

    public function test_selecting_a_company_from_another_firm_returns_404(): void
    {
        $cartera = $this->cartera();
        $ajena = Cliente::factory()->create([
            'razon_social' => 'OTRO ESTUDIO SAC',
        ]);

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.seleccionar'), [
                'cliente_id' => $ajena->id,
            ])
            ->assertNotFound();

        $this->getJson(route('acceso-rapido.empresas', ['q' => 'OTRO ESTUDIO']))
            ->assertOk()
            ->assertJsonCount(0, 'empresas');
    }

    public function test_selection_requires_a_company_id(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.seleccionar'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['cliente_id']);
    }

    public function test_a_forgotten_company_is_dropped_from_the_bar(): void
    {
        $cartera = $this->cartera();

        $this->actingAs($cartera['admin'])
            ->postJson(route('acceso-rapido.seleccionar'), [
                'cliente_id' => $cartera['asignada']->id,
            ])
            ->assertOk();

        $cartera['asignada']->delete();

        $this->get(route('perfil.edit'))
            ->assertSee('data-empresa-id=""', false)
            ->assertSee('Seleccionar empresa')
            ->assertDontSee($cartera['asignada']->razon_social);

        $this->assertNull(session(SelectorEmpresa::SESSION_KEY));
    }

    public function test_the_bar_escapes_the_company_name(): void
    {
        $estudio = Estudio::factory()->create();
        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);
        $cliente = Cliente::factory()->paraEstudio($estudio)->create([
            'razon_social' => '<script>alert(1)</script> SAC',
        ]);

        $response = $this->actingAs($admin)->get(route('clientes.show', $cliente));

        $response->assertSee('data-empresa-id="'.$cliente->id.'"', false);
        $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    /**
     * @return array{admin: User, asistente: User, asignada: Cliente, privada: Cliente, baja: Cliente}
     */
    private function cartera(): array
    {
        $estudio = Estudio::factory()->create();

        $admin = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);

        $asistente = User::factory()->asistente()->create([
            'estudio_id' => $estudio->id,
        ]);

        $asignada = Cliente::factory()->paraEstudio($estudio)->create([
            'razon_social' => 'ASIGNADA DEMO SAC',
        ]);

        $privada = Cliente::factory()->paraEstudio($estudio)->create([
            'razon_social' => 'PRIVADA DEMO SAC',
        ]);

        $baja = Cliente::factory()->paraEstudio($estudio)->retirado()->create([
            'razon_social' => 'BAJA DEMO SAC',
        ]);

        $asistente->clientes()->attach($asignada->id);

        return [
            'admin' => $admin,
            'asistente' => $asistente,
            'asignada' => $asignada,
            'privada' => $privada,
            'baja' => $baja,
        ];
    }
}
