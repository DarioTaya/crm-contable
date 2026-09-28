<?php

namespace App\Services\ControlImpuestos;

readonly class SolicitudCargaMasiva
{
    /**
     * @param  list<string>  $obligaciones
     * @param  list<int>  $clienteIds
     */
    public function __construct(
        public string $modo,
        public int $anio,
        public string $politica,
        public ?int $mesDesde,
        public ?int $mesHasta,
        public ?string $estado,
        public array $obligaciones,
        public string $alcance,
        public array $clienteIds,
        public ?string $contenido,
    ) {}
}
