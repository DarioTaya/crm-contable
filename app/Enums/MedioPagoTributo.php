<?php

namespace App\Enums;

/**
 * Origen del dinero con el que se canceló un tributo.
 *
 * No se infiere de la observación: si el pago no tiene medio, el reporte lo muestra sin clasificar.
 */
enum MedioPagoTributo: string
{
    case Detracciones = 'detracciones';
    case Caja = 'caja';
    case Bancos = 'bancos';
    case CompensacionRenta = 'compensacion_renta';
    case CompensacionItan = 'compensacion_itan';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Detracciones => 'Detracciones',
            self::Caja => 'Caja',
            self::Bancos => 'Bancos',
            self::CompensacionRenta => 'Compensación renta anual',
            self::CompensacionItan => 'Compensación ITAN',
        };
    }
}
