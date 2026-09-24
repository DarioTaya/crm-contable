<?php

namespace App\Enums;

/**
 * Códigos oficiales y denominaciones de tributos de la SUNAT.
 */
enum TipoTributoSunat: string
{
    case Igv18 = '1011';
    case Igv105 = '1016';
    case RentaMype = '3121';
    case RentaEspecial = '3111';
    case RentaGeneral = '3031';
    case EsSalud = '5210';
    case Onp = '5310';
    case RentaCuarta = '3041';
    case RentaQuinta = '3051';
    case Afp = 'AFP';

    /**
     * Denominación oficial del tributo SUNAT.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::Igv18 => 'IGV Cuenta Propia 18%',
            self::Igv105 => 'IGV Restaurantes y Hoteles 10.5% (Ley 31556)',
            self::RentaMype => 'Renta Régimen MYPE Tributario 1%',
            self::RentaEspecial => 'Renta Régimen Especial (RER) 1.5%',
            self::RentaGeneral => 'Renta Régimen General / Coeficiente',
            self::EsSalud => 'EsSalud Seguro Regular (Ley 26790)',
            self::Onp => 'ONP Sistema Nacional de Pensiones (Ley 19990)',
            self::RentaCuarta => 'Renta 4ta Categoría (Trabajadores Independientes)',
            self::RentaQuinta => 'Renta 5ta Categoría (Planilla)',
            self::Afp => 'Aportes Previsionales AFP (Prima / Fondo / Comisión)',
        };
    }

    /**
     * Nombre corto para etiquetas compactas en tablas o badges.
     */
    public function nombreCorto(): string
    {
        return match ($this) {
            self::Igv18 => 'IGV 18%',
            self::Igv105 => 'IGV 10.5%',
            self::RentaMype => 'Renta MYPE 1%',
            self::RentaEspecial => 'Renta RER 1.5%',
            self::RentaGeneral => 'Renta General',
            self::EsSalud => 'EsSalud',
            self::Onp => 'ONP',
            self::RentaCuarta => 'Renta 4ta',
            self::RentaQuinta => 'Renta 5ta',
            self::Afp => 'AFP',
        };
    }

    /**
     * Etiqueta completa con código oficial para selector y reportes.
     */
    public function etiquetaCompleta(): string
    {
        return "{$this->value} - {$this->descripcion()}";
    }

    /**
     * Opciones completas indexadas por código SUNAT para poblar selectores.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = "{$caso->value} - {$caso->descripcion()}";
        }

        return $opciones;
    }

    /**
     * Resuelve el nombre del tributo dado un código o devuelve un valor por defecto.
     */
    public static function obtenerNombre(?string $codigo, string $default = 'Tributo SUNAT'): string
    {
        if (empty($codigo)) {
            return $default;
        }

        return self::tryFrom($codigo)?->descripcion() ?? $codigo;
    }

    /**
     * Resuelve el nombre corto dado un código.
     */
    public static function obtenerNombreCorto(?string $codigo, string $default = 'Tributo'): string
    {
        if (empty($codigo)) {
            return $default;
        }

        return self::tryFrom($codigo)?->nombreCorto() ?? $codigo;
    }
}
