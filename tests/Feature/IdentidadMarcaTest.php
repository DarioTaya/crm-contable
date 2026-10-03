<?php

namespace Tests\Feature;

use App\Models\Estudio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IdentidadMarcaTest extends TestCase
{
    use RefreshDatabase;

    private Estudio $estudio;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->estudio = Estudio::create([
            'nombre' => 'Estudio Demo SAC',
            'activo' => true,
        ]);

        $this->admin = User::create([
            'estudio_id' => $this->estudio->id,
            'name' => 'Carlos Administrador',
            'email' => 'admin@estudio.pe',
            'password' => Hash::make('password123'),
            'rol' => 'admin',
            'activo' => true,
        ]);
    }

    public function test_el_administrador_guarda_la_marca_y_las_paginas_siguen_en_gris(): void
    {
        $respuesta = $this->actingAs($this->admin)->postJson(route('ajustes.update'), $this->payload([
            'color_base' => '#10243F',
            'color_acento' => '#C2410C',
        ]));

        $respuesta->assertOk();
        $respuesta->assertJsonPath('marca.--color-sidebar', '#10243F');
        $respuesta->assertJsonPath('marca.--color-brand-500', '#C2410C');
        $respuesta->assertJsonPath('marca.--color-brand-50', '#F4F6F8');

        $this->estudio->refresh();
        $this->assertSame('#10243F', $this->estudio->configuracion['color_base']);
        $this->assertSame('#C2410C', $this->estudio->configuracion['color_acento']);

        $pagina = $this->actingAs($this->admin)->get(route('dashboard'));
        $pagina->assertOk();
        $pagina->assertSee('--color-sidebar: #10243F', false);
        $pagina->assertSee('--color-brand-500: #C2410C', false);
        $pagina->assertSee('--color-brand-50: #F4F6F8', false);
        $pagina->assertSee('--color-canvas: #F1F3F6', false);

        $ajustes = $this->actingAs($this->admin)->get(route('ajustes.index'));
        $ajustes->assertOk();
        $ajustes->assertSee('Colores de la interfaz');
        $ajustes->assertSee('#10243F', false);
        $ajustes->assertSee('#C2410C', false);
    }

    public function test_la_previsualizacion_devuelve_el_papel_neutro_y_el_acento_elegido(): void
    {
        $respuesta = $this->actingAs($this->admin)->getJson(route('ajustes.marca', [
            'color_base' => '#10243F',
            'color_acento' => '#C2410C',
        ]));

        $respuesta->assertOk();
        $respuesta->assertJsonPath('variables.--color-sidebar', '#10243F');
        $respuesta->assertJsonPath('variables.--color-brand-500', '#C2410C');
        $respuesta->assertJsonPath('variables.--color-brand-50', '#F4F6F8');
    }

    public function test_un_color_claro_de_barra_responde_422(): void
    {
        $respuesta = $this->actingAs($this->admin)->postJson(route('ajustes.update'), $this->payload([
            'color_base' => '#00C2CB',
            'color_acento' => '#C2410C',
        ]));

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonPath(
            'errors.color_base.0',
            'El color de la barra debe ser oscuro para que el menú en blanco se lea con claridad.',
        );
    }

    public function test_un_color_mal_formado_responde_422(): void
    {
        $respuesta = $this->actingAs($this->admin)->postJson(route('ajustes.update'), $this->payload([
            'color_base' => '#0B132B</style>',
            'color_acento' => '#00C2CB',
        ]));

        $respuesta->assertUnprocessable();
        $respuesta->assertJsonPath(
            'errors.color_base.0',
            'El color de la barra debe ser un hexadecimal de 6 dígitos, por ejemplo #0B132B.',
        );
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function payload(array $extra): array
    {
        return array_merge([
            'razon_social' => 'Estudio Demo SAC',
            'ruc' => '20123456789',
            'representante' => 'Carlos Administrador',
            'correo_corporativo' => 'admin@estudio.pe',
            'telefono_whatsapp' => '912345678',
            'frecuencia_cron_horas' => 8,
            'notificar_whatsapp' => '0',
            'nivel_alerta_whatsapp' => 'Solo Altas',
        ], $extra);
    }
}
