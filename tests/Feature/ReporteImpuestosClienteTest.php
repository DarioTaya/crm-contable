<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\Operacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReporteImpuestosClienteTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Reporte Impuestos',
            'ruc' => '20999000111',
        ]);

        $this->user = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->actingAs($this->user);
        $this->travelTo(Carbon::parse('2026-09-26 15:00:00', 'America/Lima'));
    }

    public function test_el_expediente_muestra_igv_justo_pagado_a_tiempo_con_detracciones(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO IGV SAC', '20111000111');

        $this->guardarTributo($cliente, '01-2026', '1011', 1500, '2026-05-18', true);
        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 1500,
            'fecha_pago' => '2026-05-18',
            'medio_pago' => 'detracciones',
            'observacion' => 'OP 8841',
        ])->assertOk();

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('Impuestos del ejercicio 2026');
        $response->assertSee('ENE-26');
        $response->assertSee('1,500.00');
        $response->assertSee('18/05/2026');
        $response->assertSee('1 pago');
        $response->assertSee('pagosAbiertos: null', false);
        $response->assertSee('Detracciones');
        $response->assertSee('OP 8841');
        $response->assertSee('IGV Justo');
        $response->assertSee('Pagado a tiempo');
        $response->assertSee('canceló 18/05/2026');
        $this->assertDatabaseHas('abonos_deudas', [
            'deuda_tributaria_id' => $deuda->id,
            'medio_pago' => 'detracciones',
        ]);

        $this->flushSession();
        $this->get(route('control_deudas.index'))->assertDontSee('LIBRO IGV SAC');
    }

    public function test_dos_pagos_fuera_de_fecha_se_leen_en_el_periodo(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO DOS PAGOS SAC', '20111000112');

        $this->guardarTributo($cliente, '01-2026', '1011', 1500, '2026-02-18');
        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 800,
            'fecha_pago' => '2026-02-10',
            'medio_pago' => 'caja',
        ])->assertOk();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 700,
            'fecha_pago' => '2026-03-02',
            'medio_pago' => 'bancos',
        ])->assertOk();

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('2 pagos');
        $response->assertSee('Caja');
        $response->assertSee('Bancos');
        $response->assertSee('Pagado fuera de fecha');
        $response->assertSee('canceló 02/03/2026');
        $response->assertSee('10/02/2026');
    }

    public function test_el_saldo_del_expediente_coincide_con_control_de_deudas(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO SALDO SAC', '20111000113');

        $this->guardarTributo($cliente, '01-2026', '1011', 1000, '2026-02-18');
        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 400,
            'fecha_pago' => '2026-02-10',
            'medio_pago' => 'detracciones',
        ])->assertOk();

        $expediente = $this->get(route('clientes.operaciones', $cliente));
        $expediente->assertSee('Debe · parcial vencido');
        $expediente->assertSee('600.00');
        $expediente->assertSee('LIBRO SALDO SAC');

        $deudas = $this->get(route('control_deudas.index'));
        $deudas->assertSee('LIBRO SALDO SAC');
        $deudas->assertSee('600.00');
        $deudas->assertSee('1,000.00');
    }

    public function test_la_renta_muestra_compensacion_itan_y_plame_queda_aparte(): void
    {
        $cliente = $this->cliente('General', 'LIBRO RENTA SAC', '20111000114');

        $this->guardarTributo($cliente, '02-2026', '3031', 250, '2026-03-18');
        $renta = DeudaTributaria::query()->where('tributo_codigo', '3031')->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $renta->operacion_id,
            'deuda_tributaria_id' => $renta->id,
            'monto' => 250,
            'fecha_pago' => '2026-03-18',
            'medio_pago' => 'compensacion_itan',
        ])->assertOk();

        $this->guardarTributo($cliente, '02-2026', '5210', 80, '2026-03-18');
        $essalud = DeudaTributaria::query()->where('tributo_codigo', '5210')->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $essalud->operacion_id,
            'deuda_tributaria_id' => $essalud->id,
            'monto' => 80,
            'fecha_pago' => '2026-03-10',
            'medio_pago' => 'bancos',
        ])->assertOk();

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('Renta General');
        $response->assertSee('Compensación ITAN');
        $response->assertSee('Pagado a tiempo');
        $response->assertSee('EsSalud');
        $response->assertSee('80.00');
        $response->assertSee('PDT 621');
        $response->assertSee('PLAME');

        $reporte = $response->viewData('reporteImpuestos');
        $pdt = collect($reporte['bloques'])->firstWhere('id', 'pdt_621');
        $plame = collect($reporte['bloques'])->firstWhere('id', 'pdt_601');
        $this->assertSame(['1011', '3031', '3081'], array_column($pdt['tributos'], 'codigo'));
        $this->assertSame(['5210', '5310', '6098', '3052', '3042'], array_column($plame['tributos'], 'codigo'));
        $html = $response->getContent();
        $fila = function (string $codigo) use ($html): string {
            $inicio = strpos($html, 'data-tributo="'.$codigo.'"');
            $this->assertNotFalse($inicio, 'Falta la fila del tributo '.$codigo);

            return substr($html, $inicio, 80);
        };
        $this->assertStringContainsString('data-sin-monto', $fila('1011'));
        $this->assertStringContainsString('data-sin-monto', $fila('5310'));
        $this->assertStringNotContainsString('data-sin-monto', $fila('3031'));
        $this->assertStringNotContainsString('data-sin-monto', $fila('5210'));
        $response->assertSee('2 sin monto', false);
        $response->assertSee('4 sin monto', false);
        $response->assertDontSee('data-malla-oculta', false);
    }

    public function test_sin_importes_la_malla_muestra_todos_los_tributos_en_cero(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO EN CERO SAC', '20111000121');

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('tributosCompletos: {}', false);
        $response->assertSee('2 sin monto', false);
        $response->assertSee('5 sin monto', false);
        $response->assertSee('data-malla-oculta', false);
        $response->assertSee('A pagar · según PDT');
        $response->assertSee('Sin registrar');
        $response->assertSee('Sin pagos');
        $response->assertSee('IGV 18%');
        $response->assertSee('Renta RMT 1%');
        $response->assertSee('EsSalud');
        $response->assertSee('ONP');
        $response->assertSee('Renta 4ta');
        $response->assertDontSee('Este ejercicio no tiene tributos de PLAME');

        $reporte = $response->viewData('reporteImpuestos');
        $pdt = collect($reporte['bloques'])->firstWhere('id', 'pdt_621');
        $plame = collect($reporte['bloques'])->firstWhere('id', 'pdt_601');
        $this->assertCount(2, $pdt['tributos']);
        $this->assertCount(5, $plame['tributos']);
        $this->assertSame('S/ 0.00', $pdt['tributos'][0]['periodos'][0]['determinado']);
        $this->assertSame('S/ 0.00', $pdt['tributos'][0]['periodos'][0]['saldo']);
        $this->assertSame('S/ 0.00', $plame['tributos'][0]['periodos'][0]['determinado']);
        $this->assertSame('S/ 0.00', $reporte['resumen']['determinado_621']);
        $this->assertSame('S/ 0.00', $reporte['resumen']['determinado_601']);
    }

    public function test_nrus_no_arma_malla_de_pdt_621_sin_importes(): void
    {
        $cliente = $this->cliente('RUS', 'BODEGA SIN PDT SAC', '10111000115');

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('NRUS no presenta PDT 621');
        $response->assertDontSee('Renta RMT');
        $response->assertDontSee('IGV 18%');
    }

    public function test_un_importe_anterior_sin_deuda_tributaria_sigue_en_el_libro(): void
    {
        $cliente = $this->cliente('RER', 'LIBRO LEGADO SAC', '20111000116');
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $cliente->id,
            'periodo' => '03-2026',
            'tiene_deuda' => true,
            'tributo_codigo' => '3111',
            'monto_deuda_inicial' => 180,
            'vencimiento_deuda' => '2026-04-20',
        ]);
        AbonoDeuda::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'monto' => 180,
            'fecha_pago' => '2026-04-20',
        ]);

        $response = $this->get(route('clientes.operaciones', $cliente));

        $response->assertSee('Renta RER 1.5%');
        $response->assertSee('180.00');
        $response->assertSee('Pagado a tiempo');
        $response->assertSee('Sin clasificar');
        $response->assertSee('MAR-26');
    }

    public function test_el_ejercicio_consultado_no_mezcla_periodos_de_otro_anio(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO ANIO SAC', '20111000117');
        $this->guardarTributo($cliente, '12-2025', '1011', 321.45, '2026-01-15');

        $esteAnio = $this->get(route('clientes.operaciones', ['cliente' => $cliente, 'anio' => 2026]));
        $esteAnio->assertDontSee('321.45');
        $esteAnio->assertSee('ENE-26');

        $anterior = $this->get(route('clientes.operaciones', ['cliente' => $cliente, 'anio' => 2025]));
        $anterior->assertSee('321.45');
        $anterior->assertSee('DIC-25');
        $anterior->assertDontSee('ENE-26');
    }

    public function test_meses_posteriores_a_la_baja_no_figuran_como_sin_determinar(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO BAJA SAC', '20111000118');
        $cliente->update([
            'estado' => 'retirado',
            'mes_baja' => '2026-03',
        ]);
        $this->guardarTributo($cliente, '01-2026', '1011', 90, '2026-02-18');

        $response = $this->get(route('clientes.operaciones', $cliente));
        $igv = collect($response->viewData('reporteImpuestos')['bloques'])
            ->firstWhere('id', 'pdt_621')['tributos'][0];

        $this->assertSame('90.00', str_replace('S/ ', '', $igv['periodos'][0]['determinado']));
        $this->assertSame('baja', $igv['periodos'][2]['situacion']);
        $this->assertSame('Baja', $igv['periodos'][2]['situacion_etiqueta']);
        $response->assertSee('90.00');
        $response->assertSee('Baja');
    }

    public function test_medio_de_pago_invalido_responde_422(): void
    {
        $cliente = $this->cliente('MYPE', 'LIBRO MEDIO SAC', '20111000119');
        $this->guardarTributo($cliente, '01-2026', '1011', 100, '2026-02-18');
        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 100,
            'fecha_pago' => '2026-02-18',
            'medio_pago' => 'efectivo',
        ])->assertUnprocessable()
            ->assertJsonPath('errors.medio_pago.0', 'Seleccione un medio de pago válido.');
    }

    public function test_control_de_impuestos_pide_el_medio_al_registrar_el_pago(): void
    {
        $this->cliente('MYPE', 'LIBRO FORMULARIO SAC', '20111000120');

        $this->get(route('control.index'))->assertSee('Medio de pago');
    }

    public function test_empresa_que_migro_de_rer_a_mype_muestra_igv_rer_y_rmt_dinamicamente(): void
    {
        $cliente = $this->cliente('MYPE', 'TRANSICION RER A MYPE SAC', '20111000130');

        // Periodo Enero 2026: tributó bajo RER (3111)
        $this->guardarTributo($cliente, '01-2026', '3111', 150, '2026-02-18');
        // Periodo Mayo 2026: ascendió a RMT (3121)
        $this->guardarTributo($cliente, '05-2026', '3121', 280, '2026-06-18');

        $response = $this->get(route('clientes.operaciones', $cliente));
        $reporte = $response->viewData('reporteImpuestos');
        $pdt = collect($reporte['bloques'])->firstWhere('id', 'pdt_621');

        $codigos = array_column($pdt['tributos'], 'codigo');
        // Debe figurar IGV (1011), RER histórico (3111) y RMT actual (3121)
        $this->assertContains('1011', $codigos);
        $this->assertContains('3111', $codigos);
        $this->assertContains('3121', $codigos);
        $this->assertNotContains('3031', $codigos); // No debe figurar Régimen General
        $this->assertNotContains('3081', $codigos);
    }

    public function test_vista_dividida_en_dos_muestra_tablas_paralelas_con_determinacion_vs_pagos(): void
    {
        $cliente = $this->cliente('MYPE', 'EMPRESA TABLAS PARALELAS SAC', '20111000140');

        // Periodo Enero 2026: IGV S/ 1000 pagado S/ 600 (Debe S/ 400)
        $this->guardarTributo($cliente, '01-2026', '1011', 1000, '2026-02-18');
        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deuda->operacion_id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 600,
            'fecha_pago' => '2026-02-15',
            'medio_pago' => 'bancos',
        ])->assertOk();

        $response = $this->get(route('clientes.operaciones', $cliente));

        // Cabeceras de los 2 bloques paralelos en la tabla
        $response->assertSee('1. Determinación según DJ · ¿Cuándo y cuánto se tenía que pagar?');
        $response->assertSee('2. Cumplimiento y Pagos · ¿Cuándo y cuánto se pagó según DJ?');

        // Columnas
        $response->assertSee('A Pagar (DJ)');
        $response->assertSee('Cuándo se Pagó');
        $response->assertSee('Monto Pagado');
        $response->assertSee('Saldo (Debe)');

        // Datos del periodo
        $response->assertSee('ENE-26');
        $response->assertSee('1,000.00');
        $response->assertSee('600.00');
        $response->assertSee('Debe 400.00');

        // Modal y scripts
        $response->assertSee('modalDetalle: {', false);
        $response->assertSee('abrirModal(', false);
    }

    public function test_transicion_de_rer_a_rmt_muestra_banner_contable_y_badges_por_mes(): void
    {
        $cliente = $this->cliente('MYPE', 'CLIENTE ASCENSO RER RMT SAC', '20111000150');

        // Enero a Abril: tributó bajo RER (3111)
        $this->guardarTributo($cliente, '01-2026', '3111', 120, '2026-02-18');
        $this->guardarTributo($cliente, '02-2026', '3111', 130, '2026-03-18');

        // Mayo: superó los 525,000 y ascendió a RMT (3121)
        $this->guardarTributo($cliente, '05-2026', '3121', 450, '2026-06-18');

        $response = $this->get(route('clientes.operaciones', $cliente));

        // Banner contable de transición
        $response->assertSee('Transición de Régimen Tributario');
        $response->assertSee('Corte en Mayo 2026 (05-2026)');
        $response->assertSee('Art. 121 LIR');

        $reporte = $response->viewData('reporteImpuestos');
        $this->assertTrue($reporte['analisis_regimen']['hubo_transicion']);
        $this->assertSame('05-2026', $reporte['analisis_regimen']['periodo_transicion']);
        $this->assertSame('Mayo', $reporte['analisis_regimen']['mes_transicion_nombre']);

        // Verificación de los meses en filas_periodos
        $pdt = collect($reporte['bloques'])->firstWhere('id', 'pdt_621');
        $filas = $pdt['filas_periodos'];

        // Mes 1 (Enero): debe tener sigla RER
        $this->assertSame('RER', $filas[0]['regimen']['sigla']);
        $this->assertSame('RER 1.5%', $filas[0]['regimen']['nombre']);
        $this->assertFalse($filas[0]['regimen']['es_transicion']);

        // Mes 5 (Mayo): debe tener sigla RMT y es_transicion = true
        $this->assertSame('RMT', $filas[4]['regimen']['sigla']);
        $this->assertSame('RMT 1%', $filas[4]['regimen']['nombre']);
        $this->assertTrue($filas[4]['regimen']['es_transicion']);
    }

    public function test_pdt_621_muestra_columnas_independientes_igv_y_renta_con_montos_reales_y_diferencias(): void
    {
        $cliente = $this->cliente('MYPE', 'CONTABILIDAD EXACTA SAC', '20111000160');

        // Periodo 01-2026: IGV 7,764 y Renta 827 (ambos cancelados)
        $this->guardarTributo($cliente, '01-2026', '1011', 7764, '2026-02-18');
        $deudaIgv1 = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deudaIgv1->operacion_id,
            'deuda_tributaria_id' => $deudaIgv1->id,
            'monto' => 7764,
            'fecha_pago' => '2026-02-16',
            'medio_pago' => 'bancos',
        ])->assertOk();

        $this->guardarTributo($cliente, '01-2026', '3111', 827, '2026-02-18');
        $deudaRta1 = DeudaTributaria::query()->where('tributo_codigo', '3111')->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deudaRta1->operacion_id,
            'deuda_tributaria_id' => $deudaRta1->id,
            'monto' => 827,
            'fecha_pago' => '2026-02-16',
            'medio_pago' => 'bancos',
        ])->assertOk();

        // Periodo 06-2026: IGV 10,375 (pagado 6,747, debe 3,628) y Renta 1,223 (cancelado)
        $this->guardarTributo($cliente, '06-2026', '1011', 10375, '2026-07-18');
        $deudaIgv6 = DeudaTributaria::query()->where('tributo_codigo', '1011')->where('monto_deuda_inicial', 10375)->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deudaIgv6->operacion_id,
            'deuda_tributaria_id' => $deudaIgv6->id,
            'monto' => 6747,
            'fecha_pago' => '2026-07-15',
            'medio_pago' => 'detracciones',
        ])->assertOk();

        $this->guardarTributo($cliente, '06-2026', '3111', 1223, '2026-07-18');
        $deudaRta6 = DeudaTributaria::query()->where('tributo_codigo', '3111')->where('monto_deuda_inicial', 1223)->firstOrFail();
        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $deudaRta6->operacion_id,
            'deuda_tributaria_id' => $deudaRta6->id,
            'monto' => 1223,
            'fecha_pago' => '2026-07-15',
            'medio_pago' => 'bancos',
        ])->assertOk();

        $response = $this->get(route('clientes.operaciones', $cliente));

        // Cabeceras con los códigos oficiales SUNAT
        $response->assertSee('4011 IGV (18%)');
        $response->assertSee('4017 Renta');
        $response->assertSee('4011 IGV Pag.');
        $response->assertSee('4017 Rta Pag.');

        // Importes de Enero 2026
        $response->assertSee('7,764.00');
        $response->assertSee('827.00');
        $response->assertSee('8,591.00');

        // Importes de Junio 2026
        $response->assertSee('10,375.00');
        $response->assertSee('1,223.00');
        $response->assertSee('6,747.00');
        $response->assertSee('Debe 3,628.00');

        // Totales y fila contable de diferencias
        $response->assertSee('DIFERENCIAS / SALDOS PENDIENTES');
        $response->assertSee('3,628.00');

        $reporte = $response->viewData('reporteImpuestos');
        $pdt = collect($reporte['bloques'])->firstWhere('id', 'pdt_621');
        $this->assertEquals(1813900, $pdt['totales']['totales_columnas']['igv']['determinado_centavos']);
        $this->assertEquals(205000, $pdt['totales']['totales_columnas']['renta']['determinado_centavos']);
        $this->assertEquals(362800, $pdt['totales']['diferencias']['igv_centavos']);
        $this->assertEquals(0, $pdt['totales']['diferencias']['renta_centavos']);
    }

    private function cliente(string $regimen, string $razonSocial, string $ruc): Cliente
    {
        return Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => $razonSocial,
            'ruc' => $ruc,
            'regimen_tributario' => $regimen,
            'estado' => 'activo',
        ]);
    }

    private function guardarTributo(Cliente $cliente, string $periodo, string $codigo, float $monto, string $vencimiento, bool $igvJusto = false): void
    {
        $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => $periodo,
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => $codigo,
            'es_igv_justo' => $igvJusto,
            'monto_deuda_inicial' => $monto,
            'vencimiento_deuda' => $vencimiento,
        ])->assertOk();
    }
}
