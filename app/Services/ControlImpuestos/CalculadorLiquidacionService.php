<?php

namespace App\Services\ControlImpuestos;

use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\LiquidacionPeriodo;

/**
 * Motor de Determinación Tributaria Oficial SUNAT (PDT 621 / Declara Fácil y PDT 601 PLAME).
 *
 * Implementa las normas tributarias del TUO de la Ley del IGV (D.S. 055-99-EF),
 * Ley del Impuesto a la Renta (D.S. 179-2004-EF), Ley de Amazonía (Ley 27037),
 * Régimen MYPE Tributario (D.L. 1269), Régimen Especial (RER) y PLAME.
 */
class CalculadorLiquidacionService
{
    /**
     * Tasa de IGV estándar (18%: 16% IGV + 2% IPM).
     */
    public const TASA_IGV = 0.18;

    /**
     * Tasa de EsSalud Regular (9%).
     */
    public const TASA_ESSALUD = 0.09;

    /**
     * Tasa del Sistema Nacional de Pensiones / ONP (13%).
     */
    public const TASA_ONP = 0.13;

    /**
     * Tasa de retención de Renta de Cuarta Categoría (8%).
     */
    public const TASA_RENTA_4TA = 0.08;

    /**
     * Remuneración Mínima Vital vigente (S/ 1,130.00).
     */
    public const RMV_ACTUAL = 1130.00;

    /**
     * Aporte mínimo legal a EsSalud (9% de RMV = S/ 101.70).
     */
    public const ESSALUD_MINIMO = 101.70;

