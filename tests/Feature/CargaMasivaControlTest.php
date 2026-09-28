<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
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
        $this->assertStringContainsString('ruc;razon_social;periodo;sire;pdt_621;pdt_601;pagado;1011 IGV 18%;igv_justo', $contenido);
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
