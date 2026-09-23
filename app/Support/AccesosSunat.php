<?php

namespace App\Support;

/**
 * Destinos de SUNAT Operaciones en Línea, después del login con Clave SOL.
 *
 * Trámites queda en el menú SOL. Declaraciones y las dos DJ anuales usan la
 * ruta final que deja la sesión ya iniciada: la plataforma de declaraciones
 * con el código de menú de PDT 621, y el aplicativo e-renta de personas y de
 * empresas. Esas tres rutas las confirmó el uso real del portal el 21/09/2026;
 * las anteriores respondían 404.
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