    /**
     * Recalcula la determinación de IGV, Renta y PLAME de una liquidación mensual.
     */
    public function determinar(LiquidacionPeriodo $liq, ?Cliente $cliente = null): void
    {
        $cliente = $cliente ?? $liq->cliente;
        $regimen = $cliente?->regimen_tributario;
        $esRer = $regimen === RegimenTributario::Rer || strtoupper((string) ($regimen?->value ?? $regimen)) === 'RER';
        $esRus = $regimen === RegimenTributario::Rus || strtoupper((string) ($regimen?->value ?? $regimen)) === 'RUS';

        // ══════════════════════════════════════════════════════════════
        // 1. DETERMINACIÓN DEL IGV (PDT 621 / FORMULARIO VIRTUAL 621)
        // ══════════════════════════════════════════════════════════════
        if ($liq->es_zona_selva) {
            // Exoneración por Ley de Amazonía (Ley 27037)
            $liq->igv_ventas = 0.00;
            $liq->igv_compras = 0.00;
            $liq->igv_resultante_pagar = 0.00;
            $liq->igv_saldo_favor_siguiente = 0.00;
            $liq->igv_retenciones_saldo_siguiente = 0.00;
            $liq->igv_percepciones_saldo_siguiente = 0.00;
        } else {
            $baseVentas = (float) $liq->ventas_base;
            $baseCompras = (float) $liq->compras_base;

            // Débito fiscal de ventas: calcular si no viene digitado manualmente
            if ($baseVentas > 0 && (float) $liq->igv_ventas <= 0) {
                $liq->igv_ventas = round($baseVentas * self::TASA_IGV, 2);
            }

            // Crédito fiscal de compras: calcular si no viene digitado manualmente
            if ($baseCompras > 0 && (float) $liq->igv_compras <= 0) {
                $liq->igv_compras = round($baseCompras * self::TASA_IGV, 2);
            }

            $debito = round(
                (float) $liq->igv_ventas
                + (float) $liq->igv_ventas_ley
                - (float) $liq->igv_descuentos,
                2,
            );
            $credito = round(
                (float) $liq->igv_compras
                + (float) $liq->igv_compras_ley
                + (float) $liq->igv_compras_mixtas
                + (float) $liq->igv_importaciones,
                2,
            );
            $saldoFavorAnterior = (float) $liq->igv_saldo_favor_anterior;

            // Impuesto Bruto menos Crédito Fiscal y Saldo a Favor del Exportador / Anterior
            $netoFiscal = round($debito - $credito - $saldoFavorAnterior, 2);

            $retencionesDelMes = (float) $liq->igv_retenciones;
            $retencionesAnteriores = (float) $liq->igv_retenciones_saldo_anterior;
            $totalRetenciones = round($retencionesAnteriores + $retencionesDelMes, 2);

            $percepcionesDelMes = (float) $liq->igv_percepciones;
            $percepcionesAnteriores = (float) $liq->igv_percepciones_saldo_anterior;
            $totalPercepciones = round($percepcionesAnteriores + $percepcionesDelMes, 2);

            if ($netoFiscal < 0) {
                // Hay crédito fiscal neto a favor del contribuyente (Casilla 184)
                $liq->igv_saldo_favor_siguiente = round(abs($netoFiscal), 2);
                $liq->igv_resultante_pagar = 0.00;

                // Las retenciones y percepciones NO se mezclan con el crédito fiscal;
                // pasan íntegras como saldo no aplicado para el mes siguiente (Casillas 164 y 166)
                $liq->igv_retenciones_saldo_siguiente = $totalRetenciones;
                $liq->igv_percepciones_saldo_siguiente = $totalPercepciones;
            } elseif ($netoFiscal == 0.0) {
                $liq->igv_saldo_favor_siguiente = 0.00;
                $liq->igv_resultante_pagar = 0.00;
                $liq->igv_retenciones_saldo_siguiente = $totalRetenciones;
                $liq->igv_percepciones_saldo_siguiente = $totalPercepciones;
            } else {
                // Existe saldo deudor de IGV preliminar
                $liq->igv_saldo_favor_siguiente = 0.00;

                // Fase A: Deducir Retenciones
                if ($totalRetenciones >= $netoFiscal) {
                    $restoDespuesRetenciones = 0.00;
                    $liq->igv_retenciones_saldo_siguiente = round($totalRetenciones - $netoFiscal, 2);
                    // Las percepciones quedan intactas para el periodo siguiente
                    $liq->igv_percepciones_saldo_siguiente = $totalPercepciones;
                    $liq->igv_resultante_pagar = 0.00;
                } else {
                    $restoDespuesRetenciones = round($netoFiscal - $totalRetenciones, 2);
                    $liq->igv_retenciones_saldo_siguiente = 0.00;

                    // Fase B: Deducir Percepciones del saldo remanente
                    if ($totalPercepciones >= $restoDespuesRetenciones) {
                        $liq->igv_percepciones_saldo_siguiente = round($totalPercepciones - $restoDespuesRetenciones, 2);
                        $liq->igv_resultante_pagar = 0.00;
                    } else {
                        $liq->igv_percepciones_saldo_siguiente = 0.00;
                        $liq->igv_resultante_pagar = round($restoDespuesRetenciones - $totalPercepciones, 2);
                    }
                }
            }
        }

        // ══════════════════════════════════════════════════════════════
        // 2. DETERMINACIÓN DE IMPUESTO A LA RENTA (CASILLA 301, 302 PDT 621)
        // ══════════════════════════════════════════════════════════════
        if ($esRus) {
            // El NRUS no determina renta por el PDT 621
            $liq->renta_base_ingresos = 0.00;
            $liq->renta_calculada = 0.00;
            $liq->renta_resultante_pagar = 0.00;
            $liq->renta_saldo_favor_siguiente = 0.00;
        } else {
            // Base Imponible de Renta: Ventas Gravadas + Ventas No Gravadas
            $totalVentas = max(0, round(
                (float) $liq->ventas_base
                - (float) $liq->igv_descuentos_base
                + (float) $liq->ventas_no_gravadas,
                2,
            ));
            if ((float) $liq->renta_base_ingresos <= 0 && $totalVentas > 0) {
                $liq->renta_base_ingresos = $totalVentas;
            }

            // Tasa y factor de cálculo según régimen
            $factor = $this->resolverFactorRenta($liq->renta_tasa, $regimen);

            if ((float) $liq->renta_base_ingresos > 0 && (float) $liq->renta_calculada <= 0 && $factor > 0) {
                $liq->renta_calculada = round((float) $liq->renta_base_ingresos * $factor);
            }

            $rentaBruta = (float) ($liq->renta_calculada ?? 0);

            if ($esRer) {
                $liq->renta_saldo_favor_anterior = 0.00;
                $liq->renta_saldo_favor_siguiente = 0.00;
                $ret3ra = (float) $liq->renta_retenciones;
                $itan = (float) $liq->renta_compensacion_itan;
                $liq->renta_resultante_pagar = max(0.00, round($rentaBruta - $ret3ra - $itan, 2));
                $liq->renta_itan_saldo_siguiente = max(0.00, round((float) ($liq->renta_itan_saldo_anterior ?? 0) - $itan, 2));
            } else {
                // Régimen General o MYPE Tributario: admite compensaciones
                $compensacionSaldo = (float) ($liq->renta_saldo_favor_anterior ?? 0); // Casilla 109
                $compensacionItan = (float) ($liq->renta_compensacion_itan ?? 0);     // Casilla 111
                $retenciones3ra = (float) ($liq->renta_retenciones ?? 0);             // Casilla 113
                $itanSaldoAnt = (float) ($liq->renta_itan_saldo_anterior ?? 0);

                // Control de saldo remanente de ITAN para meses siguientes del ejercicio (Ley 28424)
                if ($itanSaldoAnt > 0) {
                    $liq->renta_itan_saldo_siguiente = max(0.00, round($itanSaldoAnt - $compensacionItan, 2));
                } else {
                    $liq->renta_itan_saldo_siguiente = 0.00;
                }

                $totalCreditosRenta = round($compensacionSaldo + $compensacionItan + $retenciones3ra, 2);

                if ($totalCreditosRenta >= $rentaBruta) {
                    $liq->renta_resultante_pagar = 0.00;
                    // El saldo a favor de Renta (Casilla 109) no agotado queda para los siguientes meses del ejercicio
                    $remanenteCreditos = round($totalCreditosRenta - $rentaBruta, 2);
                    $liq->renta_saldo_favor_siguiente = min($compensacionSaldo, $remanenteCreditos);
                } else {
                    $liq->renta_resultante_pagar = round($rentaBruta - $totalCreditosRenta, 2);
                    $liq->renta_saldo_favor_siguiente = 0.00;
                }
            }
        }

        // ══════════════════════════════════════════════════════════════
        // 3. DETERMINACIÓN DE PLANILLA ELECTRÓNICA / PLAME (PDT 601)
        // ══════════════════════════════════════════════════════════════
        $baseEssalud = (float) ($liq->plame_essalud_base ?? 0);
        if ($baseEssalud > 0 && (float) ($liq->plame_essalud_a_pagar ?? 0) <= 0) {
            $liq->plame_essalud_a_pagar = max(round($baseEssalud * self::TASA_ESSALUD, 2), self::ESSALUD_MINIMO);
        }

        $base4ta = (float) ($liq->plame_renta_4ta_base ?? 0);
        if ($base4ta > 0 && (float) ($liq->plame_renta_4ta_a_pagar ?? 0) <= 0) {
            $liq->plame_renta_4ta_a_pagar = round($base4ta * self::TASA_RENTA_4TA, 2);
        }
    }

