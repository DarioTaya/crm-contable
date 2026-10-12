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
            ->assertSee('/clientes/'.$cliente->id.'/cuenta-detracciones', false)
            ->assertSee('/clientes/'.$cliente->id.'/detracciones-retenciones', false)
            ->assertDontSee('/clientes/'.$cliente->id.'/operaciones', false)
            ->assertSee('/clientes/'.$cliente->id.'/sire', false);

        $this->get(route('clientes.notificaciones', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="notificaciones"', false);

        $this->get(route('clientes.operaciones', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="operaciones"', false);

        $this->get(route('clientes.detracciones', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="cuenta-detracciones"', false);

        $this->get(route('clientes.creditos', $cliente))
            ->assertOk()
            ->assertSee('data-seccion="creditos"', false);

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
    #[TestWith(['creditos', 'clientes.creditos'])]
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

    public function test_el_menu_de_sunat_queda_dentro_del_expediente_en_datos_y_accesos(): void
    {
        $cliente = $this->cliente();
        $cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'ClaveSol123',
        ]);

        $html = $this->get(route('clientes.show', $cliente))->assertOk()->getContent();
        $expediente = $this->htmlDelExpediente($html);

        $this->assertStringContainsString('Ingresar a SUNAT (1 Clic)', $expediente);
        $this->assertStringContainsString('abrirMenuSunat(', $expediente);
        $this->assertStringContainsString('Accesos SUNAT', $expediente);
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

    private function htmlDelExpediente(string $html): string
    {
        $marca = strpos($html, 'x-data="expedienteCliente');
        $this->assertNotFalse($marca);
        $inicio = strrpos(substr($html, 0, $marca), '<div');
        $this->assertNotFalse($inicio);

        $resto = substr($html, $inicio);
        $profundidad = 0;
        $cursor = 0;

        while (preg_match('/<div\b|<\/div>/', $resto, $coincidencia, PREG_OFFSET_CAPTURE, $cursor)) {
            $etiqueta = $coincidencia[0][0];
            $posicion = $coincidencia[0][1];
            $profundidad += str_starts_with($etiqueta, '</') ? -1 : 1;
            $cursor = $posicion + strlen($etiqueta);

            if ($profundidad === 0) {
                return substr($resto, 0, $cursor);
            }
        }

        $this->fail('El expediente no cierra su contenedor.');
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
