<?php

namespace App\Services\ControlImpuestos;

/**
 * Importes tal como los imprime SUNAT: 1,250.00, 1.250,00 o S/. 1,128.
 */
final class MontoTributario
{
    /**
     * Importe con separador de miles o con céntimos. No toma un pedazo de 1,128 como 1.12.
     */
    public static function regex(): string
    {
        return '(?:\d{1,3}(?:,\d{3})+(?:\.\d{2})?|\d{1,3}(?:\.\d{3})+(?:,\d{2})?|\d+[.,]\d{2})(?!\d)';
    }

    /**
     * Importe de una constancia: el detalle trae céntimos y la constancia trae S/. 157 o S/. 1,128.
     */
    public static function regexTributo(): string
    {
        return '(?:S\\/\\.?\\s*(?:'.self::regex().'|\\d+)|'.self::regex().')';
    }

    public static function parse(string $raw): ?float
    {
        $raw = trim($raw);
        $negativo = str_starts_with($raw, '-') || str_starts_with($raw, '(');
        $raw = preg_replace('/^S\\/\\.?\\s*/', '', ltrim($raw, "()- \t")) ?? $raw;
        $raw = str_replace([' ', '(', ')'], '', $raw);
        if ($raw === '' || ! preg_match('/\d/', $raw)) {
            return null;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+,\d{2}$/', $raw)) {
            $valor = (float) str_replace(',', '.', str_replace('.', '', $raw));
        } elseif (preg_match('/^\d{1,3}(,\d{3})+\.\d{2}$/', $raw)) {
            $valor = (float) str_replace(',', '', $raw);
        } elseif (preg_match('/^\d{1,3}(,\d{3})+$/', $raw)) {
            $valor = (float) str_replace(',', '', $raw);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $raw)) {
            $valor = (float) str_replace('.', '', $raw);
        } elseif (preg_match('/^\d+,\d{2}$/', $raw)) {
            $valor = (float) str_replace(',', '.', $raw);
        } elseif (preg_match('/^\d+\.\d{2}$/', $raw)) {
            $valor = (float) $raw;
        } elseif (preg_match('/^\d+$/', $raw)) {
            $valor = (float) $raw;
        } else {
            return null;
        }

        if ($valor > 999999999.99) {
            return null;
        }

        return $negativo ? round($valor * -1, 2) : round($valor, 2);
    }

    public static function iguales(float $a, float $b, float $tolerancia = 0.05): bool
    {
        return abs($a - $b) <= $tolerancia;
    }
}
