<?php

namespace App\Support;

/**
 * Destinos de SUNAT Operaciones en Línea. Cada uno es un ingreso propio:
 * Trámites, la plataforma de declaraciones y e-renta no comparten la sesión.
 * El navegador tiene que autenticarse en la URL del servicio elegido. Entrar
 * por Trámites y luego saltar a los demás deja otra vez la pantalla de Clave SOL.
 *
 * Declaraciones usa el código de menú del PDT 621. Las DJ anuales entran por
 * el loader de e-renta: la ruta interna, sin ese loader, responde 500.
 */
class AccesosSunat
{
    /**
     * @return array<string, array{etiqueta: string, detalle: string, url: string}>
     */
    public static function destinos(): array
    {
        return [
            'tramites' => [
                'etiqueta' => 'Trámites y consultas',
                'detalle' => 'Emitir facturas y hacer consultas',
                'url' => 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm',
            ],
            'pdt621' => [
                'etiqueta' => 'Declaraciones y pagos',
                'detalle' => 'PDT 621, IGV y renta mensual',
                'url' => 'https://e-menu.sunat.gob.pe/cl-ti-itmenu2/MenuInternetPlataforma.htm?exe=55.1.1.1.1',
            ],
            'f710' => [
                'etiqueta' => 'Renta anual empresas',
                'detalle' => 'DJ anual de empresas, formulario 710',
                'url' => 'https://e-renta.sunat.gob.pe/loader/recaudaciontributaria/declaracionpago/formularios?idFormulario=710',
            ],
            'f709' => [
                'etiqueta' => 'Renta anual persona natural',
                'detalle' => 'DJ anual con negocio, formulario 709',
                'url' => 'https://e-renta.sunat.gob.pe/loader/recaudaciontributaria/declaracionpago/formularios?idFormulario=709',
            ],
        ];
    }

    public static function url(?string $codigo): string
    {
        $destinos = self::destinos();

        if ($codigo !== null && isset($destinos[$codigo])) {
            return $destinos[$codigo]['url'];
        }

        return $destinos['tramites']['url'];
    }
}
