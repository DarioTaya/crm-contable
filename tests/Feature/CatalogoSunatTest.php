<?php

namespace Tests\Feature;

use App\Models\AbonoDeuda;
use App\Models\CatalogoMedioPago;
use App\Models\CatalogoTributo;
use App\Models\Cliente;
use App\Models\DeudaTributaria;
use App\Models\Estudio;
use App\Models\Operacion;
use App\Models\User;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogoSunatTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Catálogo',
            'ruc' => '20999888777',
        ]);
        $this->admin = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
        ]);
        $this->actingAs($this->admin);
        app(CatalogoSunat::class)->sembrar($this->estudio->id);
    }

    public function test_el_administrador_ve_los_codigos_oficiales_y_agrega_un_tributo(): void
    {
        $this->get(route('ajustes.codigos'))
            ->assertOk()
            ->assertSee('1011')
            ->assertSee('IGV - OPER. INT. - CTA. PROPIA')
            ->assertSee('Detracciones');

        $this->post(route('ajustes.codigos.tributos.store'), [
            'codigo' => 'AFP',
            'denominacion' => 'APORTE CON LETRAS',
            'formulario' => 'pdt_601',
            'rol' => 'otro',
        ])->assertSessionHasErrors('codigo');

        $this->post(route('ajustes.codigos.tributos.store'), [
            'codigo' => '5442',
            'denominacion' => 'RENTA DE QUINTA CATEGORIA',
            'nombre_corto' => 'Renta 5ta adicional',
            'formulario' => 'pdt_601',
            'rol' => 'otro',
        ])->assertRedirect(route('ajustes.codigos'));

        $this->assertDatabaseHas('catalogo_tributos', [
            'estudio_id' => $this->estudio->id,
            'codigo' => '5442',
            'denominacion' => 'RENTA DE QUINTA CATEGORIA',
            'sistema' => false,
        ]);

        $codigos = array_column(
            collect(app(CatalogoSunat::class)->catalogoFormulario())->firstWhere('id', 'pdt_601')['tributos'],
            'codigo',
        );
        $this->assertContains('5442', $codigos);
    }

    public function test_la_renta_mensual_se_amarra_al_regimen_del_cliente(): void
    {
        $this->post(route('ajustes.codigos.tributos.store'), [
            'codigo' => '3999',
            'denominacion' => 'RENTA ESPECIAL DEL ESTUDIO',
            'nombre_corto' => 'Renta estudio',
            'formulario' => 'pdt_621',
            'rol' => 'renta_mensual',
            'regimenes' => ['RER'],
        ])->assertRedirect(route('ajustes.codigos'));

        $catalogo = app(CatalogoSunat::class);
        $this->assertSame('3999', $catalogo->codigoRentaDelRegimen('RER'));
        $this->assertSame('3121', $catalogo->codigoRentaDelRegimen('RMT'));
        $this->assertNotContains('RER', CatalogoTributo::query()->where('codigo', '3111')->firstOrFail()->regimenes ?? []);
    }

    public function test_no_se_elimina_un_tributo_que_ya_tiene_deuda(): void
    {
        $igv = CatalogoTributo::query()->where('codigo', '1011')->firstOrFail();
        $this->delete(route('ajustes.codigos.tributos.destroy', $igv))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($igv);

        $this->post(route('ajustes.codigos.tributos.store'), [
            'codigo' => '8073',
            'denominacion' => 'FRACCIONAMIENTO',
            'formulario' => 'otro',
            'rol' => 'otro',
        ]);
        $fraccionamiento = CatalogoTributo::query()->where('codigo', '8073')->firstOrFail();
        $cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CLIENTE CATALOGO SAC',
            'ruc' => '20111000991',
            'regimen_tributario' => 'RER',
            'estado' => 'activo',
        ]);
        $operacion = Operacion::create([
            'estudio_id' => $this->estudio->id,
            'cliente_id' => $cliente->id,
            'periodo' => '07-2026',
        ]);
        DeudaTributaria::create([
            'estudio_id' => $this->estudio->id,
            'operacion_id' => $operacion->id,
            'tributo_codigo' => '8073',
            'monto_deuda_inicial' => 80,
        ]);

        $this->delete(route('ajustes.codigos.tributos.destroy', $fraccionamiento))
            ->assertSessionHasErrors('codigo');
        $this->assertModelExists($fraccionamiento);

        DeudaTributaria::query()->where('tributo_codigo', '8073')->delete();
        $this->delete(route('ajustes.codigos.tributos.destroy', $fraccionamiento))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($fraccionamiento);
    }

    public function test_el_medio_nuevo_se_acepta_en_el_pago_y_no_se_borra_si_ya_se_uso(): void
    {
        $this->post(route('ajustes.codigos.medios.store'), [
            'codigo' => 'BCP',
            'denominacion' => 'Banco de Crédito del Perú',
        ])->assertRedirect(route('ajustes.codigos'));

        $medio = CatalogoMedioPago::query()->where('codigo', 'bcp')->firstOrFail();
        $this->assertSame('Banco de Crédito del Perú', $medio->denominacion);

        $cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CLIENTE PAGO SAC',
            'ruc' => '20111000992',
            'regimen_tributario' => 'RMT',
            'estado' => 'activo',
        ]);
        $this->postJson(route('control.update'), [
            'cliente_id' => $cliente->id,
            'periodo' => '07-2026',
            'tiene_deuda' => true,
            'guardar_tributo' => true,
            'tributo_codigo' => '1011',
            'monto_deuda_inicial' => 100,
            'vencimiento_deuda' => '2026-08-20',
        ])->assertOk();

        $operacion = Operacion::query()->where('cliente_id', $cliente->id)->where('periodo', '07-2026')->firstOrFail();
        $deuda = DeudaTributaria::query()->where('operacion_id', $operacion->id)->where('tributo_codigo', '1011')->firstOrFail();

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 40,
            'fecha_pago' => '2026-08-18',
            'medio_pago' => 'bcp',
        ])->assertOk()
            ->assertJsonPath('abono.medio_pago', 'bcp')
            ->assertJsonPath('abono.medio_etiqueta', 'Banco de Crédito del Perú');

        $this->put(route('ajustes.codigos.medios.update', $medio), [
            'codigo' => 'bcp2',
            'denominacion' => 'Banco de Crédito del Perú',
            'activo' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('abonos_deudas', ['medio_pago' => 'bcp2']);

        $this->postJson(route('abonos_deudas.store'), [
            'operacion_id' => $operacion->id,
            'deuda_tributaria_id' => $deuda->id,
            'monto' => 10,
            'fecha_pago' => '2026-08-18',
            'medio_pago' => 'efectivo',
        ])->assertJsonValidationErrors('medio_pago');

        $this->delete(route('ajustes.codigos.medios.destroy', $medio))
            ->assertSessionHasErrors('codigo');

        AbonoDeuda::query()->where('medio_pago', 'bcp2')->delete();
        $this->delete(route('ajustes.codigos.medios.destroy', $medio))
            ->assertSessionHasNoErrors();
        $this->assertModelMissing($medio);
    }

    public function test_un_contador_no_entra_y_otro_estudio_no_ve_el_codigo(): void
    {
        $contador = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'contador',
        ]);
        $this->actingAs($contador)
            ->get(route('ajustes.codigos'))
            ->assertForbidden();

        $this->actingAs($this->admin);
        $this->post(route('ajustes.codigos.tributos.store'), [
            'codigo' => '5442',
            'denominacion' => 'TRIBUTO DEL ESTUDIO',
            'formulario' => 'otro',
            'rol' => 'otro',
        ]);
        $tributo = CatalogoTributo::query()->where('codigo', '5442')->firstOrFail();

        $otro = Estudio::create(['nombre' => 'Otro', 'ruc' => '20888111222']);
        $ajeno = User::factory()->create([
            'estudio_id' => $otro->id,
            'rol' => 'admin',
        ]);

        $this->actingAs($ajeno)
            ->put(route('ajustes.codigos.tributos.update', $tributo), [
                'denominacion' => 'ROBADO',
                'nombre_corto' => 'Robado',
                'formulario' => 'otro',
                'rol' => 'otro',
                'activo' => '1',
            ])
            ->assertNotFound();
    }
}
