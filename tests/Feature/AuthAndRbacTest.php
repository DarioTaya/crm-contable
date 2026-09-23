<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndRbacTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio1;

    protected Estudio $estudio2;

    protected User $admin;

    protected User $asistente;

    protected Cliente $clienteAsignado;

    protected Cliente $clienteNoAsignado;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio1 = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio 1', 'correo' => 'estudio1@test.com', 'activo' => true]
        );

        $this->estudio2 = Estudio::create([
            'nombre' => 'Estudio 2',
            'correo' => 'estudio2@test.com',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'estudio_id' => $this->estudio1->id,
            'name' => 'Admin Titular',
            'email' => 'admin@estudio.com',
            'password' => Hash::make('secret123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->asistente = User::create([
            'estudio_id' => $this->estudio1->id,
            'name' => 'Asistente Junior',
            'email' => 'asistente@estudio.com',
            'password' => Hash::make('secret123'),
            'rol' => 'asistente',
            'activo' => true,
        ]);

        $this->clienteAsignado = Cliente::create([
            'estudio_id' => $this->estudio1->id,
            'razon_social' => 'CLIENTE ASIGNADO SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'MYPE',
            'estado' => 'activo',
        ]);

        $this->clienteNoAsignado = Cliente::create([
            'estudio_id' => $this->estudio1->id,
            'razon_social' => 'CLIENTE PRIVADO SAC',
            'ruc' => '20222222222',
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);

        // Asignar únicamente el primer cliente al asistente
        $this->asistente->clientes()->attach($this->clienteAsignado->id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->withHeader('X-Test-Guest', '1')->get(route('dashboard'));
        $response->assertRedirect(route('login'));

        $responseClientes = $this->withHeader('X-Test-Guest', '1')->get(route('clientes.index'));
        $responseClientes->assertRedirect(route('login'));
    }

    public function test_login_successful_with_valid_credentials(): void
    {
        $response = $this->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_login_fails_with_invalid_password(): void
    {
        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $this->asistente->update(['activo' => false]);

        $response = $this->from(route('login'))->post(route('login.post'), [
            'email' => 'asistente@estudio.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $response = $this->actingAs($this->admin)->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_with_remember_stores_device_credentials_and_remember_token(): void
    {
        $response = $this->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $response->assertCookie((string) config('auth.device_remember.email_cookie'), 'admin@estudio.com');
        $response->assertCookie((string) config('auth.device_remember.secret_cookie'), 'secret123');
        $response->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertNotSame('', (string) $this->admin->fresh()->getRememberToken());
    }

    public function test_login_without_remember_does_not_store_device_credentials(): void
    {
        $response = $this->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->admin);
        $response->assertCookieMissing((string) config('auth.device_remember.email_cookie'));
        $response->assertCookieMissing((string) config('auth.device_remember.secret_cookie'));
        $response->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertSame('', (string) $this->admin->fresh()->getRememberToken());
    }

    public function test_login_form_prefills_remembered_device_credentials_after_logout(): void
    {
        $emailCookie = (string) config('auth.device_remember.email_cookie');
        $secretCookie = (string) config('auth.device_remember.secret_cookie');

        $this->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'secret123',
            'remember' => '1',
        ]);

        $this->withCookies([
            $emailCookie => 'admin@estudio.com',
            $secretCookie => 'secret123',
        ])->post(route('logout'));

        $response = $this->withHeader('X-Test-Guest', '1')->withCookies([
            $emailCookie => 'admin@estudio.com',
            $secretCookie => 'secret123',
        ])->get(route('login'));

        $response->assertOk();
        $response->assertSee('value="admin@estudio.com"', false);
        $response->assertSee('value="secret123"', false);
        $this->assertMatchesRegularExpression(
            '/id="remember"[^>]*\bchecked\b/',
            $response->getContent()
        );
    }

    public function test_login_without_remember_clears_previous_device_credentials(): void
    {
        $emailCookie = (string) config('auth.device_remember.email_cookie');
        $secretCookie = (string) config('auth.device_remember.secret_cookie');

        $response = $this->withCookies([
            $emailCookie => 'admin@estudio.com',
            $secretCookie => 'secret123',
        ])->post(route('login.post'), [
            'email' => 'admin@estudio.com',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertCookieExpired($emailCookie);
        $response->assertCookieExpired($secretCookie);
    }

    public function test_admin_can_access_ajustes_and_usuarios(): void
    {
        $responseAjustes = $this->actingAs($this->admin)->get(route('ajustes.index'));
        $responseAjustes->assertStatus(200);

        $responseUsuarios = $this->actingAs($this->admin)->get(route('usuarios.index'));
        $responseUsuarios->assertStatus(200);
        $responseUsuarios->assertSee('Admin Titular');
        $responseUsuarios->assertSee('Asistente Junior');
    }

    public function test_assistant_cannot_access_ajustes_or_usuarios(): void
    {
        $responseAjustes = $this->actingAs($this->asistente)->get(route('ajustes.index'));
        $responseAjustes->assertStatus(403);

        $responseUsuarios = $this->actingAs($this->asistente)->get(route('usuarios.index'));
        $responseUsuarios->assertStatus(403);
    }

    public function test_assistant_cannot_delete_clients(): void
    {
        $response = $this->actingAs($this->asistente)->delete(route('clientes.destroy', $this->clienteAsignado));
        $response->assertStatus(403);
        $this->assertDatabaseHas('clientes', ['id' => $this->clienteAsignado->id]);
    }

    public function test_admin_can_delete_clients(): void
    {
        $response = $this->actingAs($this->admin)->delete(route('clientes.destroy', $this->clienteNoAsignado));
        $response->assertRedirect(route('clientes.index'));
        $this->assertSoftDeleted('clientes', ['id' => $this->clienteNoAsignado->id]);
    }

    public function test_assistant_only_sees_assigned_clients_in_index(): void
    {
        $response = $this->actingAs($this->asistente)->get(route('clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('CLIENTE ASIGNADO SAC');
        $response->assertDontSee('CLIENTE PRIVADO SAC');
    }

    public function test_admin_sees_all_clients_in_index(): void
    {
        $response = $this->actingAs($this->admin)->get(route('clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('CLIENTE ASIGNADO SAC');
        $response->assertSee('CLIENTE PRIVADO SAC');
    }

    public function test_assistant_cannot_view_unassigned_client_dossier(): void
    {
        $response = $this->actingAs($this->asistente)->get(route('clientes.show', $this->clienteNoAsignado));
        $response->assertStatus(403);
    }

    public function test_assistant_can_view_assigned_client_dossier(): void
    {
        $response = $this->actingAs($this->asistente)->get(route('clientes.show', $this->clienteAsignado));
        $response->assertStatus(200);
        $response->assertSee('CLIENTE ASIGNADO SAC');
        // El botón eliminar no debe figurar en el expediente del asistente
        $response->assertDontSee('Eliminar Cliente');
    }

    public function test_admin_creates_assistant_and_assigns_clients(): void
    {
        $response = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name' => 'Carlos Asistente',
            'email' => 'carlos@estudio.com',
            'telefono' => '999888777',
            'password' => 'password123',
            'rol' => 'asistente',
            'clientes' => [$this->clienteNoAsignado->id],
        ]);

        $response->assertRedirect(route('usuarios.index'));
        $this->assertDatabaseHas('users', ['email' => 'carlos@estudio.com', 'rol' => 'asistente']);

        $nuevoUsuario = User::where('email', 'carlos@estudio.com')->first();
        $this->assertTrue($nuevoUsuario->clientes->contains($this->clienteNoAsignado));
    }

    public function test_estudio_scope_isolates_data_between_different_studios(): void
    {
        // Crear cliente en Estudio 2
        $clienteEstudio2 = Cliente::create([
            'estudio_id' => $this->estudio2->id,
            'razon_social' => 'OTRO ESTUDIO CLIENTE SAC',
            'ruc' => '20333333333',
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);

        // Usuario en Estudio 2
        $adminEstudio2 = User::create([
            'estudio_id' => $this->estudio2->id,
            'name' => 'Admin Estudio 2',
            'email' => 'admin2@estudio.com',
            'password' => Hash::make('secret123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        // Al loguearse el Admin de Estudio 1, no ve clientes de Estudio 2
        $this->actingAs($this->admin);
        $this->assertCount(2, Cliente::all());
        $this->assertFalse(Cliente::where('ruc', '20333333333')->exists());

        // Al loguearse el Admin de Estudio 2, solo ve clientes de Estudio 2
        $this->actingAs($adminEstudio2);
        $this->assertCount(1, Cliente::all());
        $this->assertTrue(Cliente::where('ruc', '20333333333')->exists());
    }
}
