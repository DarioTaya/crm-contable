<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ExpedienteClienteRutasTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_y_cada_seccion_responden_en_su_propia_url(): void
    {
        $cliente = $this->cliente();

        $this->get(route('clientes.show', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="datos"', false)
            ->assertSee('/clientes/'.$cliente->id.'/notificaciones', false)
            ->assertSee('/clientes/'.$cliente->id.'/impuestos', false)
            ->assertDontSee('/clientes/'.$cliente->id.'/operaciones', false)
            ->assertSee('/clientes/'.$cliente->id.'/sire', false);

        $this->get(route('clientes.notificaciones', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="notificaciones"', false);

        $this->get(route('clientes.operaciones', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="operaciones"', false);

        $this->get(route('clientes.sire', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="sire"', false);
    }

    public function test_la_url_antigua_de_operaciones_abre_impuestos(): void
    {
        $cliente = $this->cliente();

        $this->assertSame(
            url('/clientes/'.$cliente->id.'/impuestos'),
            route('clientes.operaciones', $cliente),
        );

        $this->get('/clientes/'.$cliente->id.'/operaciones?anio=2025')
            ->assertMovedPermanently()
            ->assertRedirectToRoute('clientes.operaciones', [
                'cliente' => $cliente,
                'anio' => '2025',
            ]);
    }

    #[TestWith(['notificaciones', 'clientes.notificaciones'])]
    #[TestWith(['operaciones', 'clientes.operaciones'])]
    #[TestWith(['sire', 'clientes.sire'])]
    #[TestWith(['datos', 'clientes.show'])]
    #[TestWith(['api_credentials', 'clientes.show'])]
    #[TestWith(['desconocida', 'clientes.show'])]
    public function test_el_enlace_antiguo_con_tab_redirige_a_la_seccion(string $tab, string $ruta): void
    {
        $cliente = $this->cliente();

        $this->get(route('clientes.show', ['cliente' => $cliente, 'tab' => $tab]))
            ->assertMovedPermanently()
            ->assertRedirectToRoute($ruta, $cliente);
    }

    public function test_el_enlace_antiguo_de_sire_conserva_los_filtros(): void
    {
        $cliente = $this->cliente();

        $this->get(route('clientes.show', [
            'cliente' => $cliente,
            'tab' => 'sire',
            'sire_tipo' => 'RCE',
            'sire_periodo' => '2026-01',
        ]))->assertMovedPermanently()
            ->assertRedirectToRoute('clientes.sire', [
                'cliente' => $cliente,
                'sire_tipo' => 'RCE',
                'sire_periodo' => '2026-01',
            ]);
    }

    public function test_el_filtro_de_sire_permanece_en_la_ruta_de_la_seccion(): void
    {
        $cliente = $this->cliente();

        $this->get(route('clientes.sire', [
            'cliente' => $cliente,
            'sire_tipo' => 'RCE',
            'sire_periodo' => '2026-01',
        ]))->assertOk()
            ->assertSee('data-seccion="sire"', false)
            ->assertSee('value="RCE" selected', false)
            ->assertSee('value="2026-01"', false);
    }

    private function cliente(): Cliente
    {
        return Cliente::create([
            'razon_social' => 'EL CHALACO MAJEÑO E.I.R.L.',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);
    }
}
