<?php

namespace App\Services\Impuestos;

use App\Models\Cliente;
use App\Models\Operacion;

/**
 * Servicio Senior de Auditoría y Control Tributario de Pagos Anticipados y Créditos Fiscales:
 * - Retenciones del IGV (Agentes de Retención - Res. 037-2002/SUNAT - 3%)
 * - Percepciones del IGV (Agentes de Percepción - Ley 29169 / D.S. 091-2013-EF)
 * - Detracciones SPOT (D. Leg. 940 - Cuenta de Detracciones Banco de la Nación)
 * - Saldos a Favor de IGV (Crédito Fiscal arrastrable - TUO Ley del IGV)
 * - Compensación de ITAN (Ley 28424 / D.S. 025-2005-EF)
 */
class ReporteCreditosFiscalesCliente
{
    /**
     * @var array<int, array{corto: string, nombre: string}>
     */
    private const MESES = [
        1 => ['corto' => 'ENE', 'nombre' => 'Enero'],
        2 => ['corto' => 'FEB', 'nombre' => 'Febrero'],
        3 => ['corto' => 'MAR', 'nombre' => 'Marzo'],
        4 => ['corto' => 'ABR', 'nombre' => 'Abril'],
        5 => ['corto' => 'MAY', 'nombre' => 'Mayo'],
        6 => ['corto' => 'JUN', 'nombre' => 'Junio'],
        7 => ['corto' => 'JUL', 'nombre' => 'Julio'],
        8 => ['corto' => 'AGO', 'nombre' => 'Agosto'],
        9 => ['corto' => 'SET', 'nombre' => 'Setiembre'],
        10 => ['corto' => 'OCT', 'nombre' => 'Octubre'],
        11 => ['corto' => 'NOV', 'nombre' => 'Noviembre'],
        12 => ['corto' => 'DIC', 'nombre' => 'Diciembre'],
    ];

