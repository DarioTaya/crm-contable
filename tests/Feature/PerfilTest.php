<?php

namespace Tests\Feature;

use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerfilTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Test', 'activo' => true]
        );

        $this->user = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Sebastian Taya',
            'email' => 'sebastian@estudio.pe',
            'password' => Hash::make('Password123!'),
            'rol' => 'admin',
            'telefono' => '987654321',
            'activo' => true,
        ]);
    }

    public function test_guest_is_redirected_to_login_when_accessing_perfil(): void
    {
        $response = $this->withHeader('X-Test-Guest', '1')->get(route('perfil.edit'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_profile_page(): void
    {
        $response = $this->actingAs($this->user)->get(route('perfil.edit'));

        $response->assertStatus(200);
        $response->assertSee('Mi Perfil');
        $response->assertSee('sebastian@estudio.pe');
        $response->assertSee('Sebastian Taya');
    }

    public function test_user_can_update_personal_information(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'Sebastian Taya Yucra',
            'email' => 'sebastian.actualizado@estudio.pe',
            'telefono' => '999888777',
        ]);

        $response->assertRedirect(route('perfil.edit'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertSame('Sebastian Taya Yucra', $this->user->name);
        $this->assertSame('sebastian.actualizado@estudio.pe', $this->user->email);
        $this->assertSame('999888777', $this->user->telefono);
    }

    public function test_profile_update_validates_email_uniqueness_ignoring_self(): void
    {
        $otherUser = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Otro Usuario',
            'email' => 'otro@estudio.pe',
            'password' => Hash::make('secret'),
            'rol' => 'asistente',
            'activo' => true,
        ]);

        // Attempting to take another user's email should fail
        $response = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'Sebastian Taya',
            'email' => 'otro@estudio.pe',
        ]);

        $response->assertSessionHasErrors(['email']);

        // Keeping own email should succeed
        $responseSelf = $this->actingAs($this->user)->put(route('perfil.update'), [
            'name' => 'Sebastian Taya Modificado',
            'email' => 'sebastian@estudio.pe',
        ]);

        $responseSelf->assertSessionHasNoErrors();
    }

    public function test_user_can_update_password_with_correct_current_password(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'Password123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ]);

        $response->assertRedirect(route('perfil.edit'));
        $response->assertSessionHas('success');

        $this->user->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword456!', $this->user->password));
    }

    public function test_password_update_fails_with_invalid_current_password(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'WrongPassword!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'NewSecurePassword456!',
        ]);

        $response->assertSessionHasErrors(['current_password']);

        $this->user->refresh();
        $this->assertTrue(Hash::check('Password123!', $this->user->password));
    }

    public function test_password_update_fails_when_confirmation_mismatches(): void
    {
        $response = $this->actingAs($this->user)->put(route('perfil.password'), [
            'current_password' => 'Password123!',
            'password' => 'NewSecurePassword456!',
            'password_confirmation' => 'DifferentPassword!',
        ]);

        $response->assertSessionHasErrors(['password']);
    }
}
