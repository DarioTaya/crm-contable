<?php

namespace Tests\Feature;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Models\Credencial;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Frontera de seguridad entre inquilinos y entre roles.
 *
 * Cubre las dos dimensiones del aislamiento:
 *
 *  - Entre estudios: ninguna ruta debe resolver un registro de otro inquilino
 *    por su identificador, ni siquiera cuando este llega en el cuerpo de la
 *    peticion y no como parametro de ruta.
 *  - Dentro de un estudio: un asistente solo alcanza la cartera que tiene
 *    asignada, mientras que el administrador gobierna todo el estudio.
 *
 * Todos los casos invocan actingAs de forma explicita, porque el middleware
 * EnsureUserIsAuthenticated autentica un administrador del estudio 1 durante
 * las pruebas y enmascararia precisamente lo que aqui se verifica.
 */
class AislamientoEntreEstudiosTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudioPropio;

    private Estudio $estudioAjeno;

    private User $adminPropio;

    private User $asistentePropio;

    private Cliente $clienteAsignado;

    private Cliente $clienteNoAsignado;

    private Cliente $clienteAjeno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudioPropio = Estudio::factory()->create(['nombre' => 'Estudio Propio']);
        $this->estudioAjeno = Estudio::factory()->create(['nombre' => 'Estudio Ajeno']);

        $this->adminPropio = $this->usuario($this->estudioPropio, 'admin');
        $this->asistentePropio = $this->usuario($this->estudioPropio, 'asistente');

        $this->clienteAsignado = Cliente::factory()->paraEstudio($this->estudioPropio)->create();
        $this->clienteNoAsignado = Cliente::factory()->paraEstudio($this->estudioPropio)->create();
        $this->clienteAjeno = Cliente::factory()->paraEstudio($this->estudioAjeno)->create();

        $this->asistentePropio->clientes()->attach($this->clienteAsignado->id);
    }

    public function test_el_expediente_de_otro_estudio_no_existe_para_el_usuario(): void
    {
        $this->actingAs($this->adminPropio)
            ->get(route('clientes.show', $this->clienteAjeno))
            ->assertNotFound();
    }

    public function test_las_secciones_del_expediente_de_otro_estudio_no_existen(): void
    {
        $this->actingAs($this->adminPropio);

        $this->get(route('clientes.notificaciones', $this->clienteAjeno))->assertNotFound();
        $this->get(route('clientes.operaciones', $this->clienteAjeno))->assertNotFound();
        $this->get(route('clientes.sire', $this->clienteAjeno))->assertNotFound();
    }

    public function test_un_comprobante_de_otro_estudio_no_se_puede_descargar(): void
    {
        $comprobante = $this->comprobanteDe($this->clienteAjeno);

        $this->actingAs($this->adminPropio)
            ->get(route('comprobantes.download', [$comprobante, 'xml']))
            ->assertNotFound();
    }

    public function test_una_alerta_de_otro_estudio_no_se_puede_marcar_como_leida(): void
    {
        $alerta = $this->alertaDe($this->clienteAjeno);

        $this->actingAs($this->adminPropio)
            ->postJson(route('alertas.mark-as-read', $alerta))
            ->assertNotFound();

        $this->assertSame('Pendiente', $alerta->refresh()->estado);
    }

    public function test_el_listado_de_clientes_solo_cuenta_los_del_estudio(): void
    {
        Cliente::factory()->paraEstudio($this->estudioAjeno)->count(4)->create();

        $this->actingAs($this->adminPropio)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertDontSee($this->clienteAjeno->razon_social);
    }

    public function test_la_matriz_de_control_rechaza_un_cliente_de_otro_estudio(): void
    {
        $this->actingAs($this->adminPropio)
            ->postJson(route('control.update'), [
                'cliente_id' => $this->clienteAjeno->id,
                'periodo' => '09-2026',
                'estado' => 'Declarado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->assertDatabaseMissing('operaciones', ['cliente_id' => $this->clienteAjeno->id]);
    }

    public function test_la_matriz_de_balances_rechaza_un_cliente_de_otro_estudio(): void
    {
        $this->actingAs($this->adminPropio)
            ->postJson(route('balances.update'), [
                'cliente_id' => $this->clienteAjeno->id,
                'anio' => 2026,
                'mes' => 3,
                'estado' => 'Declarado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');

        $this->assertDatabaseMissing('control_balances', ['cliente_id' => $this->clienteAjeno->id]);
    }

    public function test_un_asistente_no_alcanza_el_expediente_que_no_tiene_asignado(): void
    {
        $this->actingAs($this->asistentePropio)
            ->get(route('clientes.show', $this->clienteNoAsignado))
            ->assertForbidden();

        $this->actingAs($this->asistentePropio)
            ->get(route('clientes.show', $this->clienteAsignado))
            ->assertOk();
    }

    public function test_un_asistente_no_puede_tocar_las_credenciales_de_un_cliente_ajeno(): void
    {
        Credencial::create([
            'cliente_id' => $this->clienteNoAsignado->id,
            'usuario_sol' => 'USUARIOSOL',
            'clave_sol' => 'claveoriginal',
        ]);

        $this->actingAs($this->asistentePropio)
            ->put(route('clientes.credenciales.update', $this->clienteNoAsignado), [
                'usuario_sol' => 'INTRUSO',
                'clave_sol' => 'claveintrusa',
            ])
            ->assertForbidden();

        $this->assertSame('USUARIOSOL', $this->clienteNoAsignado->credencial->refresh()->usuario_sol);
    }

    public function test_un_asistente_no_puede_disparar_sincronizaciones_de_un_cliente_ajeno(): void
    {
        $this->actingAs($this->asistentePropio)
            ->postJson(route('clientes.buzon.sync', $this->clienteNoAsignado))
            ->assertForbidden();

        $this->actingAs($this->asistentePropio)
            ->get(route('clientes.sunat', $this->clienteNoAsignado))
            ->assertForbidden();
    }

    public function test_un_asistente_no_puede_registrar_operaciones_de_un_cliente_ajeno(): void
    {
        $this->actingAs($this->asistentePropio)
            ->postJson(route('control.update'), [
                'cliente_id' => $this->clienteNoAsignado->id,
                'periodo' => '09-2026',
                'estado' => 'Declarado',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('cliente_id');
    }

    public function test_un_asistente_si_gobierna_la_cartera_que_tiene_asignada(): void
    {
        $this->actingAs($this->asistentePropio)
            ->postJson(route('control.update'), [
                'cliente_id' => $this->clienteAsignado->id,
                'periodo' => '09-2026',
                'estado' => 'Declarado',
            ])
            ->assertOk();

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $this->clienteAsignado->id,
            'estudio_id' => $this->estudioPropio->id,
        ]);
    }

    public function test_un_administrador_gobierna_toda_la_cartera_de_su_estudio(): void
    {
        $this->actingAs($this->adminPropio)
            ->get(route('clientes.show', $this->clienteNoAsignado))
            ->assertOk();
    }

    public function test_los_registros_hijos_heredan_el_estudio_de_su_cliente(): void
    {
        // Sin sesion, tal como corren la sincronizacion de buzones y las colas.
        $alerta = $this->alertaDe($this->clienteAjeno);
        $comprobante = $this->comprobanteDe($this->clienteAjeno);
        $credencial = Credencial::create([
            'cliente_id' => $this->clienteAjeno->id,
            'usuario_sol' => 'SOLAJENO',
            'clave_sol' => 'secreta',
        ]);

        $this->assertSame($this->estudioAjeno->id, $alerta->estudio_id);
        $this->assertSame($this->estudioAjeno->id, $comprobante->estudio_id);
        $this->assertSame($this->estudioAjeno->id, $credencial->estudio_id);
    }

    public function test_un_usuario_inactivo_pierde_el_acceso_a_su_cartera(): void
    {
        $this->asistentePropio->update(['activo' => false]);

        // El middleware cierra la sesion antes de que intervenga la politica,
        // de modo que la expulsion ocurre en el borde y no en la autorizacion.
        $this->actingAs($this->asistentePropio)
            ->get(route('clientes.show', $this->clienteAsignado))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    private function usuario(Estudio $estudio, string $rol): User
    {
        return User::create([
            'estudio_id' => $estudio->id,
            'name' => ucfirst($rol).' '.$estudio->nombre,
            'email' => "{$rol}.{$estudio->id}@estudio.test",
            'password' => Hash::make('secret123'),
            'rol' => $rol,
            'activo' => true,
        ]);
    }

    private function alertaDe(Cliente $cliente): AlertaBuzon
    {
        return AlertaBuzon::create([
            'cliente_id' => $cliente->id,
            'plataforma' => 'SUNAT',
            'asunto' => 'NOTIFICACION RESERVADA',
            'fecha_recepcion' => now(),
            'estado' => 'Pendiente',
            'prioridad' => 'Alta',
        ]);
    }

    private function comprobanteDe(Cliente $cliente): Comprobante
    {
        return Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-09',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '00000001',
            'ruc_tercero' => '20555444333',
            'total' => 118.00,
            'ruta_xml' => 'comprobantes/ajeno/test.xml',
        ]);
    }
}
