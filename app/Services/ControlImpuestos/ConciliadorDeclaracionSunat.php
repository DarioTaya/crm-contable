<?php

namespace App\Services\ControlImpuestos;

use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\LiquidacionPeriodo;
use App\Services\Catalogos\CatalogoSunat;
use Carbon\Carbon;

/**
 * Cruza la declaración leída con el cliente, el periodo y los saldos del CRM.
 * Copia las casillas de entrada. El CRM calcula el IGV y la renta y los compara con la DJ.
 */
final class ConciliadorDeclaracionSunat
{
    /**
     * @var array{igv: float|null, renta: float|null}
     */
    private array $controlSunat = ['igv' => null, 'renta' => null];

    /**
     * @var array{igv: float|null, renta: float|null}
     */
    private array $calculoHoja = ['igv' => null, 'renta' => null];

    /**
     * @var array<string, string>
     */
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
        7 => 'Julio', 8 => 'Agosto', 9 => 'Setiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /**
     * @var list<string>
     */
    private const TRIBUTOS_HOJA = ['1011', '1016', '3031', '3042', '3052', '3081', '3111', '3121', '5210', '5310'];

    public function __construct(private CalculadorLiquidacionService $calculador) {}

    /**
     * @param  array{
     *     igv_saldo_favor_anterior: float,
     *     igv_retenciones_saldo_anterior: float,
     *     igv_percepciones_saldo_anterior: float,
     *     renta_saldo_favor_anterior: float,
     *     renta_itan_saldo_anterior: float
     * }  $saldosCrm
     */
    public function conciliar(
        DeclaracionLeida $declaracion,
        Cliente $cliente,
        string $periodoEsperado,
        string $formularioEsperado,
        array $saldosCrm,
    ): ResultadoConciliacion {
        $alertas = [];
        $campos = [];
        $preservar = [];
        $this->controlSunat = ['igv' => null, 'renta' => null];
        $this->calculoHoja = ['igv' => null, 'renta' => null];

        $sinLectura = $declaracion->archivosIlegibles !== []
            && $declaracion->formulario === null
            && $declaracion->casillas === []
            && $declaracion->tributos === [];

        if ($declaracion->archivosIlegibles !== []) {
            $alertas[] = $this->alerta(
                $sinLectura ? 'error' : 'advertencia',
                'pdf_sin_texto',
                'No pude leer el texto de '.implode(', ', $declaracion->archivosIlegibles).'. Descargue de nuevo la constancia desde SOL: el PDF tiene que traer texto, no ser una foto ni un escaneo.',
            );
        }

        if ($sinLectura) {
            return $this->resultado($formularioEsperado, $declaracion, [], [], $alertas, $cliente, $periodoEsperado);
        }

        if ($declaracion->esBoleta && $declaracion->casillas === [] && $declaracion->tributos === []) {
            $alertas[] = $this->alerta(
                'error',
                'es_boleta',
                'Este archivo es una boleta de pago, no la declaración. Suba la constancia de presentación o el detalle de casillas del PDT.',
            );
        }

        if ($declaracion->boletasIgnoradas > 0) {
            $alertas[] = $this->alerta(
                $declaracion->pagos !== [] ? 'info' : 'advertencia',
                'boleta_ignorada',
                $declaracion->pagos !== []
                    ? 'Incorporé '.$declaracion->boletasIgnoradas.' boleta(s) de pago (Formulario 1662) para registrar abonos del periodo.'
                    : 'Dejé fuera un archivo que es boleta de pago. Use la constancia o el detalle de la declaración.',
            );
        }

        if ($declaracion->formulariosMixtos) {
            $alertas[] = $this->alerta(
                'error',
                'formularios_mixtos',
                'Los archivos no son del mismo formulario. Suba el PDT 621 en su hoja y el PDT 601 en la suya.',
            );
        }

        if ($declaracion->rucConflicto || $declaracion->periodoConflicto) {
            $alertas[] = $this->alerta(
                'error',
                'archivos_distintos',
                'Los archivos no corresponden al mismo RUC o al mismo periodo. Súbalos por separado.',
            );
        }

        if ($declaracion->ordenConflicto) {
            $alertas[] = $this->alerta(
                'advertencia',
                'ordenes_distintas',
                'Los archivos traen números de orden distintos. Si uno es la rectificatoria, confirme cuál es el vigente.',
            );
        }

        $formulario = $declaracion->formulario;
        if ($formulario === null) {
            $alertas[] = $this->alerta(
                'error',
                'formulario_desconocido',
                'No reconozco este archivo como constancia o detalle del PDT 621 ni del PDT 601.',
            );
        } elseif ($formulario !== $formularioEsperado) {
            $alertas[] = $this->alerta(
                'error',
                'formulario_distinto',
                $formulario === '621'
                    ? 'Este archivo es del PDT 621. Súbalo en la hoja de IGV y renta, no en PLAME.'
                    : 'Este archivo es del PDT 601 PLAME. Súbalo en la hoja de planilla, no en el PDT 621.',
            );
        }

        $rucCliente = preg_replace('/\D/', '', (string) $cliente->ruc) ?? '';
        if ($declaracion->ruc === null) {
            $alertas[] = $this->alerta('error', 'ruc_ausente', 'No encontré el RUC en el archivo. Suba la constancia oficial de SUNAT.');
        } elseif ($rucCliente !== '' && $declaracion->ruc !== $rucCliente) {
            $alertas[] = $this->alerta(
                'error',
                'ruc_distinto',
                "El RUC de la declaración es {$declaracion->ruc} y el cliente es {$rucCliente}. No cargué nada.",
            );
        }

        if ($declaracion->periodo === null) {
            $alertas[] = $this->alerta('error', 'periodo_ausente', 'No encontré el periodo tributario. Revise que el PDF sea la constancia del mes.');
        } elseif ($declaracion->periodo !== $periodoEsperado) {
            $alertas[] = $this->alerta(
                'error',
                'periodo_distinto',
                'La declaración es de '.$this->etiquetaPeriodo($declaracion->periodo).' y esta hoja es de '.$this->etiquetaPeriodo($periodoEsperado).'. Ábrala en el mes que corresponde.',
            );
        }

        if ($declaracion->casillas === [] && $declaracion->tributos === [] && $declaracion->afp === null && $this->sinErrorDeLectura($alertas)) {
            $alertas[] = $this->alerta(
                'error',
                'sin_datos',
                'El archivo no trae casillas ni importes a pagar. Suba la constancia de presentación y, si la tiene, el detalle de casillas.',
            );
        }

        foreach ($declaracion->casillasRepetidas as $casilla) {
            $alertas[] = $this->alerta(
                'advertencia',
                'casilla_repetida',
                "La casilla {$casilla} figura con montos distintos. Usé el último que aparece en el archivo.",
            );
        }

        foreach ($declaracion->tributosRepetidos as $codigo) {
            $alertas[] = $this->alerta(
                'advertencia',
                'tributo_repetido',
                "El tributo {$codigo} figura con importes distintos. Usé el último.",
            );
        }

        if ($declaracion->tipo === 'rectificatoria') {
            $orden = $declaracion->numeroOrden ? " Orden {$declaracion->numeroOrden}." : '';
            $alertas[] = $this->alerta(
                'advertencia',
                'rectificatoria',
                'Es una declaración rectificatoria. Reemplaza a la original de este periodo.'.$orden,
            );
        } elseif ($declaracion->tipo === 'sustitutoria') {
            $alertas[] = $this->alerta(
                'info',
                'sustitutoria',
                'Es una declaración sustitutoria, presentada dentro del plazo. Queda como la declaración vigente del periodo.',
            );
        }

        $bloqueado = $this->hayError($alertas);
        if (! $bloqueado && $formulario === '621') {
            [$campos, $preservar, $alertas] = $this->mapear621($declaracion, $cliente, $periodoEsperado, $saldosCrm, $campos, $preservar, $alertas);
        }

        if (! $bloqueado && $formulario === '601') {
            [$campos, $alertas] = $this->mapear601($declaracion, $campos, $alertas);
        }

        $bloqueado = $this->hayError($alertas);
        if ($bloqueado) {
            $campos = [];
            $preservar = [];
        } elseif ($declaracion->numeroOrden) {
            $fecha = $declaracion->fechaPresentacion
                ? Carbon::parse($declaracion->fechaPresentacion)->format('d/m/Y H:i')
                : null;
            $campos['notas_liquidacion'] = trim(sprintf(
                'DJ %s orden %s%s',
                $formulario,
                $declaracion->numeroOrden,
                $fecha ? " presentada el {$fecha}" : '',
            ));
        }

        return $this->resultado($formulario ?? $formularioEsperado, $declaracion, $campos, $preservar, $alertas, $cliente, $periodoEsperado);
    }

