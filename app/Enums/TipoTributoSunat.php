<?php

namespace App\Enums;

/**
 * Códigos oficiales y denominaciones de tributos de la SUNAT.
 */
enum TipoTributoSunat: string
{
    case Igv18 = '1011';
    case RentaEspecial = '3111';
    case RentaMype = '3121';
    case RentaGeneral = '3031';
    case RentaRegularizacion = '3081';
    case RentaCuarta = '3042';
    case RentaQuinta = '3052';
    case EsSalud = '5210';
    case Onp = '5310';
    case Afp = 'AFP';
    case Igv105 = '1016';

    /**
     * Denominación oficial del tributo SUNAT.
     */
    public function descripcion(): string
    {
        return match ($this) {
            self::Igv18 => 'IGV - OPER. INT. - CTA. PROPIA',
            self::Igv105 => 'IGV Restaurantes y Hoteles 10.5% (Ley 31556)',
            self::RentaEspecial => 'RENTA - REGIMEN ESPECIAL',
            self::RentaMype => 'RENTA - REGIMEN MYPE TRIBUTARIO',
            self::RentaGeneral => 'RENTA-3RA. CATEGOR.-CTA. PROPIA',
            self::RentaRegularizacion => 'RENTA - REGULARIZ. - PERS. JUR.',
            self::RentaCuarta => 'RENTA 4TA. CATEG. RETENCIONES',
            self::RentaQuinta => 'RENTA 5TA. CATEG. RETENCIONES',
            self::EsSalud => 'ESSALUD SEG REGULAR TRABAJADOR',
            self::Onp => 'SNP - LEY 19990',
            self::Afp => 'APORTES AFP (PRIMA / FONDO / COMISIÓN)',
        };
    }

    /**
     * Nombre corto para etiquetas compactas en tablas o badges.
     */
    public function nombreCorto(): string
    {
        return match ($this) {
            self::Igv18 => 'IGV 18%',
            self::Igv105 => 'IGV 10.5%',
            self::RentaEspecial => 'Renta RER 1.5%',
            self::RentaMype => 'Renta RMT 1%',
            self::RentaGeneral => 'Renta General',
            self::RentaRegularizacion => 'Renta Regularización',
            self::RentaCuarta => 'Renta 4ta',
            self::RentaQuinta => 'Renta 5ta',
            self::EsSalud => 'EsSalud',
            self::Onp => 'ONP',
            self::Afp => 'AFP',
        };
    }

    /**
     * Etiqueta completa con código oficial para selector y reportes.
     */
    public function etiquetaCompleta(): string
    {
        return "{$this->value} - {$this->descripcion()}";
    }

    /**
     * PDT en el que se determina el tributo.
     * 621 es IGV y renta; 601 es PLAME (planilla y retenciones).
     */
    public function declaracion(): string
    {
        return match ($this) {
            self::Igv18, self::Igv105, self::RentaEspecial, self::RentaMype, self::RentaGeneral, self::RentaRegularizacion => 'pdt_621',
            self::RentaCuarta, self::RentaQuinta, self::EsSalud, self::Onp, self::Afp => 'pdt_601',
        };
    }

    /**
     * Renta mensual que corresponde al régimen del cliente en el PDT 621.
     * NRUS no determina renta por ese formulario.
     */
    public static function rentaDelRegimen(RegimenTributario|string|null $regimen): ?self
    {
        if ($regimen === null) {
            return null;
        }

        $valor = $regimen instanceof RegimenTributario ? $regimen->value : (string) $regimen;
        $norm = strtoupper(trim($valor));
        if ($norm === '') {
            return null;
        }

        return match ($norm) {
            'RER' => self::RentaEspecial,
            'RG', 'GENERAL' => self::RentaGeneral,
            'RUS', 'NRUS' => null,
            'RMT', 'MYPE' => self::RentaMype,
            default => self::RentaMype,
        };
    }

