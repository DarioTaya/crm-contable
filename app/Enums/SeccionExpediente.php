<?php

namespace App\Enums;

/**
 * Secciones del expediente digital de un cliente.
 *
 * La ficha (datos y accesos) es la raíz /clientes/{cliente}. Cada módulo
 * posterior tiene su propia ruta para que los enlaces, los retornos y las
 * sincronizaciones no dependan del parámetro heredado ?tab=.
 */
enum SeccionExpediente: string
{
    case Datos = 'datos';
    case Notificaciones = 'notificaciones';
    case Operaciones = 'operaciones';
    case Creditos = 'creditos';
    case Sire = 'sire';

    /**
     * Nombre de la ruta canónica de la sección.
     */
    public function ruta(): string
    {
        return match ($this) {
            self::Datos => 'clientes.show',
            self::Notificaciones => 'clientes.notificaciones',
            self::Operaciones => 'clientes.operaciones',
            self::Creditos => 'clientes.creditos',
            self::Sire => 'clientes.sire',
        };
    }

    /**
     * Traduce el parámetro heredado ?tab= a la sección canónica.
     * La antigua pestaña de credenciales API quedó absorbida por la ficha.
     */
    public static function desde(mixed $valor): self
    {
        if (! is_string($valor)) {
            return self::Datos;
        }

        return self::tryFrom($valor) ?? self::Datos;
    }

    /**
     * Sección que corresponde al nombre de ruta que atendió la petición.
     */
    public static function actual(?string $nombreRuta): self
    {
        foreach (self::cases() as $seccion) {
            if ($seccion->ruta() === $nombreRuta) {
                return $seccion;
            }
        }

        return self::Datos;
    }
}
