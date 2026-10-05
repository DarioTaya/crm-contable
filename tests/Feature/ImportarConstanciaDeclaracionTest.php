<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\ConstanciaDeclaracion;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use App\Models\User;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ImportarConstanciaDeclaracionTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $user;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Constancias Test',
            'ruc' => '20888777666',
        ]);

        $this->user = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'COMERCIAL PRUEBA SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $this->actingAs($this->user);
    }

    public function test_detalle_y_constancia_del_pdt_621_llenan_la_hoja_y_archivan_los_archivos(): void
    {
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'periodo' => '12-2025',
        ]);
        LiquidacionPeriodo::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $this->cliente->id,
            'operacion_id' => $operacion->id,
            'periodo' => '12-2025',
            'igv_saldo_favor_siguiente' => 800,
        ]);

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia-621.txt', $this->constancia621()),
            $this->archivo('detalle-621.txt', $this->detalle621()),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('campos.ventas_base', 25000);
        $response->assertJsonPath('campos.igv_ventas', 4500);
        $response->assertJsonPath('campos.compras_base', 10000);
        $response->assertJsonPath('campos.igv_compras', 1800);
        $response->assertJsonPath('campos.igv_saldo_favor_anterior', 800);
        $response->assertJsonPath('campos.igv_percepciones', 200);
        $response->assertJsonPath('campos.igv_retenciones', 100);
        $response->assertJsonPath('campos.renta_base_ingresos', 26000);
        $response->assertJsonPath('control.igv', 1900);
        $response->assertJsonPath('control.renta', 260);
        $response->assertJsonPath('calculo.igv', 1600);
        $response->assertJsonPath('calculo.renta', 260);
        $response->assertJsonPath('preservar.renta_tributo_codigo', '3121');
        $response->assertJsonPath('campos.notas_liquidacion', 'DJ 621 orden 1234567890 presentada el 14/02/2026 11:22');

        $codigos = collect($response->json('alertas'))->pluck('codigo');
        $this->assertTrue($codigos->contains('saldo_145'));
        $this->assertFalse($codigos->contains('igv_no_cuadra'));
        $this->assertFalse($codigos->contains('tributo_no_gestionado'));

        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_el_importe_oficial_de_sunat_se_guarda_aunque_el_motor_calcule_otro(): void
    {
        $response = $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1,
            'renta_calculada' => 100,
            'renta_tributo_codigo' => '3121',
            'respetar_igv_sunat' => true,
            'respetar_renta_sunat' => true,
            'igv_resultante_oficial' => 1500,
            'igv_saldo_favor_oficial' => 0,
            'renta_calculada_oficial' => 100,
            'renta_resultante_oficial' => 80,
            'renta_saldo_favor_oficial' => 0,
            'tributo_igv_105' => 40,
            'actualizar_deudas' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('liquidacion.igv_resultante_pagar', 1500);
        $response->assertJsonPath('liquidacion.igv_determinado_por_sunat', true);
        $response->assertJsonPath('liquidacion.renta_resultante_pagar', 80);
        $response->assertJsonPath('liquidacion.renta_determinado_por_sunat', true);
        $response->assertJsonPath('liquidacion.renta_tributo_codigo', '3121');

        $operacion = Operacion::query()->where('cliente_id', $this->cliente->id)->where('periodo', '01-2026')->first();
        $this->assertNotNull($operacion);
        $this->assertDatabaseHas('deuda_tributarias', [
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 1500,
        ]);
        $this->assertDatabaseHas('deuda_tributarias', [
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '3121',
            'monto_deuda_inicial' => 80,
        ]);
        $this->assertDatabaseHas('deuda_tributarias', [
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '1016',
            'monto_deuda_inicial' => 40,
        ]);

        $recalculo = $this->postJson(route('control.liquidacion'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => '01-2026',
            'ventas_base' => 10000,
            'igv_ventas' => 1800,
            'renta_base_ingresos' => 10000,
            'renta_tasa' => 1,
            'renta_calculada' => 100,
            'actualizar_deudas' => true,
        ]);

        $recalculo->assertOk();
        $recalculo->assertJsonPath('liquidacion.igv_resultante_pagar', 1800);
        $recalculo->assertJsonPath('liquidacion.igv_determinado_por_sunat', false);
        $recalculo->assertJsonPath('liquidacion.renta_resultante_pagar', 100);
        $this->assertSame(40.0, (float) DeudaTributaria::query()->where('tributo_codigo', '1016')->value('monto_deuda_inicial'));
    }

    public function test_constancia_sin_detalle_no_borra_las_bases_de_la_hoja(): void
    {
        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia-621.txt', $this->constancia621()),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $this->assertArrayNotHasKey('ventas_base', $response->json('campos'));
        $response->assertJsonPath('control.igv', 1900);
        $response->assertJsonPath('control.renta', 260);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('solo_constancia'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_ruc_distinto_bloquea_y_no_archiva(): void
    {
        $texto = str_replace('20123456789', '20999888777', $this->constancia621());

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('ajena.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $response->assertJsonPath('campos', []);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('ruc_distinto'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_periodo_distinto_bloquea_y_no_archiva(): void
    {
        $texto = str_replace('PERIODO: 202601', 'PERIODO: 202602', $this->constancia621());

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('febrero.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('periodo_distinto'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_plame_subido_en_la_hoja_621_no_llena_el_igv(): void
    {
        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('plame.txt', $this->constancia601()),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $response->assertJsonPath('campos', []);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('formulario_distinto'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_constancia_plame_carga_essalud_onp_y_retenciones_sin_tocar_afp(): void
    {
        $response = $this->postJsonArchivos('601', '01-2026', [
            $this->archivo('plame.txt', $this->constancia601()),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('campos.plame_essalud_a_pagar', 450);
        $response->assertJsonPath('campos.plame_onp_a_pagar', 260);
        $response->assertJsonPath('campos.plame_renta_4ta_a_pagar', 80);
        $response->assertJsonPath('campos.plame_renta_5ta_a_pagar', 150);
        $this->assertArrayNotHasKey('plame_afp_a_pagar', $response->json('campos'));
        $codigos = collect($response->json('alertas'))->pluck('codigo');
        $this->assertTrue($codigos->contains('afp_ausente'));
        $this->assertFalse($codigos->contains('tributo_plame_extra'));
    }

    public function test_aportes_afp_del_detalle_se_suman(): void
    {
        $texto = $this->constancia601()."\nAPORTE OBLIGATORIO AFP 180.00\nCOMISION AFP 20.00\nPRIMA DE SEGURO AFP 15.00\n";

        $response = $this->postJsonArchivos('601', '01-2026', [
            $this->archivo('plame-afp.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('campos.plame_afp_a_pagar', 215);
        $this->assertFalse(collect($response->json('alertas'))->pluck('codigo')->contains('afp_ausente'));
    }

    public function test_rectificatoria_se_carga_y_avisa(): void
    {
        $texto = str_replace('ORIGINAL', 'RECTIFICATORIA', $this->constancia621());

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('rectificatoria.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('control.igv', 1900);
        $response->assertJsonPath('identidad.tipo', 'rectificatoria');
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('rectificatoria'));
    }

    public function test_debito_fiscal_distinto_del_18_por_ciento_avisa_sin_bloquear(): void
    {
        $texto = <<<'TXT'
FORMULARIO VIRTUAL N 621
IGV - RENTA MENSUAL
RUC 20123456789
PERIODO 01/2026
CASILLA 100 10,000.00
CASILLA 101 1,000.00
CASILLA 189 1,000.00
TXT;

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('tasa.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('campos.ventas_base', 10000);
        $response->assertJsonPath('campos.igv_ventas', 1000);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('igv_ventas'));
    }

    public function test_casilla_189_distinta_del_tributo_1011_bloquea_la_carga(): void
    {
        $detalle = str_replace('CASILLA 189 1,900.00', 'CASILLA 189 1,500.00', $this->detalle621());

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia.txt', $this->constancia621()),
            $this->archivo('detalle.txt', $detalle),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $response->assertJsonPath('campos', []);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('igv_no_cuadra'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_boleta_de_pago_no_se_toma_como_declaracion(): void
    {
        $texto = <<<'TXT'
FORMULARIO 1662
BOLETA DE PAGO
RUC 20123456789
PERIODO 01/2026
NUMERO DE ORDEN 555444333
TXT;

        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('boleta.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('es_boleta'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_pdf_sin_texto_bloquea_y_no_archiva(): void
    {
        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('vacio.pdf', '%PDF-1.4'),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', true);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('pdf_sin_texto'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_pdf_con_texto_comprimido_se_lee(): void
    {
        $response = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia.pdf', $this->pdfConTexto($this->constancia621())),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('control.igv', 1900);
        $response->assertJsonPath('identidad.ruc', '20123456789');
    }

    public function test_el_mismo_archivo_no_se_archiva_dos_veces(): void
    {
        $archivo = $this->constancia621();
        $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia.txt', $archivo),
        ])->assertOk();

        $segunda = $this->postJsonArchivos('621', '01-2026', [
            $this->archivo('constancia.txt', $archivo),
        ]);

        $segunda->assertOk();
        $segunda->assertJsonPath('bloqueado', false);
        $this->assertFalse(collect($segunda->json('alertas'))->pluck('codigo')->contains('archivo_repetido'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    public function test_essalud_bajo_el_piso_de_la_rmv_avisa(): void
    {
        $texto = str_replace('5210 ESSALUD SEG REGULAR TRABAJADOR 450.00', '5210 ESSALUD SEG REGULAR TRABAJADOR 50.00', $this->constancia601());

        $response = $this->postJsonArchivos('601', '01-2026', [
            $this->archivo('plame.txt', $texto),
        ]);

        $response->assertOk();
        $response->assertJsonPath('bloqueado', false);
        $response->assertJsonPath('campos.plame_essalud_a_pagar', 50);
        $this->assertTrue(collect($response->json('alertas'))->pluck('codigo')->contains('essalud_minimo'));
    }

    public function test_los_pdf_oficiales_de_julio_2026_leen_el_periodo_y_los_importes_de_sunat(): void
    {
        $chapi = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'IMPORTACIONES VIRGEN DE CHAPI SRL',
            'ruc' => '20498311700',
            'regimen_tributario' => 'RER',
            'estado' => 'activo',
        ]);

        $detalle = UploadedFile::fake()->createWithContent(
            'DETALLE SUSTI PDT 621 JUL-26.pdf',
            (string) file_get_contents(base_path('DETALLE SUSTI PDT 621 JUL-26.pdf')),
        );
        $constancia = UploadedFile::fake()->createWithContent(
            'CONSTANCIA PDT 601 JUL-26.pdf',
            (string) file_get_contents(base_path('CONSTANCIA PDT 601 JUL-26.pdf')),
        );

        $pdt = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $chapi->id,
            'periodo' => '07-2026',
            'formulario' => '621',
            'archivos' => [$detalle],
        ], ['Accept' => 'application/json']);

        $pdt->assertOk();
        $pdt->assertJsonPath('bloqueado', false);
        $pdt->assertJsonPath('identidad.periodo', '07-2026');
        $pdt->assertJsonPath('identidad.ruc', '20498311700');
        $pdt->assertJsonPath('identidad.numero_orden', '1201628565');
        $pdt->assertJsonPath('identidad.tipo', 'sustitutoria');
        $pdt->assertJsonPath('campos.ventas_base', 431695);
        $pdt->assertJsonPath('campos.igv_ventas', 77705);
        $pdt->assertJsonPath('campos.compras_base', 334502);
        $pdt->assertJsonPath('campos.igv_compras', 60210);
        $pdt->assertJsonPath('campos.igv_retenciones', 182);
        $pdt->assertJsonPath('campos.igv_descuentos_base', 1254);
        $pdt->assertJsonPath('campos.igv_descuentos', 226);
        $pdt->assertJsonPath('campos.igv_compras_ley_base', 65);
        $pdt->assertJsonPath('campos.igv_compras_ley', 7);
        $pdt->assertJsonPath('campos.renta_base_ingresos', 430441);
        $pdt->assertJsonPath('campos.renta_tasa', 1.5);
        $pdt->assertJsonPath('campos.renta_compensacion_itan', 4174);
        $pdt->assertJsonPath('control.igv', 17080);
        $pdt->assertJsonPath('control.renta', 2283);
        $pdt->assertJsonPath('calculo.igv', 17080);
        $pdt->assertJsonPath('calculo.renta', 2283);
        $pdt->assertJsonPath('preservar.renta_tributo_codigo', '3111');
        $codigosPdt = collect($pdt->json('alertas'))->pluck('codigo');
        $this->assertFalse($codigosPdt->contains('periodo_ausente'));
        $this->assertFalse($codigosPdt->contains('igv_distinto_al_motor'));
        $this->assertTrue($codigosPdt->contains('igv_justo'));
        $this->assertTrue($codigosPdt->contains('sustitutoria'));

        $plame = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $chapi->id,
            'periodo' => '07-2026',
            'formulario' => '601',
            'archivos' => [$constancia],
        ], ['Accept' => 'application/json']);

        $plame->assertOk();
        $plame->assertJsonPath('bloqueado', false);
        $plame->assertJsonPath('identidad.periodo', '07-2026');
        $plame->assertJsonPath('identidad.numero_orden', '1200828064');
        $plame->assertJsonPath('campos.plame_essalud_a_pagar', 1128);
        $plame->assertJsonPath('campos.plame_onp_a_pagar', 1174);
        $plame->assertJsonPath('campos.plame_renta_4ta_a_pagar', 157);
        $plame->assertJsonPath('campos.plame_renta_5ta_a_pagar', 0);
        $this->assertArrayNotHasKey('plame_essalud_base', $plame->json('campos'));
        $this->assertArrayNotHasKey('plame_renta_4ta_base', $plame->json('campos'));
        $codigosPlame = collect($plame->json('alertas'))->pluck('codigo');
        $this->assertFalse($codigosPlame->contains('periodo_ausente'));
        $this->assertFalse($codigosPlame->contains('tributo_plame_extra'));
    }

    public function test_el_detalle_de_enero_2026_cuadra_descuentos_igv_y_renta(): void
    {
        $chapi = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'IMPORTACIONES VIRGEN DE CHAPI SRL',
            'ruc' => '20498311700',
            'regimen_tributario' => 'RMT',
            'estado' => 'activo',
        ]);

        $detalle = UploadedFile::fake()->createWithContent(
            'DETALLE PDT 621 ENE-26.pdf',
            (string) file_get_contents(base_path('DETALLE PDT 621 ENE-26.pdf')),
        );

        $pdt = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $chapi->id,
            'periodo' => '01-2026',
            'formulario' => '621',
            'archivos' => [$detalle],
        ], ['Accept' => 'application/json']);

        $pdt->assertOk();
        $pdt->assertJsonPath('bloqueado', false);
        $pdt->assertJsonPath('identidad.periodo', '01-2026');
        $pdt->assertJsonPath('campos.ventas_base', 562089);
        $pdt->assertJsonPath('campos.igv_ventas', 101176);
        $pdt->assertJsonPath('campos.igv_descuentos_base', 1288);
        $pdt->assertJsonPath('campos.igv_descuentos', 232);
        $pdt->assertJsonPath('campos.compras_base', 469099);
        $pdt->assertJsonPath('campos.igv_compras', 84438);
        $pdt->assertJsonPath('campos.igv_retenciones', 406);
        $pdt->assertJsonPath('campos.renta_base_ingresos', 560801);
        $pdt->assertJsonPath('campos.renta_tasa', 1);
        $pdt->assertJsonPath('control.igv', 16100);
        $pdt->assertJsonPath('control.renta', 5608);
        $pdt->assertJsonPath('calculo.igv', 16100);
        $pdt->assertJsonPath('calculo.renta', 5608);
        $pdt->assertJsonPath('preservar.renta_tributo_codigo', '3121');
        $codigos = collect($pdt->json('alertas'))->pluck('codigo');
        $this->assertFalse($codigos->contains('periodo_ausente'));
        $this->assertFalse($codigos->contains('igv_justo'));
        $this->assertFalse($codigos->contains('igv_distinto_al_motor'));
        $this->assertFalse($codigos->contains('renta_distinta_al_motor'));
    }

    public function test_qori_poracota_lee_compras_no_gravadas_y_registra_el_pago_con_detracciones(): void
    {
        app(CatalogoSunat::class)->sembrar($this->estudio->id);

        $qori = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'QORI PORACOTA S.A.C.',
            'ruc' => '20614054230',
            'regimen_tributario' => 'RMT',
            'estado' => 'activo',
        ]);

        $detalle = UploadedFile::fake()->createWithContent(
            'DETALLE PDT 621 ENE-26 qori.pdf',
            (string) file_get_contents(base_path('DETALLE PDT 621 ENE-26 qori.pdf')),
        );
        $constancia = UploadedFile::fake()->createWithContent(
            'CONSTANCIA PDT 621 ENE-26 qori.pdf',
            (string) file_get_contents(base_path('CONSTANCIA PDT 621 ENE-26 qori.pdf')),
        );

        $respuesta = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $qori->id,
            'periodo' => '01-2026',
            'formulario' => '621',
            'archivos' => [$detalle, $constancia],
        ], ['Accept' => 'application/json']);

        $respuesta->assertOk();
        $respuesta->assertJsonPath('bloqueado', false);
        $respuesta->assertJsonPath('campos.ventas_base', 23344);
        $respuesta->assertJsonPath('campos.igv_ventas', 4202);
        $respuesta->assertJsonPath('campos.compras_base', 9679);
        $respuesta->assertJsonPath('campos.igv_compras', 1742);
        $respuesta->assertJsonPath('campos.compras_internas_no_gravadas', 80);
        $respuesta->assertJsonPath('campos.igv_percepciones', 54);
        $respuesta->assertJsonPath('campos.renta_base_ingresos', 23344);
        $respuesta->assertJsonPath('campos.renta_tasa', 1);
        $respuesta->assertJsonPath('control.igv', 0);
        $respuesta->assertJsonPath('control.renta', 233);
        $respuesta->assertJsonPath('calculo.igv', 0);
        $respuesta->assertJsonPath('calculo.renta', 233);
        $respuesta->assertJsonPath('pagos_registrados', 1);
        $this->assertFalse(collect($respuesta->json('alertas'))->pluck('codigo')->contains('ordenes_distintas'));
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        $deuda = DeudaTributaria::query()->where('tributo_codigo', '3121')->first();
        $this->assertNotNull($deuda);
        $this->assertSame(233.0, (float) $deuda->monto_deuda_inicial);
        $abono = AbonoDeuda::query()->where('deuda_tributaria_id', $deuda->id)->first();
        $this->assertNotNull($abono);
        $this->assertSame(233.0, (float) $abono->monto);
        $this->assertSame('detracciones', $abono->medio_pago);
        $this->assertSame('2026-02-12', $abono->fecha_pago->toDateString());

        $repetido = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $qori->id,
            'periodo' => '01-2026',
            'formulario' => '621',
            'archivos' => [$constancia],
        ], ['Accept' => 'application/json']);

        $repetido->assertOk();
        $this->assertTrue(collect($repetido->json('alertas'))->pluck('codigo')->contains('pago_ya_registrado'));
        $this->assertSame(1, AbonoDeuda::query()->count());
    }

    public function test_invitado_recibe_401_al_importar(): void
    {
        auth()->logout();

        $this->withHeader('X-Test-Guest', '1')
            ->postJson(route('control.liquidacion.importar'), [])
            ->assertUnauthorized();
    }

    public function test_cliente_de_otro_estudio_recibe_422(): void
    {
        $otro = Estudio::create([
            'nombre' => 'Otro Estudio',
            'ruc' => '20111222333',
        ]);
        $ajeno = Cliente::create([
            'estudio_id' => $otro->id,
            'razon_social' => 'AJENA SAC',
            'ruc' => '20999111222',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $response = $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $ajeno->id,
            'periodo' => '01-2026',
            'formulario' => '621',
            'archivos' => [$this->archivo('constancia.txt', $this->constancia621())],
        ], ['Accept' => 'application/json']);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('cliente_id');
        $this->assertSame(0, ConstanciaDeclaracion::query()->count());
    }

    /**
     * @param  list<UploadedFile>  $archivos
     */
    private function postJsonArchivos(string $formulario, string $periodo, array $archivos): TestResponse
    {
        return $this->post(route('control.liquidacion.importar'), [
            'cliente_id' => $this->cliente->id,
            'periodo' => $periodo,
            'formulario' => $formulario,
            'archivos' => $archivos,
        ], ['Accept' => 'application/json']);
    }

    private function archivo(string $nombre, string $contenido): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, $contenido);
    }

    private function constancia621(): string
    {
        return <<<'TXT'
CONSTANCIA DE PRESENTACION
FORMULARIO: 0621
NUMERO DE ORDEN: 1234567890
FECHA DE PRESENTACION: 14/02/2026 11:22:33
RUC: 20123456789
RAZON SOCIAL: COMERCIAL PRUEBA SAC
PERIODO: 202601
TIPO DE DECLARACION: ORIGINAL
1011 IGV - OPER. INT. - CTA. PROPIA 1,900.00
3121 RENTA - REGIMEN MYPE TRIBUTARIO 260.00
TXT;
    }

    private function detalle621(): string
    {
        return <<<'TXT'
FORMULARIO VIRTUAL N 621
IGV - RENTA MENSUAL
RUC 20123456789
PERIODO 01/2026
CASILLA 100 25,000.00
CASILLA 101 4,500.00
CASILLA 105 1,000.00
CASILLA 107 10,000.00
CASILLA 108 1,800.00
CASILLA 140 2,700.00
CASILLA 145 500.00
CASILLA 171 200.00
CASILLA 179 100.00
CASILLA 184 2,200.00
CASILLA 189 1,900.00
CASILLA 301 26,000.00
CASILLA 312 260.00
CASILLA 303 0.00
CASILLA 307 260.00
TXT;
    }

    private function constancia601(): string
    {
        return <<<'TXT'
CONSTANCIA DE PRESENTACION
FORMULARIO VIRTUAL N 0601
PDT PLANILLA ELECTRONICA PLAME
RUC: 20123456789
PERIODO: 202601
NUMERO DE ORDEN: 9988776655
FECHA DE PRESENTACION: 12/02/2026 09:00:00
TIPO DE DECLARACION: ORIGINAL
5210 ESSALUD SEG REGULAR TRABAJADOR 450.00
5310 SNP - LEY 19990 260.00
3042 RENTA 4TA. CATEG. RETENCIONES 80.00
3052 RENTA 5TA. CATEG. RETENCIONES 150.00
TXT;
    }

    private function pdfConTexto(string $texto): string
    {
        $literal = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', '\n'], $texto);
        $contenido = "BT /F1 12 Tf 50 750 Td ({$literal}) Tj ET";
        $stream = gzcompress($contenido);
        $length = strlen($stream);
        $objetos = [
            "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n",
            "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n",
            "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 600 800] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n",
            "4 0 obj << /Length {$length} /Filter /FlateDecode >> stream\n{$stream}\nendstream endobj\n",
            "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objetos as $objeto) {
            $offsets[] = strlen($pdf);
            $pdf .= $objeto;
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 6\n0000000000 65535 f \n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size 6 /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

        return $pdf;
    }
}