    /**
     * Declaración del código, incluidos los códigos anteriores 3041 y 3051.
     */
    public static function declaracionDe(?string $codigo): ?string
    {
        return self::resolver($codigo)?->declaracion();
    }

    /**
     * Texto exacto del selector de Tributos y Pagos.
     */
    public function etiquetaSelector(): string
    {
        if ($this === self::Afp) {
            return 'AFP - APORTES AFP (PRIMA / FONDO / COMISIÓN)';
        }

        return "{$this->value} {$this->descripcion()}";
    }

    /**
     * Opciones completas indexadas por código SUNAT para poblar selectores.
     *
     * @return array<string, string>
     */
    public static function opciones(): array
    {
        $opciones = [];
        foreach (self::cases() as $caso) {
            $opciones[$caso->value] = "{$caso->value} - {$caso->descripcion()}";
        }

        return $opciones;
    }

    /**
     * Tributos del selector, en el orden fijo de la caja de deuda.
     *
     * @return list<array{codigo: string, etiqueta: string, nombre: string}>
     */
    public static function listaSelector(): array
    {
        $lista = [];
        foreach (self::cases() as $caso) {
            if ($caso === self::Igv105) {
                continue;
            }
            $lista[] = [
                'codigo' => $caso->value,
                'etiqueta' => $caso->etiquetaSelector(),
                'nombre' => $caso->descripcion(),
            ];
        }

        return $lista;
    }

    /**
     * Catálogo del formulario, agrupado por declaración.
     * El orden es el de trabajo del contador: IGV, renta del PDT 621 y luego PLAME.
     *
     * @return list<array{id: string, titulo: string, detalle: string, tributos: list<array{codigo: string, etiqueta: string, nombre: string, nombre_corto: string}>}>
     */
    public static function catalogoFormulario(): array
    {
        $bloques = [
            [
                'id' => 'pdt_621',
                'titulo' => 'PDT 621',
                'detalle' => 'IGV y Renta',
                'orden' => [
                    self::Igv18,
                    self::Igv105,
                    self::RentaEspecial,
                    self::RentaMype,
                    self::RentaGeneral,
                    self::RentaRegularizacion,
                ],
            ],
            [
                'id' => 'pdt_601',
                'titulo' => 'PDT 601',
                'detalle' => 'PLAME',
                'orden' => [
                    self::EsSalud,
                    self::Onp,
                    self::Afp,
                    self::RentaQuinta,
                    self::RentaCuarta,
                ],
            ],
        ];

        return array_map(function (array $bloque): array {
            return [
                'id' => $bloque['id'],
                'titulo' => $bloque['titulo'],
                'detalle' => $bloque['detalle'],
                'tributos' => array_map(fn (self $caso): array => [
                    'codigo' => $caso->value,
                    'etiqueta' => $caso->etiquetaSelector(),
                    'nombre' => $caso->descripcion(),
                    'nombre_corto' => $caso->nombreCorto(),
                ], $bloque['orden']),
            ];
        }, $bloques);
    }

    /**
     * Resuelve códigos vigentes y los anteriores 3041/3051.
     */
    public static function resolver(?string $codigo): ?self
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        return match ($codigo) {
            '3041' => self::RentaCuarta,
            '3051' => self::RentaQuinta,
            default => self::tryFrom($codigo),
        };
    }

    /**
     * Resuelve el nombre del tributo dado un código o devuelve un valor por defecto.
     */
    public static function obtenerNombre(?string $codigo, string $default = 'Tributo SUNAT'): string
    {
        if (empty($codigo)) {
            return $default;
        }

        return self::resolver($codigo)?->descripcion() ?? $codigo;
    }

    /**
     * Resuelve el nombre corto dado un código.
     */
    public static function obtenerNombreCorto(?string $codigo, string $default = 'Tributo'): string
    {
        if (empty($codigo)) {
            return $default;
        }

        return self::resolver($codigo)?->nombreCorto() ?? $codigo;
    }
}
