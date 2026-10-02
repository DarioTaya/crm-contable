<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CargaMasivaControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_invitado_recibe_401_al_revisar_la_carga_masiva(): void
    {
        $this->withHeader('X-Test-Guest', '1')
            ->postJson(route('control.carga_masiva.previsualizar'), [])
            ->assertUnauthorized();
    }

    public function test_una_carga_sin_datos_recibe_422(): void
    {
        $this->cartera();

        $this->postJson(route('control.carga_masiva.aplicar'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'modo' => 'Indique si va a cargar declaraciones o la plantilla.',
                'anio' => 'El ejercicio es obligatorio.',
                'politica' => 'Indique si debe conservar lo ya registrado o reemplazarlo.',
            ]);
    }

    public function test_un_tramo_invertido_recibe_422(): void
    {
        $this->cartera();

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(8, 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'mes_hasta' => 'El mes final no puede ser anterior al mes inicial.',
            ]);
    }

    public function test_un_libro_excel_recibe_422_pidiendo_csv(): void
    {
        $this->cartera();

        $this->post(route('control.carga_masiva.aplicar'), [
            'modo' => 'plantilla',
            'anio' => 2026,
            'politica' => 'conservar',
            'archivo' => UploadedFile::fake()->create('libro.xlsx', 12, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
        ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'archivo' => 'Guarde el libro como CSV UTF-8. En Excel: Archivo, Guardar como, CSV UTF-8. También se acepta punto y coma.',
            ]);
    }

    public function test_la_matriz_ofrece_la_carga_masiva_y_conserva_el_guardado_manual(): void
    {
        $this->cartera();

        $this->get(route('control.index', ['anio' => 2026]))
            ->assertOk()
            ->assertSee('Carga masiva')
            ->assertSee('Revisar antes de guardar')
            ->assertSee('Guardar tributo');
    }

    public function test_revisar_un_tramo_no_guarda_y_aplicarlo_declara_solo_esos_meses(): void
    {
        $this->travelTo('2026-09-28 10:00:00');
        ['cliente' => $cliente] = $this->cartera();

        Operacion::create([
            'cliente_id' => $cliente->id,
            'periodo' => '03-2026',
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
            'estado_pago_honorarios' => 'Pagado',
            'notas' => 'Nota que no debe perderse',
        ]);

        $this->postJson(route('control.carga_masiva.previsualizar'), $this->tramo(1, 8))
            ->assertOk()
            ->assertJsonPath('aplicada', false)
            ->assertJsonPath('puede_aplicar', true)
            ->assertJsonPath('estados', 8)
            ->assertJsonPath('tributos', 0);

        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseMissing('operaciones', ['periodo' => '01-2026']);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 8))
            ->assertOk()
            ->assertJsonPath('aplicada', true)
            ->assertJsonPath('resumen', 'Guardado: 8 declaraciones.');

        $this->assertDatabaseCount('operaciones', 8);
        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '08-2026',
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'tiene_deuda' => false,
        ]);
        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '03-2026',
            'estado_pago_honorarios' => 'Pagado',
            'notas' => 'Nota que no debe perderse',
        ]);
        $this->assertDatabaseMissing('operaciones', ['periodo' => '09-2026']);

        $marca = Operacion::query()->where('periodo', '01-2026')->firstOrFail()->updated_at->toDateTimeString();
        $this->travelTo('2026-09-28 10:05:00');

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 8))
            ->assertUnprocessable()
            ->assertJsonPath('puede_aplicar', false)
            ->assertJsonPath('estados', 0);

        $this->assertSame($marca, Operacion::query()->where('periodo', '01-2026')->firstOrFail()->updated_at->toDateTimeString());
        $this->assertDatabaseCount('operaciones', 8);
    }

    public function test_conservar_no_pisa_un_estado_distinto_de_pendiente_y_reemplazar_si(): void
    {
        ['cliente' => $cliente] = $this->cartera();
        Operacion::create([
            'cliente_id' => $cliente->id,
            'periodo' => '04-2026',
            'estado_compras_ventas' => 'Procesando',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(4, 4))
            ->assertOk()
            ->assertJsonPath('estados', 1);

        $this->assertDatabaseHas('operaciones', [
            'periodo' => '04-2026',
            'estado_compras_ventas' => 'Procesando',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
        ]);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(4, 4, ['politica' => 'reemplazar']))
            ->assertOk();

        $this->assertDatabaseHas('operaciones', [
            'periodo' => '04-2026',
            'estado_compras_ventas' => 'Declarado',
        ]);
    }

    public function test_no_declara_los_meses_en_los_que_el_cliente_ya_no_pertenece_al_estudio(): void
    {
        $this->cartera(estado: 'retirado', mesBaja: '2026-03');

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 8))
            ->assertOk()
            ->assertJsonPath('estados', 2);

        $this->assertDatabaseHas('operaciones', ['periodo' => '02-2026', 'estado_pdt621' => 'Declarado']);
        $this->assertDatabaseMissing('operaciones', ['periodo' => '03-2026']);
    }

    public function test_la_plantilla_carga_un_tributo_visible_en_deudas_y_en_el_libro_del_cliente(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000111', razon: 'LIBRO MASIVO SAC');

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000111;02-2026;\n20111000111;01-2026;1250.50\n")
            ->assertOk()
            ->assertJsonPath('tributos', 1)
            ->assertJsonPath('resumen', 'Guardado: 1 tributo.');

        $deuda = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();
        $this->assertSame('1250.50', $deuda->monto_deuda_inicial);
        $this->assertSame('01-2026', $deuda->operacion->periodo);
        $this->assertTrue($deuda->operacion->tiene_deuda);
        $this->assertDatabaseMissing('deuda_tributarias', ['tributo_codigo' => '1011', 'monto_deuda_inicial' => 0]);

        $this->get(route('control_deudas.index'))
            ->assertOk()
            ->assertSee('LIBRO MASIVO SAC')
            ->assertSee('1,250.50');

        $this->get(route('clientes.operaciones', $cliente))
            ->assertOk()
            ->assertSee('1,250.50');
    }

    public function test_la_plantilla_acepta_importe_peruano_y_estado_en_proceso(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000112');

        $this->aplicarPlantilla("ruc;periodo;sire;1011\n20111000112;02-2026;En proceso;1.250,50\n")
            ->assertOk()
            ->assertJsonPath('estados', 1)
            ->assertJsonPath('tributos', 1);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '02-2026',
            'estado_compras_ventas' => 'Procesando',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);
        $this->assertSame('1250.50', DeudaTributaria::query()->firstOrFail()->monto_deuda_inicial);
    }

    public function test_igv_justo_en_la_plantilla_fija_el_vencimiento_a_tres_meses(): void
    {
        $this->cartera(ruc: '20111000113');

        $this->aplicarPlantilla("ruc;periodo;1011;igv_justo\n20111000113;01-2026;80;SI\n")
            ->assertOk();

        $deuda = DeudaTributaria::query()->firstOrFail();
        $this->assertTrue($deuda->es_igv_justo);
        $this->assertSame('2026-04-30', $deuda->vencimiento_deuda?->format('Y-m-d'));
        $this->assertSame('2026-04-30', $deuda->operacion->vencimiento_deuda?->format('Y-m-d'));
    }

    public function test_una_celda_vacia_no_borra_el_tributo_ya_guardado(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000114');
        $this->guardarTributo($cliente, '01-2026', '1011', 80);

        $this->aplicarPlantilla("ruc;periodo;1011;5210\n20111000114;01-2026;;15\n")
            ->assertOk()
            ->assertJsonPath('tributos', 1);

        $this->assertSame('80.00', DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail()->monto_deuda_inicial);
        $this->assertSame('15.00', DeudaTributaria::query()->where('tributo_codigo', '5210')->firstOrFail()->monto_deuda_inicial);
    }

    public function test_conservar_no_pisa_un_tributo_guardado_y_reemplazar_actualiza_el_importe(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000115');
        $this->guardarTributo($cliente, '01-2026', '1011', 100, '2026-02-18');

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000115;01-2026;999\n")
            ->assertUnprocessable()
            ->assertJsonPath('tributos', 0);

        $this->assertSame('100.00', DeudaTributaria::query()->firstOrFail()->monto_deuda_inicial);

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000115;01-2026;250.50\n", 'reemplazar')
            ->assertOk();

        $deuda = DeudaTributaria::query()->firstOrFail();
        $this->assertSame('250.50', $deuda->monto_deuda_inicial);
        $this->assertSame('2026-02-18', $deuda->vencimiento_deuda?->format('Y-m-d'));
        $this->assertSame('250.50', $deuda->operacion->fresh()->monto_deuda_inicial);
    }

    public function test_un_importe_menor_que_los_pagos_rechaza_toda_la_carga_con_422(): void
    {
        ['cliente' => $cliente, 'estudio' => $estudio] = $this->cartera(ruc: '20111000116');
        $operacion = Operacion::create([
            'cliente_id' => $cliente->id,
            'periodo' => '02-2026',
            'tiene_deuda' => true,
        ]);
        $deuda = DeudaTributaria::create([
            'estudio_id' => $estudio->id,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 100,
        ]);
        AbonoDeuda::create([
            'estudio_id' => $estudio->id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 80,
            'fecha_pago' => '2026-03-10',
        ]);

        $this->aplicarPlantilla("ruc;periodo;1011;5210\n20111000116;01-2026;;40\n20111000116;02-2026;10;\n", 'reemplazar')
            ->assertUnprocessable()
            ->assertJsonPath('aplicada', false);

        $this->assertDatabaseMissing('deuda_tributarias', ['tributo_codigo' => '5210']);
        $this->assertSame('100.00', $deuda->fresh()->monto_deuda_inicial);
    }

    public function test_un_periodo_repetido_en_la_plantilla_recibe_422_y_no_guarda_nada(): void
    {
        $this->cartera(ruc: '20111000117');

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000117;01-2026;10\n20111000117;01-2026;20\n")
            ->assertUnprocessable()
            ->assertSee('ya aparece', false);

        $this->assertDatabaseCount('deuda_tributarias', 0);
    }

    public function test_un_mes_de_baja_no_recibe_el_importe_de_la_plantilla(): void
    {
        $this->cartera(ruc: '20111000118', estado: 'retirado', mesBaja: '2026-03');

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000118;01-2026;40\n20111000118;03-2026;90\n")
            ->assertOk()
            ->assertJsonPath('tributos', 1);

        $this->assertDatabaseHas('operaciones', ['periodo' => '01-2026']);
        $this->assertDatabaseMissing('operaciones', ['periodo' => '03-2026']);
        $this->assertSame(40.0, (float) DeudaTributaria::query()->firstOrFail()->monto_deuda_inicial);
    }

    public function test_la_descarga_trae_los_meses_exigibles_y_no_los_de_otro_estudio(): void
    {
        $this->cartera(ruc: '20111000119', estado: 'retirado', mesBaja: '2026-03');
        $ajeno = Estudio::create(['nombre' => 'Estudio Ajeno', 'ruc' => '20999000999']);
        Cliente::create([
            'estudio_id' => $ajeno->id,
            'razon_social' => 'CLIENTE DE OTRO ESTUDIO SAC',
            'ruc' => '20999000199',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $respuesta = $this->get(route('control.carga_masiva.plantilla', ['anio' => 2026]));

        $respuesta->assertOk();
        $respuesta->assertHeader('content-disposition', 'attachment; filename="control-impuestos-2026.csv"');
        $contenido = $respuesta->getContent();
        $this->assertStringContainsString('SIRE (Pendiente, En proceso o Declarado; vacio no cambia)', $contenido);
        $this->assertStringContainsString('PDT 621 (Pendiente, En proceso o Declarado; vacio no cambia)', $contenido);
        $this->assertStringContainsString('PDT 601 PLAME (Pendiente, En proceso o Declarado; vacio no cambia)', $contenido);
        $this->assertStringContainsString('Honorarios RH (Debe o Pago; vacio no cambia)', $contenido);
        $this->assertStringContainsString('PAGOS', $contenido);
        $this->assertStringContainsString('Codigo del tributo (el mismo codigo de la fila del mes: 1011, 5210, AFP u otro)', $contenido);
        $this->assertStringContainsString('Fecha de pago (DD/MM/AAAA)', $contenido);
        $this->assertStringContainsString('Medio de pago (Detracciones, Caja, Bancos, Compensacion renta anual o Compensacion ITAN)', $contenido);
        $this->assertStringContainsString('Numero de operacion (opcional)', $contenido);
        $this->assertStringContainsString('1011 IGV 18% (importe a pagar PDT 621 IGV y Renta)', $contenido);
        $this->assertStringNotContainsString(';ple;', $contenido);
        $this->assertStringContainsString('20111000119', $contenido);
        $this->assertStringContainsString('02-2026', $contenido);
        $this->assertStringNotContainsString('03-2026', $contenido);
        $this->assertStringNotContainsString('20999000199', $contenido);
        $this->assertStringNotContainsString('CLIENTE DE OTRO ESTUDIO SAC', $contenido);
    }

    public function test_la_plantilla_de_una_empresa_no_incluye_al_resto_de_la_cartera(): void
    {
        ['estudio' => $estudio, 'cliente' => $elegido] = $this->cartera(ruc: '20111000131', razon: 'EL CHALACO MAJEÑO E.I.R.L.');
        Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'OTRA EMPRESA SAC',
            'ruc' => '20111000132',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $contenido = $this->get(route('control.carga_masiva.plantilla', [
            'anio' => 2026,
            'cliente_ids' => [$elegido->id],
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('20111000131', $contenido);
        $this->assertStringContainsString('EL CHALACO MAJEÑO E.I.R.L.', $contenido);
        $this->assertStringNotContainsString('20111000132', $contenido);
        $this->assertStringNotContainsString('OTRA EMPRESA SAC', $contenido);
    }

    public function test_una_plantilla_con_empresa_no_elegida_recibe_422_y_no_guarda_nada(): void
    {
        ['estudio' => $estudio, 'cliente' => $elegido] = $this->cartera(ruc: '20111000133', razon: 'EMPRESA ELEGIDA SAC');
        Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'EMPRESA FUERA DE LA SELECCION SAC',
            'ruc' => '20111000134',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $this->aplicarPlantilla("ruc;periodo;1011\n20111000133;01-2026;10\n20111000134;01-2026;20\n", 'conservar', [
            'alcance' => 'seleccion',
            'cliente_ids' => [$elegido->id],
        ])->assertUnprocessable()
            ->assertJsonPath('aplicada', false);

        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseCount('operaciones', 0);
    }

    public function test_pagado_en_la_plantilla_registra_el_pago_total_y_no_lo_duplica(): void
    {
        $this->cartera(ruc: '20111000135', razon: 'PAGO MASIVO SAC');

        $this->aplicarPlantilla("ruc;periodo;1011;pagado\n20111000135;01-2026;550;SI\n")
            ->assertOk()
            ->assertJsonPath('tributos', 1)
            ->assertJsonPath('pagos', 1)
            ->assertJsonPath('resumen', 'Guardado: 1 tributo y 1 pago.');

        $deuda = DeudaTributaria::query()->with('abonos')->firstOrFail();
        $this->assertSame('550.00', $deuda->monto_deuda_inicial);
        $this->assertSame(0.0, (float) $deuda->saldo_pendiente);
        $this->assertSame('550.00', $deuda->abonos->firstOrFail()->monto);
        $this->assertSame('2026-01-31', $deuda->abonos->firstOrFail()->fecha_pago?->format('Y-m-d'));
        $this->assertSame('Carga masiva', $deuda->abonos->firstOrFail()->observacion);

        $this->aplicarPlantilla("ruc;periodo;1011;pagado\n20111000135;01-2026;550;SI\n")
            ->assertUnprocessable()
            ->assertJsonPath('pagos', 0);

        $this->assertSame(1, AbonoDeuda::query()->count());
    }

    public function test_pagado_sin_importe_recibe_422_y_no_crea_un_pago(): void
    {
        $this->cartera(ruc: '20111000136');

        $this->aplicarPlantilla("ruc;periodo;pagado\n20111000136;01-2026;SI\n")
            ->assertUnprocessable()
            ->assertJsonPath('aplicada', false);

        $this->assertDatabaseCount('abonos_deudas', 0);
        $this->assertDatabaseCount('deuda_tributarias', 0);
    }

    public function test_una_plantilla_antigua_con_columna_ple_marca_el_pdt_601(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000137');

        $this->aplicarPlantilla("ruc;periodo;ple\n20111000137;01-2026;Declarado\n")
            ->assertOk();

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_ple' => 'Declarado',
            'estado_pdt621' => 'Pendiente',
            'estado_compras_ventas' => 'Pendiente',
        ]);
    }

    public function test_un_ruc_de_otro_estudio_rechaza_la_plantilla_con_422_y_no_guarda_nada(): void
    {
        $this->cartera(ruc: '20111000121', razon: 'CLIENTE PROPIO SAC');
        $ajeno = Estudio::create(['nombre' => 'Estudio Ajeno Plantilla', 'ruc' => '20999000888']);
        Cliente::create([
            'estudio_id' => $ajeno->id,
            'razon_social' => 'RAZON SECRETA AJENA',
            'ruc' => '20999000188',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $respuesta = $this->aplicarPlantilla("ruc;periodo;1011\n20111000121;01-2026;10\n20999000188;01-2026;10\n");

        $respuesta->assertUnprocessable()
            ->assertJsonPath('aplicada', false);
        $respuesta->assertSee('20999000188', false);
        $respuesta->assertDontSee('RAZON SECRETA AJENA');
        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseCount('operaciones', 0);
    }

    public function test_un_cliente_de_otro_estudio_elegido_por_id_recibe_422(): void
    {
        $this->cartera();
        $ajeno = Estudio::create(['nombre' => 'Estudio Ajeno Id', 'ruc' => '20999000777']);
        $clienteAjeno = Cliente::create([
            'estudio_id' => $ajeno->id,
            'razon_social' => 'RAZON SECRETA POR ID',
            'ruc' => '20999000177',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $respuesta = $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'alcance' => 'seleccion',
            'cliente_ids' => [$clienteAjeno->id],
        ]));

        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors([
                'cliente_ids.0' => 'Hay un cliente que no pertenece a su cartera.',
            ]);
        $respuesta->assertDontSee('RAZON SECRETA POR ID');
        $this->assertDatabaseCount('operaciones', 0);
    }

    public function test_un_asistente_al_marcar_todos_solo_actualiza_su_cartera(): void
    {
        $estudio = Estudio::create(['nombre' => 'Estudio Asistente', 'ruc' => '20999000666']);
        $asistente = User::factory()->asistente()->create(['estudio_id' => $estudio->id]);
        $asignado = Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'CARTERA ASIGNADA SAC',
            'ruc' => '20111000131',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
        $ajeno = Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'CARTERA NO ASIGNADA SAC',
            'ruc' => '20111000132',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);
        $asignado->asistentes()->attach($asistente->id);
        $this->actingAs($asistente);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1))
            ->assertOk()
            ->assertJsonPath('estados', 1);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $asignado->id,
            'periodo' => '01-2026',
            'estado_compras_ventas' => 'Declarado',
        ]);
        $this->assertDatabaseMissing('operaciones', ['cliente_id' => $ajeno->id]);
    }

    public function test_volver_a_pendiente_borra_tributos_y_pagos_y_en_proceso_los_conserva(): void
    {
        ['cliente' => $cliente, 'estudio' => $estudio] = $this->cartera(ruc: '20111000141', razon: 'RESET MASIVO SAC');
        $this->guardarTributo($cliente, '01-2026', '1011', 550, '2026-02-18');
        $operacion = Operacion::query()->firstOrFail();
        $operacion->update([
            'estado_compras_ventas' => 'Declarado',
            'estado_pdt621' => 'Declarado',
            'estado_ple' => 'Declarado',
            'estado_pago_honorarios' => 'Pagado',
            'notas' => 'Nota viva',
        ]);
        $deuda = DeudaTributaria::query()->firstOrFail();
        AbonoDeuda::create([
            'estudio_id' => $estudio->id,
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 550,
            'fecha_pago' => '2026-02-18',
        ]);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'estado' => 'Procesando',
            'politica' => 'reemplazar',
        ]))->assertOk()
            ->assertJsonPath('limpiezas', 0);

        $this->assertSame('550.00', DeudaTributaria::query()->firstOrFail()->monto_deuda_inicial);
        $this->assertSame(1, AbonoDeuda::query()->count());
        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_pdt621' => 'Procesando',
            'estado_pago_honorarios' => 'Pagado',
            'notas' => 'Nota viva',
        ]);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'estado' => 'Pendiente',
            'politica' => 'conservar',
        ]))->assertOk()
            ->assertJsonPath('limpiezas', 1)
            ->assertJsonPath('resumen', 'Guardado: 1 declaración. Se eliminan tributos y pagos de 1 mes.');

        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseCount('abonos_deudas', 0);
        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
            'estado_pago_honorarios' => 'Pagado',
            'notas' => 'Nota viva',
            'tiene_deuda' => false,
        ]);
    }

    public function test_la_carga_de_honorarios_marca_pago_sin_tocar_declaraciones_ni_tributos(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000142');
        $this->guardarTributo($cliente, '02-2026', '5210', 40);

        $this->postJson(route('control.carga_masiva.aplicar'), [
            'modo' => 'honorarios',
            'anio' => 2026,
            'politica' => 'conservar',
            'mes_desde' => 1,
            'mes_hasta' => 2,
            'estado' => 'Pagado',
            'alcance' => 'todos',
        ])->assertOk()
            ->assertJsonPath('honorarios', 2)
            ->assertJsonPath('limpiezas', 0)
            ->assertJsonPath('resumen', 'Guardado: 2 honorarios.');

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'estado_pago_honorarios' => 'Pagado',
            'estado_pdt621' => 'Pendiente',
        ]);
        $this->assertSame('40.00', DeudaTributaria::query()->where('tributo_codigo', '5210')->firstOrFail()->monto_deuda_inicial);

        $this->postJson(route('control.carga_masiva.aplicar'), [
            'modo' => 'honorarios',
            'anio' => 2026,
            'politica' => 'conservar',
            'mes_desde' => 1,
            'mes_hasta' => 1,
            'estado' => 'Pendiente',
            'alcance' => 'todos',
        ])->assertUnprocessable()
            ->assertJsonPath('honorarios', 0);

        $this->assertDatabaseHas('operaciones', [
            'periodo' => '01-2026',
            'estado_pago_honorarios' => 'Pagado',
        ]);
    }

    public function test_el_pago_de_la_plantilla_guarda_fecha_medio_y_numero_de_operacion(): void
    {
        $this->cartera(ruc: '20111000143');

        $this->aplicarPlantilla("ruc;periodo;1011;pagado;fecha de pago;medio de pago;numero de operacion\n20111000143;01-2026;550;SI;15/02/2026;Bancos;OP-100\n")
            ->assertOk()
            ->assertJsonPath('pagos', 1);

        $abono = AbonoDeuda::query()->firstOrFail();
        $this->assertSame('2026-02-15', $abono->fecha_pago?->format('Y-m-d'));
        $this->assertSame('bancos', $abono->medio_pago?->value);
        $this->assertSame('OP-100', $abono->observacion);
        $this->assertSame('550.00', $abono->monto);
    }

    public function test_una_sola_declaracion_marca_sire_pdt_621_y_pdt_601(): void
    {
        $this->cartera(ruc: '20111000160');

        $this->aplicarPlantilla("ruc;periodo;declaracion dj\n20111000160;01-2026;Declarado\n")
            ->assertOk()
            ->assertJsonPath('estados', 1);

        $operacion = Operacion::query()->where('periodo', '01-2026')->firstOrFail();
        $this->assertSame('Declarado', $operacion->estado_compras_ventas);
        $this->assertSame('Declarado', $operacion->estado_pdt621);
        $this->assertSame('Declarado', $operacion->estado_ple);
    }

    public function test_cada_tributo_acepta_varios_pagos_y_un_segundo_archivo_no_los_duplica(): void
    {
        $this->cartera(ruc: '20111000161');
        $csv = <<<'CSV'
ruc;periodo;1011;5210
20111000161;01-2026;550;90

PAGOS
ruc;periodo;codigo del tributo;monto del pago;fecha de pago;medio de pago;numero de operacion
20111000161;01-2026;1011;200;10/02/2026;Bancos;A1
20111000161;01-2026;1011;200;20/02/2026;Bancos;A2
20111000161;01-2026;1011;150;28/02/2026;Caja;A3
20111000161;01-2026;5210;90;15/02/2026;Detracciones;B1
CSV;

        $this->aplicarPlantilla($csv)
            ->assertOk()
            ->assertJsonPath('tributos', 2)
            ->assertJsonPath('pagos', 4);

        $igv = DeudaTributaria::query()->where('tributo_codigo', '1011')->firstOrFail();
        $this->assertSame(3, $igv->abonos()->count());
        $this->assertSame(550.0, round((float) $igv->abonos()->sum('monto'), 2));
        $essalud = DeudaTributaria::query()->where('tributo_codigo', '5210')->firstOrFail();
        $this->assertSame(1, $essalud->abonos()->count());
        $this->assertSame(90.0, round((float) $essalud->abonos()->sum('monto'), 2));
        $this->assertSame('A1', $igv->abonos()->orderBy('fecha_pago')->firstOrFail()->observacion);

        $this->aplicarPlantilla($csv)
            ->assertUnprocessable()
            ->assertJsonPath('pagos', 0);

        $this->assertSame(3, $igv->abonos()->count());
        $this->assertSame(1, $essalud->abonos()->count());
    }

    public function test_un_pago_que_supera_el_importe_no_guarda_nada(): void
    {
        $this->cartera(ruc: '20111000162');
        $csv = <<<'CSV'
ruc;periodo;1011
20111000162;01-2026;100

PAGOS
ruc;periodo;codigo del tributo;monto del pago;fecha de pago;medio de pago
20111000162;01-2026;1011;80;10/02/2026;Bancos
20111000162;01-2026;1011;40;20/02/2026;Caja
CSV;

        $this->aplicarPlantilla($csv)
            ->assertUnprocessable()
            ->assertJsonPath('aplicada', false);

        $this->assertDatabaseCount('deuda_tributarias', 0);
        $this->assertDatabaseCount('abonos_deudas', 0);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function tramo(int $desde, int $hasta, array $extra = []): array
    {
        return array_merge([
            'modo' => 'estados',
            'anio' => 2026,
            'politica' => 'conservar',
            'mes_desde' => $desde,
            'mes_hasta' => $hasta,
            'estado' => 'Declarado',
            'obligaciones' => ['sire', 'pdt', 'ple'],
            'alcance' => 'todos',
        ], $extra);
    }

    public function test_reinicio_solo_de_sire_no_borra_tributos_ni_pagos(): void
    {
        ['cliente' => $cliente] = $this->cartera();
        $this->guardarTributo($cliente, '01-2026', '1011', 500);

        $operacion = Operacion::where('cliente_id', $cliente->id)->where('periodo', '01-2026')->firstOrFail();
        $operacion->estado_compras_ventas = 'Declarado';
        $operacion->estado_pdt621 = 'Declarado';
        $operacion->save();

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'estado' => 'Pendiente',
            'obligaciones' => ['sire'],
        ]))->assertOk();

        $operacion->refresh();
        $this->assertSame('Pendiente', $operacion->estado_compras_ventas);
        $this->assertSame('Declarado', $operacion->estado_pdt621);
        $this->assertTrue((bool) $operacion->tiene_deuda);
        $this->assertSame(1, $operacion->deudasTributarias()->count());
    }

    public function test_reinicio_de_pdt_elimina_deuda_pdt_y_liquidacion_pero_conserva_plame(): void
    {
        ['cliente' => $cliente] = $this->cartera();
        $this->guardarTributo($cliente, '01-2026', '1011', 400);
        $this->guardarTributo($cliente, '01-2026', '5210', 150);

        $operacion = Operacion::where('cliente_id', $cliente->id)->where('periodo', '01-2026')->firstOrFail();
        LiquidacionPeriodo::create([
            'operacion_id' => $operacion->id,
            'cliente_id' => $cliente->id,
            'periodo' => '01-2026',
            'ventas_18' => 1000,
        ]);

        $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'estado' => 'Pendiente',
            'obligaciones' => ['pdt'],
        ]))->assertOk();

        $operacion->refresh();
        $this->assertSame('Pendiente', $operacion->estado_pdt621);
        $this->assertNull(LiquidacionPeriodo::where('operacion_id', $operacion->id)->first());
        $this->assertNull($operacion->deudasTributarias()->where('tributo_codigo', '1011')->first());
        $this->assertNotNull($operacion->deudasTributarias()->where('tributo_codigo', '5210')->first());
        $this->assertTrue((bool) $operacion->tiene_deuda);
        $this->assertSame(150.0, (float) $operacion->monto_deuda_inicial);
    }

    public function test_reinicio_omite_pdt_y_sire_en_cliente_del_nuevo_rus(): void
    {
        ['estudio' => $estudio] = $this->cartera(ruc: '20111000996', razon: 'EMPRESA MYPE SAC');
        Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'BODEGA RUS EIRL',
            'ruc' => '10444555661',
            'regimen_tributario' => 'RUS',
            'estado' => 'activo',
        ]);

        $respuesta = $this->postJson(route('control.carga_masiva.aplicar'), $this->tramo(1, 1, [
            'estado' => 'Declarado',
            'obligaciones' => ['sire', 'pdt'],
        ]));

        $respuesta->assertOk();
        $this->assertContains('Se omitió PDT 621 / SIRE en clientes del Nuevo RUS (no presentan estas declaraciones).', $respuesta->json('avisos'));
    }

    public function test_plantilla_csv_actualiza_declaraciones_de_forma_independiente(): void
    {
        $this->cartera(ruc: '20111000991');

        $csv = "ruc;periodo;sire;pdt;ple\n20111000991;01-2026;Declarado;Procesando;Pendiente\n";
        $this->aplicarPlantilla($csv)
            ->assertOk()
            ->assertJsonPath('estados', 1);

        $operacion = Operacion::query()->firstOrFail();
        $this->assertSame('Declarado', $operacion->estado_compras_ventas);
        $this->assertSame('Procesando', $operacion->estado_pdt621);
        $this->assertSame('Pendiente', $operacion->estado_ple);
    }

    public function test_plantilla_csv_actualiza_honorarios_rh(): void
    {
        $this->cartera(ruc: '20111000992');

        $csv = "ruc;periodo;honorarios\n20111000992;01-2026;Pago\n20111000992;02-2026;Pago\n";
        $this->aplicarPlantilla($csv)
            ->assertOk()
            ->assertJsonPath('honorarios', 2);

        $op1 = Operacion::where('periodo', '01-2026')->firstOrFail();
        $op2 = Operacion::where('periodo', '02-2026')->firstOrFail();
        $this->assertSame('Pagado', $op1->estado_pago_honorarios);
        $this->assertSame('Pagado', $op2->estado_pago_honorarios);
    }

    public function test_modo_honorarios_actualiza_rango_de_meses(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000993');

        $this->postJson(route('control.carga_masiva.aplicar'), [
            'modo' => 'honorarios',
            'anio' => 2026,
            'mes_desde' => 1,
            'mes_hasta' => 3,
            'estado' => 'Pagado',
            'alcance' => 'todos',
            'politica' => 'reemplazar',
        ])->assertOk()
            ->assertJsonPath('honorarios', 3);

        $this->assertSame(3, Operacion::where('cliente_id', $cliente->id)->where('estado_pago_honorarios', 'Pagado')->count());
    }

    public function test_plantilla_csv_valida_regimen_del_cliente_contra_codigo_de_renta(): void
    {
        ['cliente' => $cliente] = $this->cartera(ruc: '20111000994');
        $cliente->update(['regimen_tributario' => 'RER']);

        $csvError = "ruc;periodo;3121\n20111000994;01-2026;150\n";
        $this->aplicarPlantilla($csvError)
            ->assertUnprocessable()
            ->assertJsonPath('errores.0.mensaje', 'Fila 2: el tributo 3121 no corresponde al régimen RER de CLIENTE CARGA SAC (corresponde 3111 Renta RER 1.5%).');

        $csvValido = "ruc;periodo;3111\n20111000994;01-2026;150\n";
        $this->aplicarPlantilla($csvValido)
            ->assertOk()
            ->assertJsonPath('tributos', 1);
    }

    public function test_plantilla_csv_rechaza_multiples_codigos_de_renta_en_la_misma_fila(): void
    {
        $this->cartera(ruc: '20111000995');

        $csv = "ruc;periodo;3111;3121\n20111000995;01-2026;100;120\n";
        $this->aplicarPlantilla($csv)
            ->assertUnprocessable()
            ->assertJsonPath('errores.0.mensaje', 'Fila 2: no puede ingresar más de un tributo de renta mensual (RER, RMT, RG) para el mismo periodo.');
    }

    public function test_plantilla_csv_acepta_sinonimos_de_medio_de_pago(): void
    {
        $this->cartera(ruc: '20111000997');

        $csv = "ruc;periodo;1011\n20111000997;01-2026;600\n\nPAGOS\nruc;periodo;codigo;monto;fecha;medio\n20111000997;01-2026;1011;200;15/02/2026;efectivo\n20111000997;01-2026;1011;300;16/02/2026;banco\n20111000997;01-2026;1011;100;17/02/2026;detraccion\n";
        $this->aplicarPlantilla($csv)
            ->assertOk()
            ->assertJsonPath('tributos', 1)
            ->assertJsonPath('pagos', 3);

        $abonos = AbonoDeuda::query()->orderBy('fecha_pago')->get();
        $this->assertCount(3, $abonos);
        $this->assertSame('caja', $abonos[0]->medio_pago?->value);
        $this->assertSame('bancos', $abonos[1]->medio_pago?->value);
        $this->assertSame('detracciones', $abonos[2]->medio_pago?->value);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function aplicarPlantilla(string $csv, string $politica = 'conservar', array $extra = []): TestResponse
    {
        return $this->postJson(route('control.carga_masiva.aplicar'), array_merge([
            'modo' => 'plantilla',
            'anio' => 2026,
            'politica' => $politica,
            'contenido' => $csv,
        ], $extra));
    }

    private function guardarTributo(Cliente $cliente, string $periodo, string $codigo, float $monto, ?string $vencimiento = null): void
    {
        $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => $periodo,
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => $codigo,
            'monto_deuda_inicial' => $monto,
            'es_igv_justo' => false,
            'vencimiento_deuda' => $vencimiento,
        ])->assertOk();
    }

    /**
     * @return array{estudio: Estudio, user: User, cliente: Cliente}
     */
    private function cartera(
        string $ruc = '20111000999',
        string $razon = 'CLIENTE CARGA SAC',
        string $estado = 'activo',
        ?string $mesBaja = null,
    ): array {
        $estudio = Estudio::create([
            'nombre' => 'Estudio Carga '.$ruc,
            'ruc' => '10'.substr($ruc, 2),
        ]);
        $user = User::factory()->create([
            'estudio_id' => $estudio->id,
            'rol' => 'admin',
        ]);
        $this->actingAs($user);
        $cliente = Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => $razon,
            'ruc' => $ruc,
            'regimen_tributario' => 'MYPE',
            'estado' => $estado,
            'mes_baja' => $mesBaja,
        ]);

        return compact('estudio', 'user', 'cliente');
    }
}
