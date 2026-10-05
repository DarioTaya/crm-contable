<?php

namespace App\Services\ControlImpuestos;

/**
 * Constancia o detalle de un PDT, ya separado en identidad, casillas y tributos.
 */
class DeclaracionLeida
{
    public ?string $formulario = null;

    public ?string $ruc = null;

    public ?string $razonSocial = null;

    public ?string $periodo = null;

    public ?string $numeroOrden = null;

    public ?string $fechaPresentacion = null;

    public string $tipo = 'original';

    /**
     * @var array<string, float>
     */
    public array $casillas = [];

    /**
     * @var array<string, float>
     */
    public array $tributos = [];

    public ?float $afp = null;

    public ?float $essaludBase = null;

    public ?float $renta4taBase = null;

    public bool $esBoleta = false;

    public bool $legible = true;

    public bool $tieneDetalle = false;

    public bool $formulariosMixtos = false;

    public bool $rucConflicto = false;

    public bool $periodoConflicto = false;

    public bool $ordenConflicto = false;

    /**
     * @var list<string>
     */
    public array $casillasRepetidas = [];

    /**
     * @var list<string>
     */
    public array $tributosRepetidos = [];

    public int $boletasIgnoradas = 0;

    public bool $igvJusto = false;

    /**
     * @var list<string>
     */
    public array $archivosIlegibles = [];
}
