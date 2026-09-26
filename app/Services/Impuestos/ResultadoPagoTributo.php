<?php

namespace App\Services\Impuestos;

/**
 * Lectura de un tributo en un periodo: si debe, si llegó a tiempo y en qué fecha quedó cubierto.
 */
final class ResultadoPagoTributo
{
    public function __construct(
        public string $situacion,
        public string $etiqueta,
        public bool $debe,
        public ?string $fechaCancelacion,
        public int $cantidadPagos,
        public int $saldoCentavos,
        public int $pagadoCentavos,
    ) {}

    public function cuentaComoFueraDeFecha(): bool
    {
        return in_array($this->situacion, [
            EvaluadorPagoTributo::PAGADO_FUERA_DE_FECHA,
            EvaluadorPagoTributo::DEBE_VENCIDO,
            EvaluadorPagoTributo::DEBE_PARCIAL_VENCIDO,
        ], true);
    }
}