    /**
     * @param  array<string, float|bool|string|null>  $campos
     * @param  array<string, float|string|null>  $preservar
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     */
    private function resultado(
        string $formulario,
        DeclaracionLeida $declaracion,
        array $campos,
        array $preservar,
        array $alertas,
        Cliente $cliente,
        string $periodoEsperado,
    ): ResultadoConciliacion {
        $regimenFicha = $cliente->regimen_tributario instanceof RegimenTributario
            ? $cliente->regimen_tributario->siglaSunat()
            : strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario ?? ''));

        $periodoRef = $declaracion->periodo ?? $periodoEsperado;
        $anioRef = (int) (explode('-', $periodoRef)[1] ?? date('Y'));
        $tope300Uit = self::tope300Uit($anioRef);
        $ventasAcumuladas = (float) LiquidacionPeriodo::query()
            ->where('cliente_id', $cliente->id)
            ->where('periodo', 'like', "%-{$anioRef}")
            ->sum('ventas_base');
        $ventasEsteMes = (float) ($campos['ventas_base'] ?? 0);
        $totalVentasAnual = $ventasAcumuladas + $ventasEsteMes;
        $supero300Uit = $totalVentasAnual > $tope300Uit;

        $tasaDj = $declaracion->casillas['315'] ?? null;
        $tributoRentaDj = $campos['renta_tributo_codigo'] ?? ($preservar['renta_tributo_codigo'] ?? null);

        $regimenDj = match (true) {
            $tributoRentaDj === '3111' => 'RER',
            $tributoRentaDj === '3121' => 'RMT',
            $tributoRentaDj === '3031' => 'RG',
            $tasaDj !== null && abs($tasaDj - 1.0) < 0.06 => 'RMT',
            $tasaDj !== null && abs($tasaDj - 1.5) < 0.06 => match ($regimenFicha) {
                'RMT' => 'RMT',
                'RG', 'GENERAL' => 'RG',
                'RER' => 'RER',
                default => ($supero300Uit ? 'RMT' : 'RER'),
            },
            $tasaDj !== null && $regimenFicha === 'RMT' => 'RMT',
            $tasaDj !== null && in_array($regimenFicha, ['RG', 'GENERAL'], true) => 'RG',
            default => null,
        };

        if ($formulario === '621' && ($regimenFicha === 'RMT' || $regimenDj === 'RMT')) {
            if ($supero300Uit || ($tasaDj !== null && abs($tasaDj - 1.5) < 0.06)) {
                $alertas[] = $this->alerta(
                    'info',
                    'rmt_300_uit_superado',
                    'Régimen MYPE Tributario (Art. 6 D. Leg. 1269): Ingresos anuales acumulados (S/ '.number_format($totalVentasAnual, 2).') '.($supero300Uit ? 'superan las 300 UIT (S/ '.number_format($tope300Uit, 2).')' : 'en evaluación').'. Corresponde legalmente aplicar la tasa del '.($tasaDj ? $this->formatearTasa($tasaDj).'%' : '1.5% o coeficiente').' para el pago a cuenta mensual.',
                );
            }
        }

        $regimenDiscrepancia = null;
        if ($regimenDj !== null && $regimenFicha !== '' && $regimenDj !== $regimenFicha) {
            $jerarquia = ['RUS' => 1, 'NRUS' => 1, 'RER' => 2, 'RMT' => 3, 'RG' => 4, 'GENERAL' => 4];
            $nivelFicha = $jerarquia[$regimenFicha] ?? 0;
            $nivelDj = $jerarquia[$regimenDj] ?? 0;
            $esAscenso = $nivelDj > $nivelFicha;
            $esDescenso = $nivelDj < $nivelFicha;
            $esEnero = str_starts_with($periodoRef, '01-');
            $puedeCambiarFicha = $esAscenso || ($esDescenso && $esEnero);

            $motivo = match (true) {
                $esAscenso => "El cliente ascendió de {$regimenFicha} a {$regimenDj} en el periodo {$this->etiquetaPeriodo($periodoRef)}. El pase a un régimen superior procede en cualquier mes del año (Art. 119 LIR / D. Leg. 1269).",
                $esDescenso && $esEnero => "Cambio a régimen menor ({$regimenFicha} → {$regimenDj}) procedente únicamente con la declaración de Enero (Art. 121 LIR).",
                $esDescenso && ! $esEnero => "La Ficha actual es {$regimenFicha}. Conforme al Art. 121 del TUO de la LIR, no es procedente descender de régimen a {$regimenDj} en {$this->etiquetaPeriodo($periodoRef)} (solo se permite en Enero). En este periodo el cliente tributó bajo {$regimenDj} antes de ascender a {$regimenFicha}. Se aplican la tasa y tributo declarados en la DJ sin modificar la Ficha maestra.",
                default => "Discrepancia entre la Ficha ({$regimenFicha}) y la DJ ({$regimenDj}).",
            };

            $regimenDiscrepancia = [
                'ficha' => $regimenFicha,
                'dj' => $regimenDj,
                'tasa_dj' => $tasaDj,
                'tributo_sugerido' => match ($regimenDj) {
                    'RER' => '3111',
                    'RMT' => '3121',
                    'RG' => '3031',
                    default => null,
                },
                'es_ascenso' => $esAscenso,
                'es_descenso' => $esDescenso,
                'es_enero' => $esEnero,
                'puede_cambiar_ficha' => $puedeCambiarFicha,
                'mensaje_normativo' => $motivo,
            ];
        }

        if ($regimenDj === 'RER' && $regimenFicha === 'RER' && $formulario === '621') {
            if ($totalVentasAnual > 525000.00) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'limite_rer_excedido',
                    'Las ventas anuales estimadas/acumuladas (S/ '.number_format($totalVentasAnual, 2).') superan el tope legal de S/ 525,000.00 del RER (Art. 119 LIR). La empresa debe tributar en el Régimen MYPE Tributario (RMT) o General.',
                );
            }
        }

        return new ResultadoConciliacion(
            bloqueado: $this->hayError($alertas),
            formulario: $formulario,
            campos: $campos,
            preservar: $preservar,
            alertas: $alertas,
            identidad: [
                'ruc' => $declaracion->ruc,
                'razon_social' => $declaracion->razonSocial,
                'periodo' => $declaracion->periodo,
                'periodo_etiqueta' => $declaracion->periodo ? $this->etiquetaPeriodo($declaracion->periodo) : null,
                'numero_orden' => $declaracion->numeroOrden,
                'fecha_presentacion' => $declaracion->fechaPresentacion
                    ? Carbon::parse($declaracion->fechaPresentacion)->format('d/m/Y H:i')
                    : null,
                'tipo' => $declaracion->tipo,
                'tipo_etiqueta' => match ($declaracion->tipo) {
                    'rectificatoria' => 'Rectificatoria',
                    'sustitutoria' => 'Sustitutoria',
                    default => 'Original',
                },
            ],
            control: $this->controlSunat,
            calculo: $this->calculoHoja,
            pagos: $declaracion->pagos,
            regimenDiscrepancia: $regimenDiscrepancia,
        );
    }

    /**
     * @param  array<string, float|bool|string|null>  $campos
     * @param  array<string, float|string|null>  $preservar
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     * @param  array<string, float>  $saldosCrm
     * @return array{0: array<string, float|bool|string|null>, 1: array<string, float|string|null>, 2: list<array{nivel: string, codigo: string, mensaje: string}>}
     */
    private function mapear621(
        DeclaracionLeida $declaracion,
        Cliente $cliente,
        string $periodoEsperado,
        array $saldosCrm,
        array $campos,
        array $preservar,
        array $alertas,
    ): array {
        $casillas = $declaracion->casillas;
        $tributos = $declaracion->tributos;
        $hayDetalle = isset($casillas['100']) || isset($casillas['101']) || isset($casillas['107']) || isset($casillas['301']) || isset($casillas['140']);

        if (! $hayDetalle && $tributos !== []) {
            $alertas[] = $this->alerta(
                'info',
                'solo_constancia',
                'Solo constancia: faltan las casillas. Suba el detalle del PDT 621 para calcular.',
            );
        }

        if (($casillas['160'] ?? 0) > 0 && ($casillas['101'] ?? 0) == 0.0) {
            $campos['es_zona_selva'] = true;
            $campos['ventas_base'] = ($casillas['100'] ?? 0) > 0 ? $casillas['100'] : $casillas['160'];
            $campos['igv_ventas'] = 0.0;
            $campos['igv_compras'] = 0.0;
            $alertas[] = $this->alerta(
                'info',
                'amazonia',
                'La casilla 160 trae ventas de Amazonía y no hay débito fiscal. Activé el régimen de la Ley 27037.',
            );
        } elseif (($casillas['101'] ?? 0) > 0) {
            $campos['es_zona_selva'] = false;
        }

        $mapa = [
            '100' => 'ventas_base',
            '101' => 'igv_ventas',
            '102' => 'igv_descuentos_base',
            '103' => 'igv_descuentos',
            '105' => 'ventas_no_gravadas',
            '107' => 'compras_base',
            '108' => 'igv_compras',
            '110' => 'igv_compras_mixtas_base',
            '111' => 'igv_compras_mixtas',
            '113' => 'compras_destino_no_gravadas',
            '114' => 'igv_importaciones_base',
            '115' => 'igv_importaciones',
            '116' => 'igv_importaciones_mixtas_base',
            '117' => 'igv_importaciones_mixtas',
            '119' => 'importaciones_destino_no_gravadas',
            '120' => 'compras_internas_no_gravadas',
            '122' => 'compras_importadas_no_gravadas',
            '154' => 'igv_ventas_ley_base',
            '155' => 'igv_ventas_ley',
            '156' => 'igv_compras_ley_base',
            '157' => 'igv_compras_ley',
            '179' => 'igv_retenciones',
            '171' => 'igv_percepciones',
            '328' => 'renta_compensacion_itan',
        ];

        if ($hayDetalle) {
            foreach ($mapa as $casilla => $campo) {
                if (array_key_exists($casilla, $casillas)) {
                    $campos[$campo] = $casillas[$casilla];
                }
            }

            $ingresos = max(0, round(($casillas['100'] ?? 0) - ($casillas['102'] ?? 0) + ($casillas['105'] ?? 0), 2));
            if ($ingresos > 0 || isset($casillas['301'])) {
                $campos['renta_base_ingresos'] = $ingresos;
            }
            if (isset($casillas['301']) && ! MontoTributario::iguales($ingresos, $casillas['301'], 1)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'ingresos_distintos',
                    'Ingresos: ventas menos descuentos dan S/ '.number_format($ingresos, 2).'. La DJ (casilla 301) dice S/ '.number_format($casillas['301'], 2).'. Calculé con S/ '.number_format($ingresos, 2).'.',
                );
            }

            if (isset($casillas['315']) && $casillas['315'] > 0) {
                $campos['renta_tasa'] = round($casillas['315'], 4);
                $esperada = $this->tasaDeLaFicha($cliente, $periodoEsperado, (float) ($campos['ventas_base'] ?? 0));
                if ($esperada !== null && abs($campos['renta_tasa'] - $esperada) > 0.05) {
                    $alertas[] = $this->alerta(
                        'advertencia',
                        'tasa_distinta',
                        'La ficha es '.$this->nombreRegimen($cliente).' '.$this->formatearTasa($esperada).'%. La DJ usó '.$this->formatearTasa($campos['renta_tasa']).'%. El pago a cuenta sigue la tasa de la DJ.',
                    );
                }
            }

            $retenciones = ($casillas['326'] ?? 0) + ($casillas['327'] ?? 0);
            if (isset($casillas['326']) || isset($casillas['327'])) {
                $campos['renta_retenciones'] = round($retenciones, 2);
            }

            $this->aplicarSaldo($campos, $alertas, $saldosCrm, 'igv_saldo_favor_anterior', '145', $casillas, 'igv_saldo_favor_anterior', 'Saldo a favor de IGV', $periodoEsperado);
            $this->aplicarSaldo($campos, $alertas, $saldosCrm, 'igv_retenciones_saldo_anterior', '176', $casillas, 'igv_retenciones_saldo_anterior', 'Retenciones de IGV', $periodoEsperado);
            $this->aplicarSaldo($campos, $alertas, $saldosCrm, 'igv_percepciones_saldo_anterior', '168', $casillas, 'igv_percepciones_saldo_anterior', 'Percepciones de IGV', $periodoEsperado);
            $this->aplicarSaldo($campos, $alertas, $saldosCrm, 'renta_saldo_favor_anterior', '303', $casillas, 'renta_saldo_favor_anterior', 'Saldo a favor de renta', $periodoEsperado);
        }

        if (isset($casillas['100'], $casillas['101']) && ($campos['es_zona_selva'] ?? false) !== true && $this->desvia($casillas['100'] * 0.18, $casillas['101'])) {
            $alertas[] = $this->alerta(
                'advertencia',
                'igv_ventas',
                'Casilla 101: S/ '.number_format($casillas['101'], 2).'. El 18% de la casilla 100 sería S/ '.number_format($casillas['100'] * 0.18, 2).'.',
            );
        }

        if (isset($casillas['102'], $casillas['103']) && $casillas['102'] > 0 && $this->desvia($casillas['102'] * 0.18, $casillas['103'])) {
            $alertas[] = $this->alerta(
                'advertencia',
                'igv_descuentos',
                'Casilla 103: S/ '.number_format($casillas['103'], 2).'. El 18% de los descuentos (casilla 102) sería S/ '.number_format($casillas['102'] * 0.18, 2).'.',
            );
        }

        if (isset($casillas['107'], $casillas['108']) && $this->desvia($casillas['107'] * 0.18, $casillas['108'])) {
            $alertas[] = $this->alerta(
                'advertencia',
                'igv_compras',
                'Casilla 108: S/ '.number_format($casillas['108'], 2).'. El 18% de la casilla 107 sería S/ '.number_format($casillas['107'] * 0.18, 2).'.',
            );
        }

        if (isset($tributos['1016'])) {
            $preservar['tributo_1016'] = $tributos['1016'];
        }

        if (($casillas['185'] ?? 0) > 0) {
            $alertas[] = $this->alerta(
                'info',
                'pagos_previos',
                'La DJ tiene pagos previos (casilla 185) por S/ '.number_format($casillas['185'], 2).'.',
            );
        }

        if (($casillas['187'] ?? 0) > 0) {
            $alertas[] = $this->alerta(
                'info',
                'interes_moratorio',
                'La DJ incluye interés moratorio de S/ '.number_format($casillas['187'], 2).'.',
            );
        }

        if (isset($casillas['328']) && $casillas['328'] > ($saldosCrm['renta_itan_saldo_anterior'] ?? 0) + 0.05) {
            $alertas[] = $this->alerta(
                'advertencia',
                'itan_sin_saldo',
                'ITAN de la DJ: S/ '.number_format($casillas['328'], 2).'. El CRM no tenía ese saldo. Lo desconté como figura en la DJ.',
            );
        }

        $regimen = strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario));
        if ($regimen === 'RER' && ($casillas['328'] ?? 0) > 0) {
            $alertas[] = $this->alerta(
                'advertencia',
                'itan_en_rer',
                'La ficha es RER y la DJ compensó ITAN por S/ '.number_format($casillas['328'], 2).'.',
            );
        }

        if ($declaracion->igvJusto) {
            $alertas[] = $this->alerta(
                'info',
                'igv_justo',
                'La DJ marcó IGV Justo. El vencimiento del IGV se prorroga.',
            );
        }

        $pagarIgv = $this->importeIgv($casillas, $tributos);
        $codigoRenta = $this->codigoRenta($casillas, $tributos, $cliente, $alertas);
        $pagarRenta = $this->importeRenta($casillas, $tributos, $codigoRenta);
        $this->controlSunat = ['igv' => $pagarIgv, 'renta' => $pagarRenta];

        if (isset($casillas['189'], $tributos['1011']) && ! MontoTributario::iguales($casillas['189'], $tributos['1011'])) {
            $alertas[] = $this->alerta(
                'error',
                'igv_no_cuadra',
                'La casilla 189 (S/ '.number_format($casillas['189'], 2).') no coincide con el tributo 1011 (S/ '.number_format($tributos['1011'], 2).'). No cargué la hoja.',
            );
        }

        if ($codigoRenta) {
            $preservar['renta_tributo_codigo'] = $codigoRenta;
        }

        if ($hayDetalle) {
            $this->contrastarMotor($campos, $pagarIgv, $pagarRenta, $cliente, $alertas, $declaracion);
        }

        foreach ($tributos as $codigo => $monto) {
            $codigo = (string) $codigo;
            if ($monto > 0 && ! in_array($codigo, self::TRIBUTOS_HOJA, true)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'tributo_no_gestionado',
                    "La declaración trae el tributo {$codigo} por S/ ".number_format($monto, 2).'. Esa obligación no se carga sola en esta hoja.',
                );
            }
        }

        $regimen = strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario));
        if (in_array($regimen, ['RUS', 'NRUS'], true)) {
            $alertas[] = $this->alerta(
                'advertencia',
                'nrus',
                'El cliente está en NRUS y ese régimen no presenta PDT 621. Igual leí el archivo porque usted lo subió.',
            );
        }

        return [$campos, $preservar, $alertas];
    }

    /**
     * @param  array<string, float|bool|string|null>  $campos
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     * @return array{0: array<string, float|bool|string|null>, 1: list<array{nivel: string, codigo: string, mensaje: string}>}
     */
    private function mapear601(DeclaracionLeida $declaracion, array $campos, array $alertas): array
    {
        $tributos = $declaracion->tributos;

        if (isset($tributos['5210'])) {
            $campos['plame_essalud_a_pagar'] = $tributos['5210'];
        }
        if ($declaracion->essaludBase !== null) {
            $campos['plame_essalud_base'] = $declaracion->essaludBase;
            $aporte = $tributos['5210'] ?? null;
            if ($aporte !== null && $declaracion->essaludBase > 0 && $this->desvia($declaracion->essaludBase * 0.09, $aporte)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'essalud_tasa',
                    'El EsSalud declarado no es el 9% de la base que trae el archivo. Revise remuneración asegurable, subsidios o el piso de la RMV.',
                );
            }
        }
        if (isset($tributos['5210']) && $tributos['5210'] > 0 && $tributos['5210'] + 0.001 < CalculadorLiquidacionService::ESSALUD_MINIMO) {
            $alertas[] = $this->alerta(
                'advertencia',
                'essalud_minimo',
                'El EsSalud a pagar es menor que el piso legal de S/ '.number_format(CalculadorLiquidacionService::ESSALUD_MINIMO, 2).' (9% de la RMV). Confirme si corresponde una excepción.',
            );
        }

        if (isset($tributos['5310'])) {
            $campos['plame_onp_a_pagar'] = $tributos['5310'];
        }
        if (isset($tributos['3042'])) {
            $campos['plame_renta_4ta_a_pagar'] = $tributos['3042'];
        }
        if ($declaracion->renta4taBase !== null) {
            $campos['plame_renta_4ta_base'] = $declaracion->renta4taBase;
            $retencion = $tributos['3042'] ?? null;
            if ($retencion !== null && $declaracion->renta4taBase > 0 && $this->desvia($declaracion->renta4taBase * 0.08, $retencion)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'renta_4ta_tasa',
                    'La retención de 4ta categoría no es el 8% de la base de honorarios. Revise el monto antes de guardar.',
                );
            }
        }
        if (isset($tributos['3052'])) {
            $campos['plame_renta_5ta_a_pagar'] = $tributos['3052'];
        }

        if ($declaracion->afp !== null) {
            $campos['plame_afp_a_pagar'] = $declaracion->afp;
        } elseif ($tributos !== []) {
            $alertas[] = $this->alerta(
                'info',
                'afp_ausente',
                'La constancia de SUNAT no incluye AFP. Ese aporte se paga en AFPNet y no modifiqué el monto de AFP que ya tenía la hoja.',
            );
        }

        foreach ($tributos as $codigo => $monto) {
            $codigo = (string) $codigo;
            if ($monto > 0 && ! in_array($codigo, ['5210', '5310', '3042', '3052'], true)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'tributo_plame_extra',
                    "La PLAME trae el concepto {$codigo} por S/ ".number_format($monto, 2).'. No entra a EsSalud, ONP ni a las retenciones de 4ta y 5ta.',
                );
            }
        }

        if ($campos === [] && $declaracion->afp === null) {
            $alertas[] = $this->alerta(
                'error',
                'plame_sin_importes',
                'No encontré EsSalud, ONP ni retenciones en este archivo. Suba la constancia de la declaración del PDT 601, no solo la constancia informativa de la planilla.',
            );
        }

        return [$campos, $alertas];
    }

    /**
     * @param  array<string, float>  $casillas
     * @param  array<string, float>  $tributos
     */
    private function importeIgv(array $casillas, array $tributos): ?float
    {
        if (isset($casillas['188'])) {
            return max(0, $casillas['188']);
        }

        if (isset($casillas['189'])) {
            return max(0, $casillas['189']);
        }

        if (isset($tributos['1011'])) {
            return $tributos['1011'];
        }

        return null;
    }

    /**
     * @param  array<string, float>  $casillas
     */
    private function saldoFavorIgv(array $casillas): ?float
    {
        if (($casillas['184'] ?? 0) < 0) {
            return round(abs($casillas['184']), 2);
        }

        $neto = null;
        if (isset($casillas['140'])) {
            $neto = round($casillas['140'] - ($casillas['145'] ?? 0), 2);
        } elseif (isset($casillas['101']) || isset($casillas['108'])) {
            $neto = round(($casillas['101'] ?? 0) - ($casillas['108'] ?? 0) - ($casillas['145'] ?? 0), 2);
        }

        if ($neto === null) {
            return null;
        }

        return $neto < 0 ? round(abs($neto), 2) : 0.0;
    }

    /**
     * @param  array<string, float>  $casillas
     * @param  array<string, float>  $tributos
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     */
    private function codigoRenta(array $casillas, array $tributos, Cliente $cliente, array &$alertas): ?string
    {
        $presentes = [];
        foreach (['3111', '3121', '3031', '3081'] as $codigo) {
            if (($tributos[$codigo] ?? 0) > 0) {
                $presentes[] = $codigo;
            }
        }

        if ($presentes === []) {
            foreach (['3111', '3121', '3031', '3081'] as $codigo) {
                if (array_key_exists($codigo, $tributos)) {
                    $presentes[] = $codigo;
                }
            }
        }

        if (count($presentes) > 1) {
            $alertas[] = $this->alerta(
                'advertencia',
                'varios_renta',
                'La declaración trae más de un tributo de renta ('.implode(', ', $presentes).'). Usé '.$presentes[0].' como pago a cuenta del mes.',
            );
        }

        $codigo = $presentes[0] ?? $this->codigoRentaPorTasa($casillas, $cliente);
        $esperado = app(CatalogoSunat::class)->codigoRentaDelRegimen($cliente->regimen_tributario, $cliente->estudio_id);
        $yaAvisoLaTasa = collect($alertas)->contains(fn (array $alerta): bool => $alerta['codigo'] === 'tasa_distinta');
        if ($codigo && $codigo !== '3081' && $esperado && $codigo !== $esperado && ! $yaAvisoLaTasa) {
            $alertas[] = $this->alerta(
                'advertencia',
                'regimen_distinto',
                "La declaración usa el tributo {$codigo} y el régimen del cliente corresponde al {$esperado}. Guardaré el código que declaró en SUNAT.",
            );
        }

        if ($codigo === '3081') {
            $alertas[] = $this->alerta(
                'advertencia',
                'regularizacion',
                'El tributo 3081 es la regularización anual del impuesto a la renta, no el pago a cuenta mensual.',
            );
        }

        return $codigo;
    }

    /**
     * El detalle del PDT 621 no siempre imprime el código 3111/3121. La tasa del pago a cuenta sí:
     * - En RMT es 1.0 % (<=300 UIT) o 1.5 % / coeficiente (>300 UIT - Art. 6 D. Leg. 1269). Código: 3121.
     * - En RER es 1.5 % (Art. 120 LIR). Código: 3111.
     * - En RG es coeficiente o 1.5 % (Art. 85 LIR). Código: 3031.
     *
     * @param  array<string, float>  $casillas
     */
    private function codigoRentaPorTasa(array $casillas, ?Cliente $cliente = null): ?string
    {
        if (isset($casillas['315']) && $casillas['315'] > 0) {
            $tasa = round($casillas['315'], 2);
        } elseif (isset($casillas['301'], $casillas['312']) && $casillas['301'] > 0) {
            $tasa = round($casillas['312'] / $casillas['301'] * 100, 2);
        } else {
            return null;
        }

        $regimen = $cliente ? strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario)) : '';

        if ($regimen === 'RMT') {
            return '3121';
        }

        if (in_array($regimen, ['RG', 'GENERAL'], true)) {
            return '3031';
        }

        if ($regimen === 'RER') {
            return abs($tasa - 1.0) < 0.06 ? '3121' : '3111';
        }

        return match (true) {
            abs($tasa - 1.0) < 0.06 => '3121',
            abs($tasa - 1.5) < 0.06 => '3111',
            default => '3121',
        };
    }

    /**
     * @param  array<string, float>  $casillas
     * @param  array<string, float>  $tributos
     */
    private function importeRenta(array $casillas, array $tributos, ?string $codigo): ?float
    {
        if (isset($casillas['307'])) {
            return max(0, $casillas['307']);
        }

        if (isset($casillas['324'])) {
            return max(0, $casillas['324']);
        }

        if ($codigo && isset($tributos[$codigo])) {
            return $tributos[$codigo];
        }

        if (isset($casillas['304']) && $casillas['304'] > 0) {
            return $casillas['304'];
        }

        return null;
    }

    /**
     * @param  array<string, float|bool|string|null>  $campos
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     */
    private function contrastarMotor(array $campos, ?float $pagarIgv, ?float $pagarRenta, Cliente $cliente, array &$alertas, ?DeclaracionLeida $declaracion = null): void
    {
        $borrador = new LiquidacionPeriodo;
        foreach ($campos as $campo => $valor) {
            if (is_bool($valor) || is_numeric($valor)) {
                $borrador->{$campo} = $valor;
            }
        }

        $this->calculador->determinar($borrador, $cliente);
        $this->calculoHoja = [
            'igv' => round((float) $borrador->igv_resultante_pagar, 2),
            'renta' => round((float) $borrador->renta_resultante_pagar, 2),
        ];

        if ($pagarIgv !== null && ! MontoTributario::iguales((float) $borrador->igv_resultante_pagar, $pagarIgv, 0.05)) {
            $motivos = [];
            $saldoAntCrm = (float) ($campos['igv_saldo_favor_anterior'] ?? 0);
            $saldoAntDj = (float) ($declaracion?->casillas['145'] ?? 0);
            if (abs($saldoAntCrm - $saldoAntDj) > 0.05) {
                $motivos[] = 'Saldo a favor anterior (CRM S/ '.number_format($saldoAntCrm, 2).' vs DJ Cas. 145 S/ '.number_format($saldoAntDj, 2).')';
            }
            $retCrm = (float) ($campos['igv_retenciones'] ?? 0) + (float) ($campos['igv_retenciones_saldo_anterior'] ?? 0);
            $retDj = (float) ($declaracion?->casillas['179'] ?? 0) + (float) ($declaracion?->casillas['176'] ?? 0);
            if (abs($retCrm - $retDj) > 0.05) {
                $motivos[] = 'Retenciones (CRM S/ '.number_format($retCrm, 2).' vs DJ S/ '.number_format($retDj, 2).')';
            }
            $percCrm = (float) ($campos['igv_percepciones'] ?? 0) + (float) ($campos['igv_percepciones_saldo_anterior'] ?? 0);
            $percDj = (float) ($declaracion?->casillas['171'] ?? 0) + (float) ($declaracion?->casillas['168'] ?? 0);
            if (abs($percCrm - $percDj) > 0.05) {
                $motivos[] = 'Percepciones (CRM S/ '.number_format($percCrm, 2).' vs DJ S/ '.number_format($percDj, 2).')';
            }
            if (($declaracion?->casillas['185'] ?? 0) > 0) {
                $motivos[] = 'Pagos previos en DJ (Cas. 185: S/ '.number_format($declaracion->casillas['185'], 2).')';
            }
            if (($declaracion?->casillas['187'] ?? 0) > 0) {
                $motivos[] = 'Interés moratorio en DJ (Cas. 187: S/ '.number_format($declaracion->casillas['187'], 2).')';
            }

            $detalle = $motivos !== [] ? ' Detalle de diferencias: '.implode('; ', $motivos).'.' : '';
            $alertas[] = $this->alerta(
                'advertencia',
                'igv_distinto_al_motor',
                'IGV calculado por el CRM: S/ '.number_format((float) $borrador->igv_resultante_pagar, 2).'. La DJ oficial de SUNAT indica S/ '.number_format($pagarIgv, 2).'.'.$detalle,
            );
        }

        if ($pagarRenta !== null && ! MontoTributario::iguales((float) $borrador->renta_resultante_pagar, $pagarRenta, 0.05)) {
            $motivosRenta = [];
            $saldoRentaCrm = (float) ($campos['renta_saldo_favor_anterior'] ?? 0);
            $saldoRentaDj = (float) ($declaracion?->casillas['303'] ?? 0);
            if (abs($saldoRentaCrm - $saldoRentaDj) > 0.05) {
                $motivosRenta[] = 'Saldo a favor anterior (CRM S/ '.number_format($saldoRentaCrm, 2).' vs DJ Cas. 303 S/ '.number_format($saldoRentaDj, 2).')';
            }
            $itanCrm = (float) ($campos['renta_compensacion_itan'] ?? 0);
            $itanDj = (float) ($declaracion?->casillas['328'] ?? 0);
            if (abs($itanCrm - $itanDj) > 0.05) {
                $motivosRenta[] = 'Compensación ITAN (CRM S/ '.number_format($itanCrm, 2).' vs DJ Cas. 328 S/ '.number_format($itanDj, 2).')';
            }
            if (($declaracion?->casillas['317'] ?? 0) > 0) {
                $motivosRenta[] = 'Pagos previos en DJ (Cas. 317: S/ '.number_format($declaracion->casillas['317'], 2).')';
            }

            $detalleRenta = $motivosRenta !== [] ? ' Detalle de diferencias: '.implode('; ', $motivosRenta).'.' : '';
            $alertas[] = $this->alerta(
                'advertencia',
                'renta_distinta_al_motor',
                'Renta calculada por el CRM: S/ '.number_format((float) $borrador->renta_resultante_pagar, 2).'. La DJ oficial de SUNAT indica S/ '.number_format($pagarRenta, 2).'.'.$detalleRenta,
            );
        }
    }

    /**
     * El saldo de la hoja sale del CRM cuando ya hay mes anterior. La DJ solo se usa si no hay hoja previa.
     *
     * @param  array<string, float|bool|string|null>  $campos
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     * @param  array<string, float|bool>  $saldosCrm
     * @param  array<string, float>  $casillas
     */
    private function aplicarSaldo(
        array &$campos,
        array &$alertas,
        array $saldosCrm,
        string $campo,
        string $casilla,
        array $casillas,
        string $claveCrm,
        string $nombre,
        string $periodo,
    ): void {
        $crm = round((float) ($saldosCrm[$claveCrm] ?? 0), 2);
        $hayHoja = (bool) ($saldosCrm['hay_liquidacion_anterior'] ?? false);
        $dj = array_key_exists($casilla, $casillas) ? round((float) $casillas[$casilla], 2) : null;
        $mes = (int) explode('-', $periodo)[0];

        if ($campo === 'renta_saldo_favor_anterior' && $mes === 1 && ! $hayHoja && ($dj ?? 0) > 0) {
            $campos[$campo] = 0.0;
            $alertas[] = $this->alerta(
                'info',
                'saldo_anual',
                'La DJ trae saldo de renta (casilla 303) por S/ '.number_format($dj, 2).'. Enero no lo arrastra: no hay hoja previa en el CRM.',
            );

            return;
        }

        if ($hayHoja) {
            $campos[$campo] = $crm;
            if ($dj !== null && ! MontoTributario::iguales($dj, $crm)) {
                $alertas[] = $this->alerta(
                    'advertencia',
                    'saldo_'.$casilla,
                    $nombre.': el CRM arrastra S/ '.number_format($crm, 2).'. La DJ (casilla '.$casilla.') dice S/ '.number_format($dj, 2).'. Calculé con el CRM.',
                );
            }

            return;
        }

        if ($dj !== null && $dj > 0) {
            $campos[$campo] = $dj;
            $alertas[] = $this->alerta(
                'info',
                'saldo_'.$casilla,
                $nombre.': no hay hoja del mes anterior. Usé el saldo de la DJ, S/ '.number_format($dj, 2).'.',
            );

            return;
        }

        $campos[$campo] = $crm;
    }

    public static function uitDelAnio(int $anio): float
    {
        return match ($anio) {
            2021 => 4400.0,
            2022 => 4600.0,
            2023 => 4950.0,
            2024 => 5150.0,
            2025 => 5350.0,
            2026 => 5500.0,
            default => 5500.0,
        };
    }

    public static function tope300Uit(int $anio): float
    {
        return self::uitDelAnio($anio) * 300;
    }

    private function tasaDeLaFicha(Cliente $cliente, ?string $periodo = null, float $ventasEsteMes = 0.0): ?float
    {
        $regimen = strtoupper((string) ($cliente->regimen_tributario?->value ?? $cliente->regimen_tributario));

        if ($regimen === 'RER') {
            return 1.5;
        }

        if ($regimen === 'RMT') {
            $anio = $periodo ? (int) (explode('-', $periodo)[1] ?? date('Y')) : (int) date('Y');
            $tope = self::tope300Uit($anio);
            $ventasAcum = (float) LiquidacionPeriodo::query()
                ->where('cliente_id', $cliente->id)
                ->where('periodo', 'like', "%-{$anio}")
                ->sum('ventas_base');
            $totalAnual = $ventasAcum + $ventasEsteMes;

            return $totalAnual > $tope ? 1.5 : 1.0;
        }

        if (in_array($regimen, ['RG', 'GENERAL'], true)) {
            return 1.5;
        }

        return null;
    }

    private function nombreRegimen(Cliente $cliente): string
    {
        $regimen = $cliente->regimen_tributario;

        return $regimen instanceof RegimenTributario ? $regimen->siglaSunat() : strtoupper((string) $regimen);
    }

    private function formatearTasa(float $tasa): string
    {
        return rtrim(rtrim(number_format($tasa, 2, '.', ''), '0'), '.');
    }

    private function desvia(float $esperado, float $real): bool
    {
        return abs($esperado - $real) > max(1.0, abs($esperado) * 0.005);
    }

    /**
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     */
    private function hayError(array $alertas): bool
    {
        foreach ($alertas as $alerta) {
            if ($alerta['nivel'] === 'error') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{nivel: string, codigo: string, mensaje: string}>  $alertas
     */
    private function sinErrorDeLectura(array $alertas): bool
    {
        foreach ($alertas as $alerta) {
            if (in_array($alerta['codigo'], ['pdf_sin_texto', 'es_boleta', 'formulario_desconocido'], true)) {
                return false;
            }
        }

        return true;
    }

    private function etiquetaPeriodo(string $periodo): string
    {
        [$mes, $anio] = explode('-', $periodo);

        return (self::MESES[(int) $mes] ?? $periodo).' '.$anio;
    }

    /**
     * @return array{nivel: string, codigo: string, mensaje: string}
     */
    private function alerta(string $nivel, string $codigo, string $mensaje): array
    {
        return [
            'nivel' => $nivel,
            'codigo' => $codigo,
            'mensaje' => $mensaje,
        ];
    }
}
