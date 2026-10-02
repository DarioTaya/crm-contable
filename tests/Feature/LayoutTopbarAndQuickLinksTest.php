<?php

namespace Tests\Feature;

use App\Models\Estudio;
use App\Models\QuickLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LayoutTopbarAndQuickLinksTest extends TestCase
{
    use RefreshDatabase;

    protected Estudio $estudio;

    protected User $admin;

    protected User $asistente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::firstOrCreate(
            ['id' => 1],
            ['nombre' => 'Estudio Demo SAC', 'activo' => true]
        );

        $this->admin = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Carlos Administrador',
            'email' => 'admin@estudio.pe',
            'password' => Hash::make('password123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $this->asistente = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Ana Asistente',
            'email' => 'ana@estudio.pe',
            'password' => Hash::make('password123'),
            'rol' => 'asistente',
            'activo' => true,
        ]);
    }

    public function test_topbar_renders_consultas_rapidas_menu_with_official_links_and_db_links(): void
    {
        $link = QuickLink::create([
            'estudio_id' => $this->estudio->id,
            'label' => 'SUNAT - Clave SOL Oficial',
            'url' => 'https://api-seguridad.sunat.gob.pe/',
            'orden' => 1,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Consultas Rápidas');
        $response->assertSee('Enlaces oficiales');
        $response->assertSee('SUNAT - Clave SOL Oficial');
        $response->assertSee('https://api-seguridad.sunat.gob.pe/');
        $response->assertSee('target="_blank"', false);
    }

    public function test_sidebar_user_footer_is_removed(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertDontSee('sidebar-profile-link');
        $response->assertDontSee('sidebar-footer');
    }

    public function test_topbar_renders_premium_user_avatar_with_role_badge_and_link_to_profile(): void
    {
        // 1. Administrador (Crown / Admin role indicator)
        $responseAdmin = $this->actingAs($this->admin)->get(route('dashboard'));
        $responseAdmin->assertOk();
        $responseAdmin->assertSee(route('perfil.edit'));
        $responseAdmin->assertSee('CA'); // Iniciales de Carlos Administrador
        $responseAdmin->assertSee('👑'); // Badge de Administrador

        // 2. Asistente (Briefcase / Asistente role indicator)
        $responseAsistente = $this->actingAs($this->asistente)->get(route('dashboard'));
        $responseAsistente->assertOk();
        $responseAsistente->assertSee(route('perfil.edit'));
        $responseAsistente->assertSee('AA'); // Iniciales de Ana Asistente
        $responseAsistente->assertSee('💼'); // Badge de Asistente
    }

    public function test_admin_can_view_quick_links_crud_in_ajustes(): void
    {
        $link = QuickLink::create([
            'estudio_id' => $this->estudio->id,
            'label' => 'Portal Tributario SBS',
            'url' => 'https://www.sbs.gob.pe/',
            'orden' => 1,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('ajustes.index'));

        $response->assertOk();
        $response->assertSee('Consultas Rápidas');
        $response->assertSee('Portal Tributario SBS');
        $response->assertSee('https://www.sbs.gob.pe/');
    }

    public function test_admin_can_create_quick_link_via_controller(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('quick-links.store'), [
            'label' => 'SUNAFIL Casilla',
            'url' => 'https://casillaelectronica.sunafil.gob.pe/',
            'orden' => 5,
            'activo' => true,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('quick_links', [
            'label' => 'SUNAFIL Casilla',
            'url' => 'https://casillaelectronica.sunafil.gob.pe/',
            'orden' => 5,
            'activo' => true,
        ]);
    }

    public function test_admin_can_update_quick_link(): void
    {
        $link = QuickLink::create([
            'estudio_id' => $this->estudio->id,
            'label' => 'Enlace Antiguo',
            'url' => 'https://old.gob.pe/',
            'orden' => 1,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson(route('quick-links.update', $link), [
            'label' => 'Enlace Nuevo',
            'url' => 'https://new.gob.pe/',
            'orden' => 2,
            'activo' => false,
        ]);

        $response->assertOk();
        $link->refresh();
        $this->assertSame('Enlace Nuevo', $link->label);
        $this->assertSame('https://new.gob.pe/', $link->url);
        $this->assertSame(2, $link->orden);
        $this->assertFalse($link->activo);
    }

    public function test_admin_can_delete_quick_link(): void
    {
        $link = QuickLink::create([
            'estudio_id' => $this->estudio->id,
            'label' => 'Para Eliminar',
            'url' => 'https://delete.gob.pe/',
            'orden' => 1,
            'activo' => true,
        ]);

        $response = $this->actingAs($this->admin)->deleteJson(route('quick-links.destroy', $link));

        $response->assertOk();
        $this->assertDatabaseMissing('quick_links', ['id' => $link->id]);
    }

    public function test_admin_can_reorder_quick_links(): void
    {
        $l1 = QuickLink::create(['estudio_id' => $this->estudio->id, 'label' => 'L1', 'url' => 'https://l1.pe', 'orden' => 1]);
        $l2 = QuickLink::create(['estudio_id' => $this->estudio->id, 'label' => 'L2', 'url' => 'https://l2.pe', 'orden' => 2]);

        $response = $this->actingAs($this->admin)->postJson(route('quick-links.reorder'), [
            'ids' => [$l2->id, $l1->id],
        ]);

        $response->assertOk();
        $l1->refresh();
        $l2->refresh();

        $this->assertSame(2, $l1->orden);
        $this->assertSame(1, $l2->orden);
    }

    public function test_quick_link_requires_valid_url(): void
    {
        $response = $this->actingAs($this->admin)->postJson(route('quick-links.store'), [
            'label' => 'Invalido',
            'url' => 'not-a-valid-url',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['url']);
    }
}