    /**
     * Resuelve el factor matemático (ej. 0.015) a partir del input y del régimen.
     */
    public function resolverFactorRenta(float|int|string|null $tasaInput, RegimenTributario|string|null $regimen): float
    {
        $regimenStr = strtoupper(trim((string) ($regimen instanceof RegimenTributario ? $regimen->value : $regimen)));

        if ($regimenStr === 'RER') {
            return 0.015; // 1.5% fijo e inmutable
        }

        $tasa = (float) $tasaInput;
        if ($tasa <= 0) {
            return in_array($regimenStr, ['GENERAL', 'RG'], true) ? 0.015 : 0.010;
        }

        // Si el usuario ingresó porcentaje ej. 1.0, 1.5, 2.5
        if ($tasa >= 0.5) {
            return round($tasa / 100, 6);
        }

        // Si el usuario ingresó coeficiente directo ej. 0.0215, 0.0150
        return round($tasa, 6);
    }

    /**
     * Obtiene los saldos a favor arrastrables de meses anteriores para un periodo dado.
     * Conecta inter-mensualmente y trans-ejercicio (Diciembre del año previo con Enero).
     *
     * @return array{
     *     igv_saldo_favor_anterior: float,
     *     igv_retenciones_saldo_anterior: float,
     *     igv_percepciones_saldo_anterior: float,
     *     renta_saldo_favor_anterior: float,
     *     renta_itan_saldo_anterior: float,
     *     hay_liquidacion_anterior: bool
     * }
     */
    public function obtenerSaldosAnteriores(Cliente $cliente, string $periodo): array
    {
        [$mesStr, $anioStr] = explode('-', $periodo);
        $mes = (int) $mesStr;
        $anio = (int) $anioStr;

        $periodoAnterior = $mes > 1
            ? sprintf('%02d-%d', $mes - 1, $anio)
            : sprintf('12-%d', $anio - 1);

        $liqAnterior = LiquidacionPeriodo::query()
            ->where('cliente_id', $cliente->id)
            ->where('periodo', $periodoAnterior)
            ->first();

        if (! $liqAnterior) {
            return [
                'igv_saldo_favor_anterior' => 0.00,
                'igv_retenciones_saldo_anterior' => 0.00,
                'igv_percepciones_saldo_anterior' => 0.00,
                'renta_saldo_favor_anterior' => 0.00,
                'renta_itan_saldo_anterior' => 0.00,
                'hay_liquidacion_anterior' => false,
            ];
        }

        // El saldo a favor de Renta e ITAN solo se arrastran dentro del mismo ejercicio fiscal
        $rentaSaldo = $mes > 1 ? (float) ($liqAnterior->renta_saldo_favor_siguiente ?? 0) : 0.00;
        $itanSaldo = $mes > 1 ? (float) ($liqAnterior->renta_itan_saldo_siguiente ?? 0) : 0.00;

        return [
            'igv_saldo_favor_anterior' => (float) ($liqAnterior->igv_saldo_favor_siguiente ?? 0),
            'igv_retenciones_saldo_anterior' => (float) ($liqAnterior->igv_retenciones_saldo_siguiente ?? 0),
            'igv_percepciones_saldo_anterior' => (float) ($liqAnterior->igv_percepciones_saldo_siguiente ?? 0),
            'renta_saldo_favor_anterior' => $rentaSaldo,
            'renta_itan_saldo_anterior' => $itanSaldo,
            'hay_liquidacion_anterior' => true,
        ];
    }

