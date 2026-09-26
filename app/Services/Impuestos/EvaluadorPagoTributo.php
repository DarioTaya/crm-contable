<?php

namespace App\Services\Impuestos;

/**
 * Decide la situación de un tributo ya determinado.
 *
 * La fecha de cancelación es el día en que la suma de los pagos alcanza el importe.
 * El vencimiento es el guardado en el tributo, incluida la prórroga de IGV Justo:
 * este evaluador no recalcula el cronograma.
 * Un pago del mismo día del vencimiento está a tiempo. Al día siguiente ya está fuera de fecha.
 */
class EvaluadorPagoTributo
{
    public const SIN_DETERMINAR = 'sin_determinar';

    public const PAGADO_A_TIEMPO = 'pagado_a_tiempo';

    public const PAGADO_FUERA_DE_FECHA = 'pagado_fuera_de_fecha';

    public const PAGADO_SIN_VENCIMIENTO = 'pagado_sin_vencimiento';

    public const PAGADO_SIN_FECHA = 'pagado_sin_fecha';

    public const DEBE_EN_PLAZO = 'debe_en_plazo';

    public const DEBE_VENCIDO = 'debe_vencido';

    public const DEBE_PARCIAL = 'debe_parcial';

    public const DEBE_PARCIAL_VENCIDO = 'debe_parcial_vencido';

    public const DEBE_SIN_VENCIMIENTO = 'debe_sin_vencimiento';

    /**
     * @var array<string, string>
     */
    private const ETIQUETAS = [
        self::SIN_DETERMINAR => 'Sin determinar',
        self::PAGADO_A_TIEMPO => 'Pagado a tiempo',
        self::PAGADO_FUERA_DE_FECHA => 'Pagado fuera de fecha',
        self::PAGADO_SIN_VENCIMIENTO => 'Pagado · sin vencimiento',
        self::PAGADO_SIN_FECHA => 'Pagado · sin fecha',
        self::DEBE_EN_PLAZO => 'Debe · en plazo',
        self::DEBE_VENCIDO => 'Debe · vencido',
        self::DEBE_PARCIAL => 'Debe · parcial en plazo',
        self::DEBE_PARCIAL_VENCIDO => 'Debe · parcial vencido',
        self::DEBE_SIN_VENCIMIENTO => 'Debe',
    ];

    /**
     * @param  list<array{monto: float|int|string, fecha?: string|null, id?: int}>  $pagos
     */
    public function evaluar(float|int|string $determinado, ?string $vencimiento, array $pagos, string $hoy): ResultadoPagoTributo
    {
        $determinadoCentavos = $this->centavos($determinado);
        $pagosValidos = array_values(array_filter(
            $pagos,
            fn (array $pago): bool => $this->centavos($pago['monto'] ?? 0) > 0,
        ));

        if ($determinadoCentavos <= 0) {
            return $this->resultado(self::SIN_DETERMINAR, false, null, count($pagosValidos), 0, 0);
        }

        usort($pagosValidos, function (array $a, array $b): int {
            $porFecha = strcmp((string) ($a['fecha'] ?? ''), (string) ($b['fecha'] ?? ''));

            return $porFecha !== 0 ? $porFecha : (($a['id'] ?? 0) <=> ($b['id'] ?? 0));
        });

        $acumulado = 0;
        $cubierto = false;
        $fechaCancelacion = null;

        foreach ($pagosValidos as $pago) {
            $acumulado += $this->centavos($pago['monto']);
            if ($cubierto || $acumulado < $determinadoCentavos) {
                continue;
            }

            $cubierto = true;
            $fechaCancelacion = $this->fechaIso($pago['fecha'] ?? null);
        }

        $saldo = max(0, $determinadoCentavos - $acumulado);
        $vencimientoIso = $this->fechaIso($vencimiento);
        $hoyIso = $this->fechaIso($hoy);
        $cantidad = count($pagosValidos);

        if ($saldo === 0) {
            if ($fechaCancelacion === null) {
                return $this->resultado(self::PAGADO_SIN_FECHA, false, null, $cantidad, 0, $acumulado);
            }

            if ($vencimientoIso === null) {
                return $this->resultado(self::PAGADO_SIN_VENCIMIENTO, false, $fechaCancelacion, $cantidad, 0, $acumulado);
            }

            $situacion = $fechaCancelacion <= $vencimientoIso
                ? self::PAGADO_A_TIEMPO
                : self::PAGADO_FUERA_DE_FECHA;

            return $this->resultado($situacion, false, $fechaCancelacion, $cantidad, 0, $acumulado);
        }

        $hayPagos = $acumulado > 0;

        if ($vencimientoIso === null || $hoyIso === null) {
            return $this->resultado(self::DEBE_SIN_VENCIMIENTO, true, null, $cantidad, $saldo, $acumulado);
        }

        $vencido = $hoyIso > $vencimientoIso;

        if ($vencido) {
            $situacion = $hayPagos ? self::DEBE_PARCIAL_VENCIDO : self::DEBE_VENCIDO;
        } else {
            $situacion = $hayPagos ? self::DEBE_PARCIAL : self::DEBE_EN_PLAZO;
        }

        return $this->resultado($situacion, true, null, $cantidad, $saldo, $acumulado);
    }

    private function resultado(
        string $situacion,
        bool $debe,
        ?string $fechaCancelacion,
        int $cantidadPagos,
        int $saldoCentavos,
        int $pagadoCentavos,
    ): ResultadoPagoTributo {
        return new ResultadoPagoTributo(
            situacion: $situacion,
            etiqueta: self::ETIQUETAS[$situacion],
            debe: $debe,
            fechaCancelacion: $fechaCancelacion,
            cantidadPagos: $cantidadPagos,
            saldoCentavos: $saldoCentavos,
            pagadoCentavos: $pagadoCentavos,
        );
    }

    private function centavos(float|int|string $monto): int
    {
        return (int) round(((float) $monto) * 100);
    }

    private function fechaIso(mixed $valor): ?string
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        return $valor;
    }
}
