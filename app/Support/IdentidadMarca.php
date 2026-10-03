<?php

namespace App\Support;

use App\Models\Estudio;

/**
 * Identidad visual del estudio.
 *
 * El color base pinta la barra lateral. El acento pinta botones, foco y casillas.
 * Los fondos de trabajo (50, 100 y 200) se quedan en gris claro: un acento claro
 * no puede teñir las páginas, porque ese lavado se lee como una mancha.
 */
final class IdentidadMarca
{
    public const BASE = '#0B132B';

    public const ACENTO = '#00C2CB';

    public const PAPEL = '#F1F3F6';

    /**
     * @var array<string, string>
     */
    private const NEUTROS = [
        '50' => '#F4F6F8',
        '100' => '#E8EDF2',
        '200' => '#D5DDE6',
    ];

    public function __construct(
        public readonly string $base,
        public readonly string $acento,
    ) {}

    public static function porDefecto(): self
    {
        return new self(self::BASE, self::ACENTO);
    }

    public static function desdeEstudio(?Estudio $estudio): self
    {
        return self::desdeConfiguracion($estudio?->configuracion);
    }

    /**
     * @param  array<string, mixed>|null  $configuracion
     */
    public static function desdeConfiguracion(?array $configuracion): self
    {
        $base = self::normalizar(is_string($configuracion['color_base'] ?? null) ? $configuracion['color_base'] : '');
        $acento = self::normalizar(is_string($configuracion['color_acento'] ?? null) ? $configuracion['color_acento'] : '');

        if ($base === null || $acento === null || self::problema($base, $acento) !== null) {
            return self::porDefecto();
        }

        return new self($base, $acento);
    }

    /**
     * @return array<string, string>|null
     */
    public static function problema(string $base, string $acento): ?array
    {
        $base = self::normalizar($base);
        $acento = self::normalizar($acento);

        if ($base === null) {
            return ['color_base' => 'El color de la barra debe ser un hexadecimal de 6 dígitos, por ejemplo #0B132B.'];
        }

        if ($acento === null) {
            return ['color_acento' => 'El color de acento debe ser un hexadecimal de 6 dígitos, por ejemplo #00C2CB.'];
        }

        if (self::contraste($base, '#FFFFFF') < 4.5) {
            return ['color_base' => 'El color de la barra debe ser oscuro para que el menú en blanco se lea con claridad.'];
        }

        if (self::contraste($acento, '#FFFFFF') < 1.25) {
            return ['color_acento' => 'El acento está demasiado claro y desaparece sobre el fondo de las páginas.'];
        }

        if (self::contraste($acento, '#000000') < 1.45) {
            return ['color_acento' => 'El acento está demasiado oscuro. Elige un color que se distinga de la barra.'];
        }

        return null;
    }

    public static function normalizar(string $color): ?string
    {
        $color = strtoupper(trim($color));

        if (preg_match('/^#[0-9A-F]{6}$/', $color) !== 1) {
            return null;
        }

        return $color;
    }

    public static function contraste(string $primero, string $segundo): float
    {
        $claro = max(self::luminancia($primero), self::luminancia($segundo));
        $oscuro = min(self::luminancia($primero), self::luminancia($segundo));

        return ($claro + 0.05) / ($oscuro + 0.05);
    }

    /**
     * @return array<string, string>
     */
    public function variables(): array
    {
        $boton = $this->tonoLegible($this->acento, '#FFFFFF', 4.5);
        $botonHover = $this->mezclar($boton, '#000000', 0.12);

        return [
            '--color-brand-50' => self::NEUTROS['50'],
            '--color-brand-100' => self::NEUTROS['100'],
            '--color-brand-200' => self::NEUTROS['200'],
            '--color-brand-300' => $this->mezclar($this->acento, '#FFFFFF', 0.55),
            '--color-brand-400' => $this->mezclar($this->acento, '#FFFFFF', 0.22),
            '--color-brand-500' => $this->acento,
            '--color-brand-600' => $boton,
            '--color-brand-700' => $botonHover,
            '--color-brand-800' => $this->base,
            '--color-brand-900' => $this->mezclar($this->base, '#000000', 0.22),
            '--color-brand-950' => $this->mezclar($this->base, '#000000', 0.45),
            '--color-sidebar' => $this->base,
            '--color-sidebar-hover' => $this->mezclar($this->base, '#FFFFFF', 0.12),
            '--color-sidebar-active' => $this->mezclar($this->base, '#FFFFFF', 0.12),
            '--color-sidebar-border' => $this->mezclar($this->base, '#FFFFFF', 0.16),
            '--color-canvas' => self::PAPEL,
            '--color-accent' => $this->acento,
            '--color-ink' => $this->base,
        ];
    }

    public function bloqueCss(): string
    {
        $lineas = [':root {'];

        foreach ($this->variables() as $nombre => $valor) {
            if (preg_match('/^--color-[a-z0-9-]+$/', $nombre) !== 1 || self::normalizar($valor) === null) {
                continue;
            }

            $lineas[] = '    '.$nombre.': '.$valor.';';
        }

        $lineas[] = '}';

        return implode("\n", $lineas);
    }

    private function tonoLegible(string $color, string $sobre, float $minimo): string
    {
        $actual = $color;

        for ($paso = 0; $paso < 24; $paso++) {
            if (self::contraste($actual, $sobre) >= $minimo) {
                return $actual;
            }

            $actual = $this->mezclar($actual, '#000000', 0.08);
        }

        return $actual;
    }

    private function mezclar(string $origen, string $destino, float $pesoDestino): string
    {
        $pesoDestino = max(0, min(1, $pesoDestino));
        $pesoOrigen = 1 - $pesoDestino;
        [$rojoOrigen, $verdeOrigen, $azulOrigen] = self::rgb($origen);
        [$rojoDestino, $verdeDestino, $azulDestino] = self::rgb($destino);

        return sprintf(
            '#%02X%02X%02X',
            (int) round(($rojoOrigen * $pesoOrigen) + ($rojoDestino * $pesoDestino)),
            (int) round(($verdeOrigen * $pesoOrigen) + ($verdeDestino * $pesoDestino)),
            (int) round(($azulOrigen * $pesoOrigen) + ($azulDestino * $pesoDestino)),
        );
    }

    private static function luminancia(string $hex): float
    {
        [$rojo, $verde, $azul] = self::rgb($hex);

        return (0.2126 * self::canal($rojo))
            + (0.7152 * self::canal($verde))
            + (0.0722 * self::canal($azul));
    }

    private static function canal(int $valor): float
    {
        $canal = $valor / 255;

        if ($canal <= 0.04045) {
            return $canal / 12.92;
        }

        return (($canal + 0.055) / 1.055) ** 2.4;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private static function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}
