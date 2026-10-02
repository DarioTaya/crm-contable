<?php

namespace Tests\Feature;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Cubre el estandar estructural de la tabla `clientes`: aislamiento por
 * estudio, unicidad acotada al inquilino y tipado del dominio mediante enums.
 */
class ClienteEstandarMultitenantTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudioA;

    private Estudio $estudioB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudioA = Estudio::factory()->create(['nombre' => 'Estudio Alfa']);
        $this->estudioB = Estudio::factory()->create(['nombre' => 'Estudio Beta']);
    }

    public function test_dos_estudios_pueden_registrar_el_mismo_ruc(): void
    {
        $ruc = '20123456789';

        $clienteA = Cliente::factory()->paraEstudio($this->estudioA)->create(['ruc' => $ruc]);
        $clienteB = Cliente::factory()->paraEstudio($this->estudioB)->create(['ruc' => $ruc]);

        $this->assertNotSame($clienteA->id, $clienteB->id);
        $this->assertSame($ruc, $clienteA->ruc);
        $this->assertSame($ruc, $clienteB->ruc);
        $this->assertSame(2, Cliente::withoutGlobalScopes()->where('ruc', $ruc)->count());
    }

    public function test_un_mismo_estudio_no_puede_repetir_un_ruc(): void
    {
        Cliente::factory()->paraEstudio($this->estudioA)->create(['ruc' => '20123456789']);

        $this->expectException(QueryException::class);

        Cliente::factory()->paraEstudio($this->estudioA)->create(['ruc' => '20123456789']);
    }

    public function test_la_correlativa_de_cliente_es_independiente_en_cada_estudio(): void
    {
        $primeroA = Cliente::factory()->paraEstudio($this->estudioA)->create();
        $primeroB = Cliente::factory()->paraEstudio($this->estudioB)->create();

        $this->assertSame(1, $primeroA->numero);
        $this->assertSame(1, $primeroB->numero);
    }

    public function test_el_modelo_adopta_un_estudio_cuando_no_se_indica(): void
    {
        $this->actingAs($this->usuarioDe($this->estudioB));

        $cliente = Cliente::create([
            'razon_social' => 'SIN ESTUDIO EXPLICITO SAC',
            'ruc' => '20999999999',
            'regimen_tributario' => RegimenTributario::Rmt,
            'estado' => EstadoCliente::Activo,
        ]);

        $this->assertSame($this->estudioB->id, $cliente->estudio_id);
    }

    public function test_la_base_de_datos_rechaza_un_cliente_sin_estudio(): void
    {
        $this->expectException(QueryException::class);

        // Se escribe con el query builder para saltarse el hook del modelo y
        // comprobar que la restriccion NOT NULL existe realmente en el motor.
        DB::table('clientes')->insert([
            'estudio_id' => null,
            'razon_social' => 'HUERFANO SAC',
            'ruc' => '20888888888',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_un_usuario_solo_alcanza_los_clientes_de_su_estudio(): void
    {
        Cliente::factory()->paraEstudio($this->estudioA)->count(3)->create();
        Cliente::factory()->paraEstudio($this->estudioB)->count(5)->create();

        $this->actingAs($this->usuarioDe($this->estudioA));
        $this->assertSame(3, Cliente::count());

        $this->actingAs($this->usuarioDe($this->estudioB));
        $this->assertSame(5, Cliente::count());
    }

    public function test_el_regimen_y_el_estado_se_exponen_como_enums(): void
    {
        $cliente = Cliente::factory()->paraEstudio($this->estudioA)->create([
            'regimen_tributario' => 'RER',
            'estado' => 'suspendido',
        ]);

        $cliente->refresh();

        $this->assertSame(RegimenTributario::Rer, $cliente->regimen_tributario);
        $this->assertSame(EstadoCliente::Suspendido, $cliente->estado);
        $this->assertSame('Régimen Especial de Renta', $cliente->regimen_tributario->nombreOficial());
        $this->assertSame('Suspendido', $cliente->estado_estudio_label);
    }

    public function test_el_estado_admite_los_booleanos_heredados(): void
    {
        $activo = Cliente::factory()->paraEstudio($this->estudioA)->create(['estado' => true]);
        $retirado = Cliente::factory()->paraEstudio($this->estudioA)->create(['estado' => false]);

        $this->assertSame(EstadoCliente::Activo, $activo->refresh()->estado);
        $this->assertSame(EstadoCliente::Retirado, $retirado->refresh()->estado);
    }

    public function test_solo_los_regimenes_con_libros_electronicos_llevan_balances(): void
    {
        $conBalances = Cliente::factory()->paraEstudio($this->estudioA)->regimen(RegimenTributario::Rg)->create();
        $sinBalances = Cliente::factory()->paraEstudio($this->estudioA)->regimen(RegimenTributario::Rus)->create();

        $this->assertTrue($conBalances->llevaBalances());
        $this->assertFalse($sinBalances->llevaBalances());
        $this->assertSame(['RMT', 'RG'], RegimenTributario::valoresConLibrosElectronicos());
    }

    public function test_el_regimen_invalido_es_rechazado_por_la_validacion(): void
    {
        $this->actingAs($this->usuarioDe($this->estudioA));

        $response = $this->post(route('clientes.store'), [
            'razon_social' => 'PRUEBA SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'REGIMEN_FANTASMA',
        ]);

        $response->assertSessionHasErrors('regimen_tributario');
    }

    private function usuarioDe(Estudio $estudio): User
    {
        return User::create([
            'estudio_id' => $estudio->id,
            'name' => 'Contador '.$estudio->nombre,
            'email' => "admin.{$estudio->id}@estudio.test",
            'password' => Hash::make('secret123'),
            'rol' => 'admin',
            'activo' => true,
        ]);
    }
}