    /**
     * @return array{
     *     anio: int,
     *     anios: list<int>,
     *     kpis: array{
     *         retenciones_acumuladas: float,
     *         retenciones_aplicadas: float,
     *         retenciones_saldo_disponible: float,
     *         percepciones_acumuladas: float,
     *         percepciones_aplicadas: float,
     *         percepciones_saldo_disponible: float,
     *         detracciones_total_pagado: float,
     *         detracciones_conteo_pagos: int,
     *         credito_fiscal_vigente: float,
     *         itan_saldo_disponible: float
     *     },
     *     retenciones: list<array<string, mixed>>,
     *     percepciones: list<array<string, mixed>>,
     *     detracciones: list<array<string, mixed>>,
     *     pagos_detracciones: list<array<string, mixed>>,
     *     credito_fiscal: list<array<string, mixed>>,
     *     itan: list<array<string, mixed>>
     * }
     */
    public function armar(Cliente $cliente, int $anio): array
    {
        $periodos = [];
        for ($m = 1; $m <= 12; $m++) {
            $periodos[] = sprintf('%02d-%d', $m, $anio);
        }

        $operaciones = Operacion::query()
            ->where('cliente_id', $cliente->id)
            ->whereIn('periodo', $periodos)
            ->with(['liquidacionPeriodo', 'abonosDeuda', 'deudasTributarias.abonos'])
            ->get()
            ->keyBy('periodo');

        $mesesRetenciones = [];
        $mesesPercepciones = [];
        $mesesDetracciones = [];
        $mesesCreditoFiscal = [];
        $mesesItan = [];

        $totalRetencionesDeclaradas = 0.0;
        $totalRetencionesAplicadas = 0.0;
        $saldoFinalRetenciones = 0.0;

        $totalPercepcionesDeclaradas = 0.0;
        $totalPercepcionesAplicadas = 0.0;
        $saldoFinalPercepciones = 0.0;

        $totalPagosDetracciones = 0.0;
        $pagosDetraccionesList = [];

        $saldoFinalCreditoFiscal = 0.0;
        $saldoFinalItan = 0.0;

        for ($m = 1; $m <= 12; $m++) {
            $per = sprintf('%02d-%d', $m, $anio);
            $etiqueta = self::MESES[$m]['corto'].'-'.substr((string) $anio, -2);
            $nombreMes = self::MESES[$m]['nombre'];
            $op = $operaciones->get($per);
            $liq = $op?->liquidacionPeriodo;

            // 1. Retenciones IGV (Casillas 168, 179, 171, 169)
            $retSaldoAnt = (float) ($liq?->igv_retenciones_saldo_anterior ?? 0);
            $retMes = (float) ($liq?->igv_retenciones ?? 0);
            $retSaldoSig = (float) ($liq?->igv_retenciones_saldo_siguiente ?? 0);
            $retAplicada = max(0.0, round(($retSaldoAnt + $retMes) - $retSaldoSig, 2));

            $totalRetencionesDeclaradas += $retMes;
            $totalRetencionesAplicadas += $retAplicada;
            if ($liq) {
                $saldoFinalRetenciones = $retSaldoSig;
            }

            $mesesRetenciones[] = [
                'periodo' => $per,
                'etiqueta' => $etiqueta,
                'mes' => $nombreMes,
                'saldo_anterior' => $retSaldoAnt,
                'declarado' => $retMes,
                'aplicado' => $retAplicada,
                'saldo_siguiente' => $retSaldoSig,
                'tiene_datos' => ($retSaldoAnt > 0 || $retMes > 0 || $retSaldoSig > 0),
            ];

            // 2. Percepciones IGV (Casillas 171, 173, 172, 175)
            $perSaldoAnt = (float) ($liq?->igv_percepciones_saldo_anterior ?? 0);
            $perMes = (float) ($liq?->igv_percepciones ?? 0);
            $perSaldoSig = (float) ($liq?->igv_percepciones_saldo_siguiente ?? 0);
            $perAplicada = max(0.0, round(($perSaldoAnt + $perMes) - $perSaldoSig, 2));

            $totalPercepcionesDeclaradas += $perMes;
            $totalPercepcionesAplicadas += $perAplicada;
            if ($liq) {
                $saldoFinalPercepciones = $perSaldoSig;
            }

            $mesesPercepciones[] = [
                'periodo' => $per,
                'etiqueta' => $etiqueta,
                'mes' => $nombreMes,
                'saldo_anterior' => $perSaldoAnt,
                'declarado' => $perMes,
                'aplicado' => $perAplicada,
                'saldo_siguiente' => $perSaldoSig,
                'tiene_datos' => ($perSaldoAnt > 0 || $perMes > 0 || $perSaldoSig > 0),
            ];

            // 3. Detracciones SPOT (Abonos cancelados con cargo a cuenta de detracciones BN)
            $abonosMesDetraccion = [];
            $montoDetraccionMes = 0.0;
            if ($op) {
                $todosAbonos = $op->abonosDeuda->concat(
                    $op->deudasTributarias->flatMap->abonos
                )->unique('id');

                foreach ($todosAbonos as $abono) {
                    $medioTexto = strtolower((string) ($abono->medio_pago ?? ''));
                    $obsTexto = strtolower((string) ($abono->observacion ?? ''));

                    $esDetraccion = str_contains($medioTexto, 'detracc')
                        || str_contains($obsTexto, 'detracc')
                        || str_contains($obsTexto, 'cta detraccion')
                        || str_contains($obsTexto, 'cuenta detraccion');

                    if ($esDetraccion) {
                        $monto = (float) $abono->monto;
                        $montoDetraccionMes += $monto;
                        $itemDetraccion = [
                            'id' => $abono->id,
                            'periodo' => $per,
                            'fecha' => $abono->fecha_pago?->format('d/m/Y') ?? '—',
                            'monto' => $monto,
                            'observacion' => $abono->observacion ?: 'Cargo en cuenta de detracciones Banco de la Nación',
                            'tributo' => $abono->deudaTributaria?->tributo_nombre_corto ?? 'Tributo',
                        ];
                        $abonosMesDetraccion[] = $itemDetraccion;
                        $pagosDetraccionesList[] = $itemDetraccion;
                    }
                }
            }
            $totalPagosDetracciones += $montoDetraccionMes;

            $mesesDetracciones[] = [
                'periodo' => $per,
                'etiqueta' => $etiqueta,
                'mes' => $nombreMes,
                'monto' => $montoDetraccionMes,
                'cantidad_pagos' => count($abonosMesDetraccion),
                'pagos' => $abonosMesDetraccion,
                'tiene_datos' => $montoDetraccionMes > 0,
            ];

            // 4. Crédito Fiscal IGV
            $igvSaldoAnt = (float) ($liq?->igv_saldo_favor_anterior ?? 0);
            $igvDebito = (float) ($liq?->igv_ventas ?? 0);
            $igvCredito = (float) ($liq?->igv_compras ?? 0);
            $igvSaldoSig = (float) ($liq?->igv_saldo_favor_siguiente ?? 0);
            if ($liq) {
                $saldoFinalCreditoFiscal = $igvSaldoSig;
            }

            $mesesCreditoFiscal[] = [
                'periodo' => $per,
                'etiqueta' => $etiqueta,
                'mes' => $nombreMes,
                'saldo_anterior' => $igvSaldoAnt,
                'debito_fiscal' => $igvDebito,
                'credito_fiscal' => $igvCredito,
                'saldo_favor_siguiente' => $igvSaldoSig,
                'tiene_datos' => ($igvSaldoAnt > 0 || $igvSaldoSig > 0 || $igvCredito > 0),
            ];

            // 5. Compensación ITAN
            $itanAnt = (float) ($liq?->renta_itan_saldo_anterior ?? 0);
            $itanCompensado = (float) ($liq?->renta_compensacion_itan ?? 0);
            $itanSig = (float) ($liq?->renta_itan_saldo_siguiente ?? 0);
            if ($liq) {
                $saldoFinalItan = $itanSig;
            }

            $mesesItan[] = [
                'periodo' => $per,
                'etiqueta' => $etiqueta,
                'mes' => $nombreMes,
                'saldo_anterior' => $itanAnt,
                'compensado' => $itanCompensado,
                'saldo_siguiente' => $itanSig,
                'tiene_datos' => ($itanAnt > 0 || $itanCompensado > 0 || $itanSig > 0),
            ];
        }

        $aniosDisponibles = Operacion::query()
            ->where('cliente_id', $cliente->id)
            ->pluck('periodo')
            ->map(fn (string $p): int => (int) explode('-', $p)[1])
            ->push($anio, (int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return [
            'anio' => $anio,
            'anios' => $aniosDisponibles,
            'kpis' => [
                'retenciones_acumuladas' => round($totalRetencionesDeclaradas, 2),
                'retenciones_aplicadas' => round($totalRetencionesAplicadas, 2),
                'retenciones_saldo_disponible' => round($saldoFinalRetenciones, 2),
                'percepciones_acumuladas' => round($totalPercepcionesDeclaradas, 2),
                'percepciones_aplicadas' => round($totalPercepcionesAplicadas, 2),
                'percepciones_saldo_disponible' => round($saldoFinalPercepciones, 2),
                'detracciones_total_pagado' => round($totalPagosDetracciones, 2),
                'detracciones_conteo_pagos' => count($pagosDetraccionesList),
                'credito_fiscal_vigente' => round($saldoFinalCreditoFiscal, 2),
                'itan_saldo_disponible' => round($saldoFinalItan, 2),
            ],
            'retenciones' => $mesesRetenciones,
            'percepciones' => $mesesPercepciones,
            'detracciones' => $mesesDetracciones,
            'pagos_detracciones' => $pagosDetraccionesList,
            'credito_fiscal' => $mesesCreditoFiscal,
            'itan' => $mesesItan,
        ];
    }
}
