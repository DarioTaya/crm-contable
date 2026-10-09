<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Services\ControlImpuestos\ConciliadorDeclaracionSunat;
use App\Services\ControlImpuestos\DeclaracionLeida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConciliacionRmtTasaTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private Cliente $clienteRmt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Test RMT',
            'ruc' => '20999888777',
        ]);

        $this->clienteRmt = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'IMPORTACIONES VIRGEN DE CHAPI SRL',
            'ruc' => '20498311700',
            'regimen_tributario' => 'RMT',
            'estado' => 'activo',
        ]);
    }

    public function test_rmt_superando_300_uit_aplica_1_5_sin_alertas_falsas_de_rer(): void
    {
        // Simulamos que en Ene, Feb y Mar 2026 acumuló S/ 1,500,000 en ventas
        foreach (['01-2026' => 600000, '02-2026' => 500000, '03-2026' => 500000] as $periodo => $ventas) {
            $op = Operacion::create([
                'estudio_id' => $this->estudio->id,
                'cliente_id' => $this->clienteRmt->id,
                'periodo' => $periodo,
                'estado' => 'Declarado',
            ]);
            LiquidacionPeriodo::create([
                'estudio_id' => $this->estudio->id,
                'cliente_id' => $this->clienteRmt->id,
                'operacion_id' => $op->id,
                'periodo' => $periodo,
                'ventas_base' => $ventas,
                'renta_tasa' => 1.0,
                'renta_calculada' => $ventas * 0.01,
            ]);
        }

        // En Abril 2026 vende 400,000 (acumulado 2,000,000 > 300 UIT de aprox S/ 1,650,000)
        // La DJ declara tasa 1.5% para RMT en casilla 315
        $declaracion = new DeclaracionLeida;
        $declaracion->formulario = '621';
        $declaracion->ruc = '20498311700';
        $declaracion->periodo = '04-2026';
        $declaracion->casillas = [
            '100' => 400000,
            '101' => 72000,
            '107' => 300000,
            '108' => 54000,
            '140' => 18000,
            '189' => 18000,
            '301' => 400000,
            '315' => 1.5,
            '312' => 6000,
            '307' => 6000,
        ];
        $declaracion->tributos = [
            '1011' => 18000,
            '3121' => 6000,
        ];
        $declaracion->tieneDetalle = true;

        $conciliador = app(ConciliadorDeclaracionSunat::class);
        $resultado = $conciliador->conciliar(
            $declaracion,
            $this->clienteRmt,
            '04-2026',
            '621',
            [
                'igv_saldo_favor_anterior' => 0,
                'igv_retenciones_saldo_anterior' => 0,
                'igv_percepciones_saldo_anterior' => 0,
                'renta_saldo_favor_anterior' => 0,
                'renta_itan_saldo_anterior' => 0,
            ]
        );

        $this->assertFalse($resultado->bloqueado);

        // 1. No debe existir discrepancia de régimen (sigue siendo RMT)
        $this->assertNull($resultado->regimenDiscrepancia);

        // 2. El tributo de renta preservado debe ser 3121 (RMT), no 3111 (RER)
        $this->assertSame('3121', $resultado->preservar['renta_tributo_codigo'] ?? null);

        // 3. No debe existir advertencia de superar límite de RER de 525,000
        $codigosAlertas = collect($resultado->alertas)->pluck('codigo')->all();
        $this->assertNotContains('limite_rer_excedido', $codigosAlertas);
        $this->assertNotContains('regimen_distinto', $codigosAlertas);
        $this->assertNotContains('tasa_distinta', $codigosAlertas);

        // 4. Debe contener la alerta informativa sobre superación de 300 UIT en RMT
        $this->assertContains('rmt_300_uit_superado', $codigosAlertas);
    }

    public function test_rer_superando_525000_advierte_limite_excedido(): void
    {
        $clienteRer = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'BODEGA PRUEBA RER EIRL',
            'ruc' => '20777888999',
            'regimen_tributario' => 'RER',
            'estado' => 'activo',
        ]);

        $op = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $clienteRer->id,
            'periodo' => '05-2026',
            'estado' => 'Declarado',
        ]);

        // La DJ supera los 525,000 acumulados
        $declaracion = new DeclaracionLeida;
        $declaracion->formulario = '621';
        $declaracion->ruc = '20777888999';
        $declaracion->periodo = '05-2026';
        $declaracion->casillas = [
            '100' => 550000,
            '101' => 99000,
            '107' => 100000,
            '108' => 18000,
            '140' => 81000,
            '189' => 81000,
            '301' => 550000,
            '315' => 1.5,
            '312' => 8250,
            '307' => 8250,
        ];
        $declaracion->tributos = [
            '1011' => 81000,
            '3111' => 8250,
        ];
        $declaracion->tieneDetalle = true;

        $conciliador = app(ConciliadorDeclaracionSunat::class);
        $resultado = $conciliador->conciliar(
            $declaracion,
            $clienteRer,
            '05-2026',
            '621',
            [
                'igv_saldo_favor_anterior' => 0,
                'igv_retenciones_saldo_anterior' => 0,
                'igv_percepciones_saldo_anterior' => 0,
                'renta_saldo_favor_anterior' => 0,
                'renta_itan_saldo_anterior' => 0,
            ]
        );

        $codigosAlertas = collect($resultado->alertas)->pluck('codigo')->all();
        $this->assertContains('limite_rer_excedido', $codigosAlertas);
    }
}
