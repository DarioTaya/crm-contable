<?php

namespace Tests\Unit;

use App\Support\IdentidadMarca;
use PHPUnit\Framework\TestCase;

class IdentidadMarcaTest extends TestCase
{
    public function test_la_marca_por_defecto_separa_la_barra_el_acento_y_el_papel(): void
    {
        $variables = IdentidadMarca::porDefecto()->variables();

        $this->assertSame('#0B132B', $variables['--color-sidebar']);
        $this->assertSame('#00C2CB', $variables['--color-brand-500']);
        $this->assertSame('#F4F6F8', $variables['--color-brand-50']);
        $this->assertSame('#F1F3F6', $variables['--color-canvas']);
        $this->assertNotSame('#00C2CB', $variables['--color-brand-600']);
        $this->assertGreaterThanOrEqual(4.5, IdentidadMarca::contraste($variables['--color-brand-600'], '#FFFFFF'));
    }

    public function test_cambiar_el_acento_no_tina_el_fondo_de_las_paginas(): void
    {
        $variables = (new IdentidadMarca('#10243F', '#C2410C'))->variables();

        $this->assertSame('#F4F6F8', $variables['--color-brand-50']);
        $this->assertSame('#E8EDF2', $variables['--color-brand-100']);
        $this->assertSame('#D5DDE6', $variables['--color-brand-200']);
        $this->assertSame('#F1F3F6', $variables['--color-canvas']);
        $this->assertSame('#C2410C', $variables['--color-brand-500']);
        $this->assertSame('#10243F', $variables['--color-sidebar']);
    }

    public function test_un_color_claro_no_puede_usarse_en_la_barra(): void
    {
        $problema = IdentidadMarca::problema('#00C2CB', '#0B132B');

        $this->assertSame(
            'El color de la barra debe ser oscuro para que el menú en blanco se lea con claridad.',
            $problema['color_base'] ?? null,
        );
    }

    public function test_un_acento_blanco_o_negro_se_rechaza(): void
    {
        $claro = IdentidadMarca::problema('#0B132B', '#FFFFFF');
        $oscuro = IdentidadMarca::problema('#0B132B', '#000000');

        $this->assertSame(
            'El acento está demasiado claro y desaparece sobre el fondo de las páginas.',
            $claro['color_acento'] ?? null,
        );
        $this->assertSame(
            'El acento está demasiado oscuro. Elige un color que se distinga de la barra.',
            $oscuro['color_acento'] ?? null,
        );
    }

    public function test_un_valor_que_no_es_hexadecimal_se_rechaza_y_no_entra_al_css(): void
    {
        $problema = IdentidadMarca::problema('#0B132B</style>', '#00C2CB');

        $this->assertSame(
            'El color de la barra debe ser un hexadecimal de 6 dígitos, por ejemplo #0B132B.',
            $problema['color_base'] ?? null,
        );
        $this->assertNull(IdentidadMarca::normalizar('#0B132B</style>'));
        $this->assertStringNotContainsString('<', IdentidadMarca::porDefecto()->bloqueCss());
    }

    public function test_una_configuracion_guardada_ilegible_vuelve_a_la_marca_por_defecto(): void
    {
        $marca = IdentidadMarca::desdeConfiguracion([
            'color_base' => '#FFFFFF',
            'color_acento' => '#00C2CB',
        ]);

        $this->assertSame(IdentidadMarca::BASE, $marca->base);
        $this->assertSame(IdentidadMarca::ACENTO, $marca->acento);
    }
}
