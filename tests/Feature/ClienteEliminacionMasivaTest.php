<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteEliminacionMasivaTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $admin;

    private User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::factory()->create([
            'nombre' => 'Estudio Principal',
            'activo' => true,
        ]);

        $this->admin = User::factory()->create([
            'estudio_id' => $this->estudio->id,
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->asistente = User::factory()->asistente()->create([
            'estudio_id' => $this->estudio->id,
            'activo' => true,
        ]);
    }

    public function test_administrador_puede_enviar_clientes_masivamente_a_la_papelera(): void
    {
        $c1 = Cliente::factory()->paraEstudio($this->estudio)->create([
            'razon_social' => 'CLIENTE UNO SAC',
            'ruc' => '20111111111',
            'numero' => 1,
        ]);
        $c2 = Cliente::factory()->paraEstudio($this->estudio)->create([
            'razon_social' => 'CLIENTE DOS SAC',
            'ruc' => '20222222222',
            'numero' => 2,
        ]);
        $c3 = Cliente::factory()->paraEstudio($this->estudio)->create([
            'razon_social' => 'CLIENTE TRES SAC',
            'ruc' => '20333333333',
            'numero' => 3,
        ]);

        Credencial::create([
            'cliente_id' => $c1->id,
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'Secret123',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('clientes.eliminar-masivo'), [
            'ids' => [$c1->id, $c2->id],
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('clientes', ['id' => $c1->id]);
        $this->assertSoftDeleted('clientes', ['id' => $c2->id]);
        $this->assertNotSoftDeleted('clientes', ['id' => $c3->id]);

        // Credenciales y datos históricos deben preservarse intactos
        $this->assertDatabaseHas('credenciales', [
            'cliente_id' => $c1->id,
            'usuario_sol' => 'MODDATOS',
        ]);

        $this->assertSame(1, Cliente::count());
        $this->assertSame(2, Cliente::onlyTrashed()->count());
    }

    public function test_asistente_no_puede_eliminar_clientes_masivamente(): void
    {
        $cliente = Cliente::factory()->paraEstudio($this->estudio)->create();

        $response = $this->actingAs($this->asistente)->delete(route('clientes.eliminar-masivo'), [
            'ids' => [$cliente->id],
        ]);

        $response->assertForbidden();
        $this->assertNotSoftDeleted('clientes', ['id' => $cliente->id]);
    }

    public function test_no_se_pueden_eliminar_clientes_de_otro_estudio(): void
    {
        $otroEstudio = Estudio::factory()->create();
        $clienteOtroEstudio = Cliente::factory()->paraEstudio($otroEstudio)->create([
            'razon_social' => 'OTRO ESTUDIO SAC',
            'ruc' => '20999999999',
        ]);

        $response = $this->actingAs($this->admin)->delete(route('clientes.eliminar-masivo'), [
            'ids' => [$clienteOtroEstudio->id],
        ]);

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('warning');

        $this->assertNotSoftDeleted('clientes', ['id' => $clienteOtroEstudio->id]);
    }

    public function test_administrador_puede_restaurar_masivamente_desde_la_papelera(): void
    {
        $c1 = Cliente::factory()->paraEstudio($this->estudio)->create();
        $c2 = Cliente::factory()->paraEstudio($this->estudio)->create();
        $c1->delete();
        $c2->delete();

        $this->assertSame(2, Cliente::onlyTrashed()->count());

        $response = $this->actingAs($this->admin)->post(route('clientes.restaurar-masivo'), [
            'ids' => [$c1->id, $c2->id],
        ]);

        $response->assertRedirect(route('clientes.papelera'));
        $response->assertSessionHas('success');

        $this->assertNotSoftDeleted('clientes', ['id' => $c1->id]);
        $this->assertNotSoftDeleted('clientes', ['id' => $c2->id]);
        $this->assertSame(0, Cliente::onlyTrashed()->count());
    }

    public function test_administrador_puede_purgar_definitivamente_masivamente(): void
    {
        $c1 = Cliente::factory()->paraEstudio($this->estudio)->create();
        $c2 = Cliente::factory()->paraEstudio($this->estudio)->create();
        Credencial::create([
            'cliente_id' => $c1->id,
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'Secret123',
        ]);
        $c1->delete();
        $c2->delete();

        $response = $this->actingAs($this->admin)->delete(route('clientes.purgar-masivo'), [
            'ids' => [$c1->id, $c2->id],
        ]);

        $response->assertRedirect(route('clientes.papelera'));
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('clientes', ['id' => $c1->id]);
        $this->assertDatabaseMissing('clientes', ['id' => $c2->id]);
        $this->assertDatabaseMissing('credenciales', ['cliente_id' => $c1->id]);
    }

    public function test_validacion_de_ids_requeridos(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('clientes.eliminar-masivo'), [
            'ids' => [],
        ]);

        $response->assertSessionHasErrors('ids');
    }
}
