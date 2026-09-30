<?php

namespace Tests\Feature;

use App\Models\Estudio;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::firstOrCreate(
            ['id' => 1],
            [
                'nombre' => 'DeContador Estudio',
                'correo' => 'contacto@decontador.pe',
                'activo' => true,
            ]
        );

        $this->user = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Contador Principal',
            'email' => 'contador@decontador.pe',
            'password' => Hash::make('ClaveAntigua123!'),
            'password_plain' => 'ClaveAntigua123!',
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_forgot_password_page_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));

        $response->assertStatus(200);
        $response->assertSee('¿Olvidaste tu contraseña?');
        $response->assertSee('Enviar enlace de recuperación');
    }

    public function test_reset_link_can_be_requested_for_existing_active_user(): void
    {
        Notification::fake();

        $response = $this->post(route('password.email'), [
            'email' => 'contador@decontador.pe',
        ]);

        $response->assertSessionHas('status');
        Notification::assertSentTo($this->user, ResetPasswordNotification::class);
    }

    public function test_reset_link_cannot_be_requested_for_non_existent_email(): void
    {
        Notification::fake();

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'noexiste@estudio.com',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_reset_link_cannot_be_requested_for_inactive_user(): void
    {
        Notification::fake();

        $this->user->update(['activo' => false]);

        $response = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'contador@decontador.pe',
        ]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHasErrors('email');
        Notification::assertNothingSent();
    }

    public function test_reset_password_page_can_be_rendered_with_token(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->get(route('password.reset', [
            'token' => $token,
            'email' => 'contador@decontador.pe',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Restablecer tu contraseña');
        $response->assertSee('value="contador@decontador.pe"', false);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'contador@decontador.pe',
            'password' => 'NuevaClaveContable2026!',
            'password_confirmation' => 'NuevaClaveContable2026!',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');

        $this->user->refresh();
        $this->assertTrue(Hash::check('NuevaClaveContable2026!', $this->user->password));
        $this->assertSame('NuevaClaveContable2026!', $this->user->password_plain);
    }

    public function test_user_can_login_with_newly_reset_password(): void
    {
        $token = Password::broker()->createToken($this->user);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'contador@decontador.pe',
            'password' => 'NuevaClaveContable2026!',
            'password_confirmation' => 'NuevaClaveContable2026!',
        ]);

        $loginResponse = $this->post(route('login.post'), [
            'email' => 'contador@decontador.pe',
            'password' => 'NuevaClaveContable2026!',
        ]);

        $loginResponse->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $response = $this->post(route('password.update'), [
            'token' => 'token-invalido-o-expirado',
            'email' => 'contador@decontador.pe',
            'password' => 'NuevaClave123!',
            'password_confirmation' => 'NuevaClave123!',
        ]);

        $response->assertSessionHasErrors('email');
        $this->user->refresh();
        $this->assertTrue(Hash::check('ClaveAntigua123!', $this->user->password));
    }

    public function test_password_reset_validation_requires_matching_confirmation(): void
    {
        $token = Password::broker()->createToken($this->user);

        $response = $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'contador@decontador.pe',
            'password' => 'NuevaClave123!',
            'password_confirmation' => 'ClaveDiferente123!',
        ]);

        $response->assertSessionHasErrors('password');
        $this->user->refresh();
        $this->assertTrue(Hash::check('ClaveAntigua123!', $this->user->password));
    }

    public function test_authenticated_user_is_redirected_away_from_password_reset_routes(): void
    {
        $this->actingAs($this->user);

        $this->get(route('password.request'))->assertRedirect(route('dashboard'));

        $this->get(route('password.reset', ['token' => 'cualquier-token']))->assertRedirect(route('dashboard'));
    }
}
