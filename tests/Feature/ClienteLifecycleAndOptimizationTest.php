<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Credencial;
use App\Models\Estudio;
use App\Models\User;
use App\Services\SunafilBuzonSyncService;
use App\Services\SunatBuzonSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClienteLifecycleAndOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $admin;

    protected User $asistente;

    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Demo', 'correo' => 'demo@estudio.pe', 'activo' => true]
        );

        $this->admin = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Admin Titular',
            'email' => 'admin.lifecycle@estudio.pe',
            'password' => Hash::make('password'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->asistente = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Asistente Operativo',
            'email' => 'asistente.lifecycle@estudio.pe',
            'password' => Hash::make('password'),
            'rol' => 'asistente',
            'activo' => true,
        ]);

        $this->cliente = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'EMPRESA LIFECYCLE SAC',
            'ruc' => '20777888999',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        // Crear credencial asociada
        $this->cliente->credencial()->create([
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos',
        ]);

        // Crear notificación y operación de prueba
        $this->cliente->notificaciones()->create([
            'tipo' => 'SUNAT',
            'emisor' => 'SUNAT',
            'asunto' => 'Aviso Informativo',
            'mensaje' => 'Mensaje de prueba',
            'fecha' => now(),
            'leido' => false,
        ]);

        $this->cliente->operaciones()->create([
            'periodo' => now()->format('m-Y'),
            'estado_compras_ventas' => 'Pendiente',
            'estado_pdt621' => 'Pendiente',
            'estado_ple' => 'Pendiente',
        ]);
    }

    public function test_destroy_performs_soft_delete_and_preserves_historical_records(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('clientes.destroy', $this->cliente));

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');

        // El registro existe pero tiene deleted_at no nulo
        $this->assertSoftDeleted('clientes', [
            'id' => $this->cliente->id,
            'ruc' => '20777888999',
        ]);

        // Sus relaciones históricas NO deben haberse borrado en cascada
        $this->assertDatabaseHas('credenciales', [
            'cliente_id' => $this->cliente->id,
            'usuario_sol' => 'MODDATOS',
        ]);

        $this->assertDatabaseHas('notificaciones', [
            'cliente_id' => $this->cliente->id,
            'asunto' => 'Aviso Informativo',
        ]);

        $this->assertDatabaseHas('operaciones', [
            'cliente_id' => $this->cliente->id,
        ]);

        // No debe aparecer en el listado normal
        $this->assertCount(0, Cliente::all());
        $this->assertCount(1, Cliente::onlyTrashed()->get());
    }

    public function test_admin_can_view_papelera_and_see_trashed_clients(): void
    {
        $this->cliente->delete();

        $response = $this->actingAs($this->admin)->get(route('clientes.papelera'));

        $response->assertStatus(200);
        $response->assertViewIs('clientes.papelera');
        $response->assertSee('EMPRESA LIFECYCLE SAC');
        $response->assertSee('20777888999');
        $response->assertSee('Restaurar');
        $response->assertSee('Purgar');
    }

    public function test_assistant_cannot_access_papelera(): void
    {
        $this->cliente->delete();

        $response = $this->actingAs($this->asistente)->get(route('clientes.papelera'));

        $response->assertStatus(403);
    }

    public function test_admin_can_restore_trashed_client(): void
    {
        $this->cliente->delete();
        $this->assertTrue($this->cliente->fresh()->trashed());

        $response = $this->actingAs($this->admin)->post(route('clientes.restaurar', $this->cliente->id));

        $response->assertRedirect(route('clientes.index'));
        $response->assertSessionHas('success');

        $this->assertFalse($this->cliente->fresh()->trashed());
        $this->assertCount(1, Cliente::all());

        // Al restaurar, sus relaciones siguen vivas
        $this->assertEquals('MODDATOS', $this->cliente->fresh()->credencial->usuario_sol);
    }

    public function test_admin_can_force_delete_client_and_purge_all_physical_records(): void
    {
        $this->cliente->delete();

        $response = $this->actingAs($this->admin)->delete(route('clientes.forzar-eliminacion', $this->cliente->id));

        $response->assertRedirect(route('clientes.papelera'));
        $response->assertSessionHas('success');

        // Debe haber desaparecido totalmente de la tabla
        $this->assertDatabaseMissing('clientes', [
            'id' => $this->cliente->id,
        ]);

        // Sus relaciones hijas deben haber sido purgadas limpiamente
        $this->assertDatabaseMissing('credenciales', ['cliente_id' => $this->cliente->id]);
        $this->assertDatabaseMissing('notificaciones', ['cliente_id' => $this->cliente->id]);
        $this->assertDatabaseMissing('operaciones', ['cliente_id' => $this->cliente->id]);
    }

    public function test_assistant_cannot_force_delete_or_restore_clients(): void
    {
        $this->cliente->delete();

        $responseRestore = $this->actingAs($this->asistente)->post(route('clientes.restaurar', $this->cliente->id));
        $responseRestore->assertStatus(403);

        $responseForce = $this->actingAs($this->asistente)->delete(route('clientes.forzar-eliminacion', $this->cliente->id));
        $responseForce->assertStatus(403);
    }

    public function test_scrapers_skip_inactive_and_trashed_clients_to_save_cpu_and_ram(): void
    {
        $sunatSync = app(SunatBuzonSyncService::class);
        $sunafilSync = app(SunafilBuzonSyncService::class);

        // 1. Cliente Suspendido
        $this->cliente->update(['estado' => 'suspendido']);

        $resSunat = $sunatSync->syncCliente($this->cliente);
        $this->assertFalse($resSunat['success']);
        $this->assertStringContainsString('no se encuentra activo', $resSunat['message']);

        $resSunafil = $sunafilSync->syncCliente($this->cliente);
        $this->assertFalse($resSunafil['success']);
        $this->assertStringContainsString('no se encuentra activo', $resSunafil['message']);

        // 2. Cliente Retirado
        $this->cliente->update(['estado' => 'retirado']);
        $resRetirado = $sunatSync->syncCliente($this->cliente);
        $this->assertFalse($resRetirado['success']);

        // 3. Cliente en Papelera (Soft Deleted)
        $this->cliente->update(['estado' => 'activo']);
        $this->cliente->delete();

        $resTrashed = $sunatSync->syncCliente($this->cliente);
        $this->assertFalse($resTrashed['success']);
        $this->assertStringContainsString('papelera', $resTrashed['message']);

        $resTrashedSunafil = $sunafilSync->syncCliente($this->cliente);
        $this->assertFalse($resTrashedSunafil['success']);
        $this->assertStringContainsString('papelera', $resTrashedSunafil['message']);
    }

    public function test_sync_all_only_queries_active_non_trashed_clients(): void
    {
        // Cliente 1: Activo
        // Cliente 2: Suspendido
        Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CLIENTE SUSPENDIDO SAC',
            'ruc' => '20111222333',
            'regimen_tributario' => 'General',
            'estado' => 'suspendido',
        ]);

        // Cliente 3: En papelera
        $clienteTrash = Cliente::create([
            'estudio_id' => $this->estudio->id,
            'razon_social' => 'CLIENTE TRASH SAC',
            'ruc' => '20444555666',
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);
        $clienteTrash->delete();

        // Cliente::activos() solo debe arrojar 1 cliente
        $activos = Cliente::activos()->get();
        $this->assertCount(1, $activos);
        $this->assertEquals($this->cliente->id, $activos->first()->id);
    }
}
