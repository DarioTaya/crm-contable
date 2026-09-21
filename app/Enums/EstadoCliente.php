<?php

namespace App\Enums;

/**
 * Situacion comercial del cliente dentro del estudio contable.
 *
 * No confundir con `estado_contribuyente`, que refleja la situacion del RUC
 * ante SUNAT (ACTIVO, BAJA DE OFICIO, SUSPENSION TEMPORAL). Este enum describe
 * unicamente la relacion contractual con el estudio.
 */
enum EstadoCliente: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';
    case Retirado = 'retirado';

    /**
     * Etiqueta legible para la interfaz.
     */
    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activo',
            self::Suspendido => 'Suspendido',
            self::Retirado => 'Retirado / Baja',
        };
    }

    /**
     * Variante cromatica del sistema de diseno asociada al estado.
     */
    public function variante(): string
    {
        return match ($this) {
            self::Activo => 'success',
            self::Suspendido => 'warning',
            self::Retirado => 'neutral',
        };
    }

    /**
     * Indica si el cliente debe incluirse en las sincronizaciones automaticas
     * de buzon SUNAT/SUNAFIL y en los tableros de vencimientos.
     */
    public function requiereSeguimiento(): bool
    {
        return $this === self::Activo;
    }

    /**
     * Normaliza cadenas heredadas y valores booleanos previos a la migracion
     * de `estado` de boolean a cadena.
     */
    public static function desde(mixed $valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        if ($valor === true || $valor === 1 || $valor === '1') {
            return self::Activo;
        }

        if ($valor === false || $valor === 0 || $valor === '0') {
            return self::Retirado;
        }

        return self::tryFrom(strtolower(trim((string) $valor))) ?? self::Activo;
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
            fn (array $acc, self $caso) => $acc + [$caso->value => $caso->etiqueta()],
            [],
        );
    }
}
