<?php

namespace App\Enums;

/**
 * Regímenes tributarios vigentes de SUNAT.
 *
 * Estandarización oficial:
 * - RUS: Nuevo Régimen Único Simplificado
 * - RER: Régimen Especial de Renta
 * - RMT: Régimen MYPE Tributario
 * - RG:  Régimen General
 */
enum RegimenTributario: string
{
    case Rus = 'RUS';
    case Rer = 'RER';
    case Rmt = 'RMT';
    case Rg = 'RG';

    /**
     * Nombre oficial completo según SUNAT.
     */
    public function nombreOficial(): string
    {
        return match ($this) {
            self::Rus => 'Nuevo Régimen Único Simplificado',
            self::Rer => 'Régimen Especial de Renta',
            self::Rmt => 'Régimen MYPE Tributario',
            self::Rg => 'Régimen General',
        };
    }

    /**
     * Sigla oficial empleada por SUNAT en sus formularios y constancias.
     */
    public function siglaSunat(): string
    {
        return match ($this) {
            self::Rus => 'NRUS',
            self::Rer => 'RER',
            self::Rmt => 'RMT',
            self::Rg => 'RG',
        };
    }

    /**
     * Etiqueta completa estandarizada para formularios, badges y reportes:
     * Código - Nombre Oficial (ej. "RMT - Régimen MYPE Tributario").
     */
    public function etiquetaCompleta(): string
    {
        return "{$this->value} - {$this->nombreOficial()}";
    }

    /**
     * Variante cromática del sistema de diseño asociada al régimen.
     */
    public function variante(): string
    {
        return match ($this) {
            self::Rmt => 'info',
            self::Rer => 'warning',
            self::Rus => 'success',
            self::Rg => 'purple',
        };
    }

    /**
     * Indica si el régimen obliga a presentar declaración jurada anual de renta.
     */
    public function exigeDeclaracionAnual(): bool
    {
        return $this !== self::Rus;
    }

    /**
     * Indica si el régimen obliga a llevar libros electrónicos (PLE / SIRE / Balances).
     */
    public function exigeLibrosElectronicos(): bool
    {
        return in_array($this, [self::Rmt, self::Rg], true);
    }

    /**
     * Valores de los regímenes obligados a llevar libros electrónicos, listos
     * para usarse en cláusulas whereIn.
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
     * Opciones para poblar selectores de formulario en formato exacto:
     * RUS - Nuevo Régimen Único Simplificado
     * RER - Régimen Especial de Renta
     * RMT - Régimen MYPE Tributario
     * RG - Régimen General
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        return [
            self::Rus->value => self::Rus->etiquetaCompleta(),
            self::Rer->value => self::Rer->etiquetaCompleta(),
            self::Rmt->value => self::Rmt->etiquetaCompleta(),
            self::Rg->value => self::Rg->etiquetaCompleta(),
        ];
    }

    /**
     * Resuelve de forma tolerante cadenas históricas ('MYPE', 'General') o variaciones
     * al caso oficial correspondiente del Enum.
     */
    public static function tryFromNormalizado(?string $valor): ?self
    {
        if ($valor === null) {
            return null;
        }

        $norm = strtoupper(trim($valor));
        if ($norm === '') {
            return null;
        }

        return match ($norm) {
            'RUS', 'NRUS', 'NUEVO RUS', 'NUEVO RÉGIMEN ÚNICO SIMPLIFICADO', 'NUEVO REGIMEN UNICO SIMPLIFICADO' => self::Rus,
            'RER', 'RÉGIMEN ESPECIAL', 'REGIMEN ESPECIAL', 'RÉGIMEN ESPECIAL DE RENTA', 'REGIMEN ESPECIAL DE RENTA' => self::Rer,
            'RMT', 'MYPE', 'RÉGIMEN MYPE', 'REGIMEN MYPE', 'RÉGIMEN MYPE TRIBUTARIO', 'REGIMEN MYPE TRIBUTARIO' => self::Rmt,
            'RG', 'GENERAL', 'RÉGIMEN GENERAL', 'REGIMEN GENERAL' => self::Rg,
            default => self::tryFrom($norm),
        };
    }
}
