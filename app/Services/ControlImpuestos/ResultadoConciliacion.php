<?php

namespace App\Services\ControlImpuestos;

/**
 * Lo que la hoja puede recibir y las alertas que el contador debe ver antes de guardar.
 */
class ResultadoConciliacion
{
    /**
     * @param  array<string, float|bool|string|null>  $campos
     * @param  array<string, float|string|null>  $preservar
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     * @param  array<string, string|null>  $identidad
     * @param  array{igv: float|null, renta: float|null}  $control
     * @param  array{igv: float|null, renta: float|null}  $calculo
     * @param  list<int>  $constanciaIds
     */
    public function __construct(
        public bool $bloqueado,
        public string $formulario,
        public array $campos,
        public array $preservar,
        public array $alertas,
        public array $identidad,
        public array $constanciaIds = [],
        public array $control = ['igv' => null, 'renta' => null],
        public array $calculo = ['igv' => null, 'renta' => null],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => ! $this->bloqueado,
            'bloqueado' => $this->bloqueado,
            'formulario' => $this->formulario,
            'identidad' => $this->identidad,
            'campos' => $this->campos,
            'preservar' => $this->preservar,
            'control' => $this->control,
            'calculo' => $this->calculo,
            'alertas' => $this->alertas,
            'constancia_id' => $this->constanciaIds[0] ?? null,
        ];
    }
}
