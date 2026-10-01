<?php

namespace App\Services\Clientes;

/**
 * Reglas públicas del RUC peruano: prefijo tributario y dígito verificador
 * módulo 11. Es el mismo criterio del alta manual.
 */
class RucSunat
{
    /**
     * @var list<string>
     */
    public const PREFIJOS = ['10', '15', '16', '17', '20'];

    public static function mensajeSiInvalido(string $ruc): ?string
    {
        if (! preg_match('/^\d{11}$/', $ruc)) {
            return 'El RUC debe contener exactamente 11 dígitos numéricos.';
        }

        if (! in_array(substr($ruc, 0, 2), self::PREFIJOS, true)) {
            return 'El RUC debe iniciar con 10, 20, 15, 16 o 17.';
        }

        if (! self::digitoVerificadorValido($ruc)) {
            return "El RUC {$ruc} no supera la validación matemática oficial de SUNAT (dígito verificador incorrecto).";
        }

        return null;
    }

    public static function tipoPersona(string $ruc): string
    {
        return match (substr($ruc, 0, 2)) {
            '10' => 'Persona Natural con Negocio',
            '20' => 'Persona Jurídica (Empresa / Sociedad)',
            default => 'Contribuyente Especial',
        };
    }

    public static function digitoVerificadorValido(string $ruc): bool
    {
        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += ((int) $ruc[$i]) * $factores[$i];
        }

        $resto = 11 - ($suma % 11);
        $digitoVerificador = match ($resto) {
            10 => 0,
            11 => 1,
            default => $resto,
        };

        return (int) $ruc[10] === $digitoVerificador;
    }
}