    /**
     * Propaga automáticamente el arrastre de saldos a favor en cascada
     * para los meses posteriores del mismo año (M+1, M+2, ...).
     */
    public function propagarArrastreEnCascada(Cliente $cliente, string $periodoBase): void
    {
        [$mesBaseStr, $anioBaseStr] = explode('-', $periodoBase);
        $mesBase = (int) $mesBaseStr;
        $anioBase = (int) $anioBaseStr;

        $periodosPosteriores = [];
        for ($m = $mesBase + 1; $m <= 12; $m++) {
            $periodosPosteriores[] = sprintf('%02d-%d', $m, $anioBase);
        }

        if (empty($periodosPosteriores)) {
            return;
        }

        $liquidaciones = LiquidacionPeriodo::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('periodo', $periodosPosteriores)
            ->orderBy('periodo')
            ->get()
            ->keyBy('periodo');

        $mesPrevio = $mesBase;
        $liqPrevia = LiquidacionPeriodo::query()
            ->where('cliente_id', $cliente->id)
            ->where('periodo', $periodoBase)
            ->first();

        for ($m = $mesBase + 1; $m <= 12; $m++) {
            $periodoActual = sprintf('%02d-%d', $m, $anioBase);
            $liqActual = $liquidaciones->get($periodoActual);

            if (! $liqActual) {
                $liqPrevia = null;

                continue;
            }

            if ($liqPrevia) {
                $liqActual->igv_saldo_favor_anterior = (float) $liqPrevia->igv_saldo_favor_siguiente;
                $liqActual->igv_retenciones_saldo_anterior = (float) $liqPrevia->igv_retenciones_saldo_siguiente;
                $liqActual->igv_percepciones_saldo_anterior = (float) $liqPrevia->igv_percepciones_saldo_siguiente;
                $liqActual->renta_saldo_favor_anterior = (float) $liqPrevia->renta_saldo_favor_siguiente;
                $liqActual->renta_itan_saldo_anterior = (float) $liqPrevia->renta_itan_saldo_siguiente;
            } else {
                $saldos = $this->obtenerSaldosAnteriores($cliente, $periodoActual);
                $liqActual->igv_saldo_favor_anterior = $saldos['igv_saldo_favor_anterior'];
                $liqActual->igv_retenciones_saldo_anterior = $saldos['igv_retenciones_saldo_anterior'];
                $liqActual->igv_percepciones_saldo_anterior = $saldos['igv_percepciones_saldo_anterior'];
                $liqActual->renta_saldo_favor_anterior = $saldos['renta_saldo_favor_anterior'];
                $liqActual->renta_itan_saldo_anterior = $saldos['renta_itan_saldo_anterior'];
            }

            $this->determinar($liqActual, $cliente);
            $liqActual->save();

            $liqPrevia = $liqActual;
        }
    }
}
