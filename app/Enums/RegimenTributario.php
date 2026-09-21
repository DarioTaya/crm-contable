<?php

namespace App\Enums;

/**
 * Regimenes tributarios vigentes de SUNAT.
 *
 * Los valores respaldados conservan las cadenas historicas ya presentes en la
 * base de datos. El nombre oficial y el codigo SUNAT viven en los metodos, de
 * modo que un cambio de nomenclatura no obliga a migrar datos.
 */
enum RegimenTributario: string
{
    case Rus = 'RUS';
    case Rer = 'RER';
    case Mype = 'MYPE';
    case General = 'General';

    /**
     * Nombre oficial completo segun SUNAT.
     */
    public function nombreOficial(): string
    {
        return match ($this) {
            self::Rus => 'Nuevo Régimen Único Simplificado',
            self::Rer => 'Régimen Especial de Renta',
            self::Mype => 'Régimen MYPE Tributario',
            self::General => 'Régimen General',
        };
    }

    /**
     * Sigla oficial empleada por SUNAT en sus formularios.
     */
    public function siglaSunat(): string
    {
        return match ($this) {
            self::Rus => 'NRUS',
            self::Rer => 'RER',
            self::Mype => 'RMT',
            self::General => 'RG',
        };
    }

    /**
     * Variante cromatica del sistema de diseno asociada al regimen.
     */
    public function variante(): string
    {
        return match ($this) {
            self::Mype => 'info',
            self::Rer => 'warning',
            self::Rus => 'success',
            self::General => 'purple',
        };
    }

    /**
     * Indica si el regimen obliga a presentar declaracion jurada anual de renta.
     */
    public function exigeDeclaracionAnual(): bool
    {
        return $this !== self::Rus;
    }

    /**
     * Indica si el regimen obliga a llevar libros electronicos (PLE / SIRE).
     */
    public function exigeLibrosElectronicos(): bool
    {
        return in_array($this, [self::Mype, self::General], true);
    }

    /**
     * Valores de los regimenes obligados a llevar libros electronicos, listos
     * para usarse en clausulas whereIn.
     *
     * @return array<int, string>
     */
    public static function valoresConLibrosElectronicos(): array
    {
        return array_values(array_map(
            fn (self $caso) => $caso->value,
            array_filter(self::cases(), fn (self $caso) => $caso->exigeLibrosElectronicos()),
        ));
    }

    /**
     * Opciones para poblar selectores de formulario.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return array_reduce(
            self::cases(),
            fn (array $acc, self $caso) => $acc + [$caso->value => $caso->nombreOficial()],
            [],
        );
    }
}
