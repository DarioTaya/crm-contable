<?php

namespace Tests\Unit;

use App\Support\AccesosSunat;
use PHPUnit\Framework\TestCase;

class AccesosSunatTest extends TestCase
{
    public function test_unknown_destination_falls_back_to_tramites(): void
    {
        $this->assertSame(
            AccesosSunat::destinos()['tramites']['url'],
            AccesosSunat::url('no-existe')
        );
        $this->assertSame(
            AccesosSunat::destinos()['tramites']['url'],
            AccesosSunat::url(null)
        );
    }

    public function test_each_destination_points_at_sunat(): void
    {
        foreach (AccesosSunat::destinos() as $destino) {
            $host = parse_url($destino['url'], PHP_URL_HOST);
            $this->assertIsString($host);
            $this->assertTrue($host === 'sunat.gob.pe' || str_ends_with($host, '.sunat.gob.pe'));
            $this->assertNotSame('', $destino['etiqueta']);
        }

        $this->assertCount(4, AccesosSunat::destinos());
    }

    /**
     * Las DJ anuales solo entran por el loader de e-renta: golpear directo la
     * ruta interna de la aplicación devuelve 500 aunque la sesión esté viva.
     */
    public function test_destinations_use_the_live_sol_routes(): void
    {
        $destinos = AccesosSunat::destinos();

        $this->assertSame(
            'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm',
            $destinos['tramites']['url']
        );
        $this->assertSame(
            'https://e-menu.sunat.gob.pe/cl-ti-itmenu2/MenuInternetPlataforma.htm?exe=55.1.1.1.1',
            $destinos['pdt621']['url']
        );
        $this->assertSame(
            'https://e-renta.sunat.gob.pe/loader/recaudaciontributaria/declaracionpago/formularios?idFormulario=710',
            $destinos['f710']['url']
        );
        $this->assertSame(
            'https://e-renta.sunat.gob.pe/loader/recaudaciontributaria/declaracionpago/formularios?idFormulario=709',
            $destinos['f709']['url']
        );
    }
}
