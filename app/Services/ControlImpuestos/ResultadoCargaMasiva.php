<?php

namespace App\Services\ControlImpuestos;

readonly class ResultadoCargaMasiva
{
    /**
     * @param  list<array{fila: int|null, mensaje: string}>  $errores
     * @param  list<string>  $muestra
     * @param  list<string>  $avisos
     */
    public function __construct(
        public bool $aplicada,
        public bool $puedeAplicar,
        public int $estados,
        public int $tributos,
        public int $pagos,
        public int $omitidos,
        public string $resumen,
        public array $errores,
        public array $muestra,
        public array $avisos,
    ) {}

    /**
     * @return array{
     *     aplicada: bool,
     *     puede_aplicar: bool,
     *     estados: int,
     *     tributos: int,
     *     pagos: int,
     *     omitidos: int,
     *     resumen: string,
     *     errores: list<array{fila: int|null, mensaje: string}>,
     *     muestra: list<string>,
     *     avisos: list<string>
     * }
     */
    public function toArray(): array
    {
        return [
            'aplicada' => $this->aplicada,
            'puede_aplicar' => $this->puedeAplicar,
            'estados' => $this->estados,
            'tributos' => $this->tributos,
            'pagos' => $this->pagos,
            'omitidos' => $this->omitidos,
            'resumen' => $this->resumen,
            'errores' => $this->errores,
            'muestra' => $this->muestra,
            'avisos' => $this->avisos,
        ];
    }
}
