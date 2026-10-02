<?php

namespace Tests\Unit;

use App\Enums\RegimenTributario;
use App\Enums\TipoTributoSunat;
use PHPUnit\Framework\TestCase;

class TipoTributoSunatTest extends TestCase
{
    public function test_catalogo_separa_pdt_621_de_plame_sin_dejar_tributos_fuera(): void
    {
        $catalogo = TipoTributoSunat::catalogoFormulario();

        $this->assertSame('pdt_621', $catalogo[0]['id']);
        $this->assertSame('IGV y Renta', $catalogo[0]['detalle']);
        $this->assertSame(
            ['1011', '1016', '3111', '3121', '3031', '3081'],
            array_column($catalogo[0]['tributos'], 'codigo'),
        );

        $this->assertSame('pdt_601', $catalogo[1]['id']);
        $this->assertSame('PLAME', $catalogo[1]['detalle']);
        $this->assertSame(
            ['5210', '5310', 'AFP', '3052', '3042'],
            array_column($catalogo[1]['tributos'], 'codigo'),
        );

        $codigos = array_merge(
            array_column($catalogo[0]['tributos'], 'codigo'),
            array_column($catalogo[1]['tributos'], 'codigo'),
        );
        $esperados = array_map(fn (TipoTributoSunat $caso): string => $caso->value, TipoTributoSunat::cases());
        sort($codigos);
        sort($esperados);

        $this->assertSame($esperados, $codigos);
    }

    public function test_renta_del_regimen_y_codigos_anteriores_caen_en_su_declaracion(): void
    {
        $this->assertSame(TipoTributoSunat::RentaMype, TipoTributoSunat::rentaDelRegimen(RegimenTributario::Rmt));
        $this->assertSame(TipoTributoSunat::RentaMype, TipoTributoSunat::rentaDelRegimen('RMT'));
        $this->assertSame(TipoTributoSunat::RentaMype, TipoTributoSunat::rentaDelRegimen('MYPE'));
        $this->assertSame(TipoTributoSunat::RentaEspecial, TipoTributoSunat::rentaDelRegimen('RER'));
        $this->assertSame(TipoTributoSunat::RentaGeneral, TipoTributoSunat::rentaDelRegimen(RegimenTributario::Rg));
        $this->assertSame(TipoTributoSunat::RentaGeneral, TipoTributoSunat::rentaDelRegimen('RG'));
        $this->assertSame(TipoTributoSunat::RentaGeneral, TipoTributoSunat::rentaDelRegimen('GENERAL'));
        $this->assertNull(TipoTributoSunat::rentaDelRegimen(RegimenTributario::Rus));
        $this->assertNull(TipoTributoSunat::rentaDelRegimen(null));

        $this->assertSame('pdt_621', TipoTributoSunat::declaracionDe('1011'));
        $this->assertSame('pdt_621', TipoTributoSunat::declaracionDe('1016'));
        $this->assertSame('pdt_601', TipoTributoSunat::declaracionDe('5210'));
        $this->assertSame('pdt_601', TipoTributoSunat::declaracionDe('3041'));
        $this->assertSame('pdt_601', TipoTributoSunat::declaracionDe('3051'));
        $this->assertNull(TipoTributoSunat::declaracionDe(null));
        $this->assertNull(TipoTributoSunat::declaracionDe('9999'));
    }
}
