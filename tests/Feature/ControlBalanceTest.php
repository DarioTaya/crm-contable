<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ControlBalance;
use Database\Seeders\CronogramaSunatSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ControlBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_control_balances_index_filters_strictly_mype_and_general_and_preserves_correlative_numbering(): void
    {
        // 1. RUS (debe ser excluido, correlativo N° 1)
        $rus = Cliente::create([
            'razon_social' => 'BODEGA RUS EIRL',
            'ruc' => '10111222334',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        // 2. MYPE (debe ser incluido, correlativo N° 2)
        $mype = Cliente::create([
            'razon_social' => 'DISTRIBUIDORA MYPE SAC',
            'ruc' => '20111222335',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        // 3. RER (debe ser excluido, correlativo N° 3)
        $rer = Cliente::create([
            'razon_social' => 'COMERCIAL RER SRL',
            'ruc' => '20111222336',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);

        // 4. General (debe ser incluido, correlativo N° 4)
        $general = Cliente::create([
            'razon_social' => 'CORPORACION GENERAL SAC',
            'ruc' => '20111222337',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $this->assertEquals(1, $rus->numero);
        $this->assertEquals(2, $mype->numero);
        $this->assertEquals(3, $rer->numero);
        $this->assertEquals(4, $general->numero);

        $response = $this->get(route('balances.index'));

        $response->assertStatus(200);
        $response->assertViewIs('balances.index');

        // MYPE y General deben aparecer
        $response->assertSee('DISTRIBUIDORA MYPE SAC');
        $response->assertSee('CORPORACION GENERAL SAC');

        // RUS y RER deben ser excluidos
        $response->assertDontSee('BODEGA RUS EIRL');
        $response->assertDontSee('COMERCIAL RER SRL');

        // El conteo solo incluye a 2 empresas
        $response->assertViewHas('totalClientes', 2);

        // Los correlativos originales deben saltar (2 y 4)
        $matriz = $response->viewData('matrizClientes');
        $this->assertCount(2, $matriz);
        $this->assertEquals(2, $matriz[0]['numero']);
        $this->assertEquals($mype->id, $matriz[0]['cliente']->id);
        $this->assertEquals(4, $matriz[1]['numero']);
        $this->assertEquals($general->id, $matriz[1]['cliente']->id);
    }

    public function test_control_balances_update_saves_month_status_via_ajax(): void
    {
        $mype = Cliente::create([
            'razon_social' => 'CLIENTE BALANCE AJAX SAC',
            'ruc' => '20444555667',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->postJson(route('balances.update'), [
            'cliente_id' => $mype->id,
            'anio' => 2026,
            'mes' => 5,
            'estado' => 'Declarado',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Balance actualizado exitosamente.',
        ]);
        $response->assertJsonPath('kpis.balancesDeclarados', 1);

        $balance = ControlBalance::where('cliente_id', $mype->id)->where('anio', 2026)->first();
        $this->assertNotNull($balance);
        $this->assertEquals('Declarado', $balance->getEstadoMes(5));
    }

    public function test_balance_control_opens_the_month_due_under_the_three_month_ple_rule(): void
    {
        $this->seed(CronogramaSunatSeeder::class);

        $this->travelTo(Carbon::parse('2026-09-23 10:00:00', 'America/Lima'), function () {
            $response = $this->get(route('balances.index', ['anio' => 2026]));

            $response->assertOk();
            $response->assertSee('Periodo en apertura');
            $response->assertSee('Junio de 2026');
            $response->assertSee('30/09/2026');
            $response->assertSee('Abierto');
            $response->assertSee('3 meses');
        });
    }

    public function test_control_balances_update_saves_dj_anual_via_ajax(): void
    {
        $general = Cliente::create([
            'razon_social' => 'CLIENTE DJ ANUAL SAC',
            'ruc' => '20888999112',
            'regimen_tributario' => 'General',
            'estado' => true,
        ]);

        $response = $this->postJson(route('balances.update'), [
            'cliente_id' => $general->id,
            'anio' => 2026,
            'dj_anual' => 'Declarado',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Balance actualizado exitosamente.',
        ]);
        $response->assertJsonPath('kpis.djAnualDeclaradas', 1);

        $balance = ControlBalance::where('cliente_id', $general->id)->where('anio', 2026)->first();
        $this->assertNotNull($balance);
        $this->assertEquals('Declarado', $balance->dj_anual);
    }

    public function test_control_balances_update_saves_note_via_ajax(): void
    {
        $mype = Cliente::create([
            'razon_social' => 'CLIENTE NOTA BALANCE SAC',
            'ruc' => '20333444555',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $response = $this->postJson(route('balances.update'), [
            'cliente_id' => $mype->id,
            'anio' => 2026,
            'mes' => 8,
            'nota' => 'Depreciación y cierre anual pendiente',
        ]);

        $response->assertStatus(200);

        $balance = ControlBalance::where('cliente_id', $mype->id)->where('anio', 2026)->first();
        $this->assertNotNull($balance);
        $this->assertEquals('Depreciación y cierre anual pendiente', $balance->getNotaMes(8));
    }

    public function test_expediente_digital_renders_balances_only_for_mype_and_general(): void
    {
        $mype = Cliente::create([
            'razon_social' => 'EXPEDIENTE MYPE SAC',
            'ruc' => '20123456781',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $rus = Cliente::create([
            'razon_social' => 'EXPEDIENTE RUS EIRL',
            'ruc' => '10123456782',
            'regimen_tributario' => 'RUS',
            'estado' => true,
        ]);

        $rer = Cliente::create([
            'razon_social' => 'EXPEDIENTE RER SRL',
            'ruc' => '20123456783',
            'regimen_tributario' => 'RER',
            'estado' => true,
        ]);

        // MYPE debe mostrar la sección de Control de Balances
        $responseMype = $this->get(route('clientes.operaciones', $mype));
        $responseMype->assertStatus(200);
        $responseMype->assertSee('Control de Balances y Estados Financieros');
        $responseMype->assertSee('DJ ANUAL '.(now()->year - 1));

        // RUS NO debe mostrar la sección de Control de Balances
        $responseRus = $this->get(route('clientes.operaciones', $rus));
        $responseRus->assertStatus(200);
        $responseRus->assertDontSee('Control de Balances y Estados Financieros');

        // RER NO debe mostrar la sección de Control de Balances
        $responseRer = $this->get(route('clientes.operaciones', $rer));
        $responseRer->assertStatus(200);
        $responseRer->assertDontSee('Control de Balances y Estados Financieros');
    }

    public function test_ui_text_refinements_match_requirements(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'TEST TEXTOS REFINADOS SAC',
            'ruc' => '20999888111',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $textoEsperado = 'Matriz anual de control tributario (DJ: SIRE, PDT 621 y PDT 601) y cobro de honorarios (RH: Recibo por Honorarios) con notas flotantes.';

        // 1. En control/index.blade.php
        $resControl = $this->get(route('control.index'));
        $resControl->assertStatus(200);
        $resControl->assertSee($textoEsperado, false);

        // 2. En clientes/show.blade.php
        $resShow = $this->get(route('clientes.operaciones', $cliente));
        $resShow->assertStatus(200);
        $resShow->assertSee($textoEsperado, false);
    }
}
