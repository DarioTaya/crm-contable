<?php

namespace Tests\Feature;

use App\Models\CronogramaSunat;
use App\Models\Estudio;
use App\Models\QuickLink;
use App\Models\User;
use Carbon\Carbon;
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    public function test_topbar_cronograma_muestra_pdt_621_y_sire_desde_la_base(): void
    {
        Carbon::setTestNow('2026-10-02 09:00:00');

        CronogramaSunat::create([
            'anio' => 2026,
            'tipo' => 'pdt_621',
            'mes' => 1,
            'periodo' => '01-2026',
            'digito_0' => '2026-02-16',
            'digito_1' => '2026-02-17',
            'digito_2_3' => '2026-02-18',
            'digito_4_5' => '2026-02-19',
            'digito_6_7' => '2026-02-20',
            'digito_8_9' => '2026-02-23',
            'buenos_contribuyentes' => '2026-02-24',
        ]);
        CronogramaSunat::create([
            'anio' => 2026,
            'tipo' => 'pdt_621',
            'mes' => 9,
            'periodo' => '09-2026',
            'digito_0' => '2026-10-16',
        ]);
        CronogramaSunat::create([
            'anio' => 2026,
            'tipo' => 'sire',
            'mes' => 1,
            'periodo' => '01-2026',
            'digito_0' => '2026-02-13',
            'buenos_contribuyentes' => '2026-02-23',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        CronogramaSunat::create([
            'anio' => 2026,
            'tipo' => 'balance_mensual',
            'mes' => 1,
            'periodo' => '01-2026',
            'fecha_vencimiento_general' => '2026-04-30',
        ]);
        CronogramaSunat::create([
            'anio' => 2026,
            'tipo' => 'balance_anual',
            'mes' => null,
            'periodo' => 'anual-2026',
            'digito_0' => '2027-03-24',
            'buenos_contribuyentes' => '2027-04-05',
        ]);

        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertSeeInOrder([
            'Consultas Rápidas',
            'title="Cronograma SUNAT"',
            'Cuenta de Carlos Administrador',
        ], false);
        $response->assertSeeInOrder([
            'SIRE',
            'PDT 621',
            'Balances',
            'Rta. 3ra',
        ]);
        $response->assertSee("tipo: 'sire'", false);
        $response->assertSee('Cronograma SUNAT 2026');
        $response->assertSee('Último dígito del RUC');
        $response->assertSee('Fecha límite');
        $response->assertSee('Ejercicio 2026');
        $response->assertSee('16 Feb');
        $response->assertSee('13 Feb');
        $response->assertSee('30 Abr');
        $response->assertSee('24 Mar');
        $response->assertSee('Período en liquidación', false);
        $response->assertSee('Setiembre');
    }
}
