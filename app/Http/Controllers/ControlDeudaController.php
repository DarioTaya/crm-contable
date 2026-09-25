<?php

namespace App\Http\Controllers;

use App\Models\DeudaTributaria;
use App\Models\Operacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class ControlDeudaController extends Controller
{
    /**
     * Dashboard de Control de Deudas pendientes con SUNAT.
     */
    public function index(Request $request): View|JsonResponse
    {
        $clientesParam = $request->input('clientes');
        $clienteIds = null;
        if (! empty($clientesParam)) {
            $clienteIds = is_array($clientesParam) ? $clientesParam : explode(',', (string) $clientesParam);
            $clienteIds = array_map('intval', array_filter($clienteIds));
        }

        $todasDeudas = $this->filasDeuda($clienteIds);
        $deudas = $todasDeudas->filter(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente > 0)->values();

        $totalDeudaHistorica = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->monto_deuda_inicial);
        $totalRecaudado = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->total_abonado);
        $totalSaldoPorCobrar = (float) $todasDeudas->sum(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente);

        $todasLasDeudasSinFiltro = $this->filasDeuda(null)
            ->filter(fn (DeudaTributaria|Operacion $fila) => (float) $fila->saldo_pendiente > 0);

        $clientesConDeuda = $todasLasDeudasSinFiltro->map(fn (DeudaTributaria|Operacion $fila) => $fila->cliente)
            ->filter()
            ->unique('id')
            ->map(function ($cli) use ($todasLasDeudasSinFiltro) {
                return [
                    'id' => $cli->id,
                    'numero' => $cli->numero ?? $cli->id,
                    'numero_formateado' => (string) ($cli->numero ?? $cli->id),
                    'razon_social' => $cli->razon_social,
                    'ruc' => $cli->ruc,
                    'total_deudas' => $todasLasDeudasSinFiltro->filter(function (DeudaTributaria|Operacion $fila) use ($cli) {
                        $clienteId = $fila instanceof Operacion ? $fila->cliente_id : $fila->operacion?->cliente_id;

                        return (int) $clienteId === (int) $cli->id;
                    })->count(),
                ];
            })
            ->sortBy('razon_social')
            ->values();

        // Mapeo detallado para reactividad en Alpine.js
        $deudasData = $deudas->map(function (DeudaTributaria|Operacion $op) {
            $numCliente = $op->cliente ? ($op->cliente->numero ?? $op->cliente->id) : 0;
            $clienteId = $op instanceof Operacion ? $op->cliente_id : $op->operacion?->cliente_id;
            $vence = $op->vencimiento_deuda;
            $urgencia = 'normal';
            if ($vence) {
                if ($vence->isPast()) {
                    $urgencia = 'vencido';
                } elseif ($vence->diffInDays(now()) <= 7) {
                    $urgencia = 'por_vencer';
                }
            }

            return [
                'id' => $op->id,
                'deuda_id' => $op instanceof DeudaTributaria ? $op->id : null,
                'operacion_id' => $op instanceof Operacion ? $op->id : $op->operacion_id,
                'cliente_id' => $clienteId,
                'cliente_codigo' => (string) $numCliente,
                'cliente_razon' => $op->cliente->razon_social ?? 'Empresa sin nombre',
                'cliente_ruc' => $op->cliente->ruc ?? '',
                'periodo' => $op->periodo,
                'tributo_codigo' => $op->tributo_codigo ?? '1011',
                'tributo_nombre' => $op->tributo_nombre,
                'tributo_nombre_corto' => $op->tributo_nombre_corto,
                'tributo_etiqueta' => $op->tributo_etiqueta,
                'es_igv_justo' => (bool) $op->es_igv_justo,
                'vencimiento' => $vence?->format('Y-m-d'),
                'vencimiento_formateado' => $vence?->format('d/m/Y') ?? '—',
                'vencimiento_urgencia' => $urgencia,
                'monto_deuda_inicial' => (float) ($op->monto_deuda_inicial ?? 0),
                'monto_deuda_inicial_formateado' => $op->monto_deuda_inicial_formateado,
                'total_abonado' => (float) $op->total_abonado,
                'total_abonado_formateado' => $op->total_abonado_formateado,
                'saldo_pendiente' => (float) $op->saldo_pendiente,
                'saldo_pendiente_formateado' => $op->saldo_pendiente_formateado,
                'porcentaje_pagado' => $op->monto_deuda_inicial > 0
                    ? min(100, round(($op->total_abonado / $op->monto_deuda_inicial) * 100))
                    : 0,
                'abonos' => $op->abonosDeuda->sortByDesc('fecha_pago')->map(fn ($abono) => [
                    'id' => $abono->id,
                    'monto' => (float) $abono->monto,
                    'monto_formateado' => $abono->monto_formateado,
                    'fecha_pago' => $abono->fecha_pago?->format('d/m/Y') ?? '',
                    'observacion' => $abono->observacion ?? '',
                ])->values()->all(),
            ];
        })->values();

        // Si es petición AJAX / JSON, devolver JSON con deudas y KPIs recalculados
        if ($request->ajax() || $request->wantsJson() || $request->has('ajax')) {
            return response()->json([
                'success' => true,
                'deudas' => $deudasData,
                'total_deuda' => $totalDeudaHistorica,
                'total_recaudado' => $totalRecaudado,
                'total_saldo' => $totalSaldoPorCobrar,
                'count' => $deudas->count(),
            ]);
        }

        return view('deudas.index', compact(
            'deudas',
            'clientesConDeuda',
            'deudasData',
            'totalDeudaHistorica',
            'totalRecaudado',
            'totalSaldoPorCobrar',
        ));
    }

    /**
     * Historial de tributos ya cancelados.
     * Usa las mismas filas que Control de Deudas y las agrupa por ejercicio y mes.
     */
    public function archivadas(): View
    {
        $filas = $this->filasDeuda(null)
            ->filter(fn (DeudaTributaria|Operacion $fila): bool => (float) $fila->saldo_pendiente <= 0 && (float) $fila->total_abonado > 0)
            ->values();

        $historial = [];
        $clientes = [];
        $tributos = [];
        $mesesPresentes = [];

        foreach ($filas as $fila) {
            $registro = $this->registroArchivado($fila);
            $historial[$registro['anio']][$registro['mes']][] = $registro;

            if ($registro['cliente_id'] !== null) {
                $clientes[$registro['cliente_id']] = [
                    'id' => $registro['cliente_id'],
                    'codigo' => $registro['codigo'],
                    'razon_social' => $registro['cliente'],
                ];
            }

            $codigoTributo = $registro['tributo_codigo'] !== '' ? $registro['tributo_codigo'] : 'sin-codigo';
            $tributos[$codigoTributo] = [
                'codigo' => $codigoTributo,
                'nombre' => $registro['tributo'],
            ];

            if ($registro['mes'] > 0) {
                $mesesPresentes[$registro['mes']] = $registro['mes_nombre'];
            }
        }

        krsort($historial);

        $anios = [];
        foreach (array_keys($historial) as $anio) {
            $anio = (int) $anio;
            if ($anio > 0) {
                $anios[] = $anio;
            }
        }
        if (array_key_exists(0, $historial)) {
            $anios[] = 0;
        }

        foreach ($historial as $anio => $porMes) {
            ksort($porMes);
            $ordenado = [];
            foreach ($porMes as $numero => $items) {
                usort($items, function (array $a, array $b): int {
                    $porCliente = strcasecmp($a['cliente'], $b['cliente']);
                    if ($porCliente !== 0) {
                        return $porCliente;
                    }

                    return strcasecmp($a['tributo_codigo'], $b['tributo_codigo']);
                });

                $ordenado[] = [
                    'numero' => (int) $numero,
                    'nombre' => $this->nombreMes((int) $numero),
                    'items' => $items,
                    'total' => array_sum(array_column($items, 'monto')),
                    'pagado' => array_sum(array_column($items, 'pagado')),
                ];
            }
            $historial[$anio] = $ordenado;
        }

        $totalArchivadas = 0;
        foreach ($historial as $mesesAnio) {
            foreach ($mesesAnio as $mes) {
                $totalArchivadas += count($mes['items']);
            }
        }

        $itemsActivos = [];
        foreach ($historial as $mesesAnio) {
            foreach ($mesesAnio as $mes) {
                foreach ($mes['items'] as $item) {
                    $itemsActivos[] = $item;
                }
            }
        }

        $montoInicial = 0.0;
        $pagadoInicial = 0.0;
        $clientesIniciales = [];
        foreach ($itemsActivos as $item) {
            $montoInicial += $item['monto'];
            $pagadoInicial += $item['pagado'];
            if ($item['cliente_id'] !== null) {
                $clientesIniciales[$item['cliente_id']] = true;
            }
        }

        uasort($clientes, fn (array $a, array $b): int => strcasecmp($a['razon_social'], $b['razon_social']));
        uasort($tributos, fn (array $a, array $b): int => strcasecmp($a['codigo'], $b['codigo']));
        ksort($mesesPresentes);

        $mesesOpciones = [];
        foreach ($mesesPresentes as $numero => $nombre) {
            $mesesOpciones[] = [
                'numero' => (int) $numero,
                'nombre' => $nombre,
            ];
        }

        return view('deudas.archivadas', [
            'historial' => $historial,
            'anios' => $anios,
            'totalArchivadas' => $totalArchivadas,
            'clientesOpciones' => array_values($clientes),
            'tributosOpciones' => array_values($tributos),
            'mesesOpciones' => $mesesOpciones,
            'resumenInicial' => [
                'cantidad' => count($itemsActivos),
                'monto' => $montoInicial,
                'pagado' => $pagadoInicial,
                'clientes' => count($clientesIniciales),
            ],
        ]);
    }

    /**
     * @return array{
     *     clave: string,
     *     anio: int,
     *     mes: int,
     *     mes_nombre: string,
     *     cliente_id: int|null,
     *     cliente: string,
     *     cliente_url: string|null,
     *     ruc: string,
     *     codigo: string,
     *     periodo: string,
     *     tributo: string,
     *     tributo_nombre: string,
     *     tributo_codigo: string,
     *     es_igv_justo: bool,
     *     vencimiento: string,
     *     monto: float,
     *     pagado: float,
     *     monto_formateado: string,
     *     pagado_formateado: string,
     *     saldo_formateado: string,
     *     ultimo_pago: string,
     *     abonos: list<array{fecha: string, monto_formateado: string, observacion: string}>,
     *     buscar: string
     * }
     */
    private function registroArchivado(DeudaTributaria|Operacion $fila): array
    {
        $partes = $this->partesPeriodo((string) $fila->periodo);
        $mes = $partes[0] ?? 0;
        $anio = $partes[1] ?? 0;
        $cliente = $fila->cliente;
        $codigo = $cliente ? (string) ($cliente->numero ?? $cliente->id) : '0';
        $abonos = $fila->abonosDeuda
            ->sortByDesc(fn ($abono) => $abono->fecha_pago?->timestamp ?? 0)
            ->values();
        $ultimoPago = $abonos->first();
        $vence = $fila->vencimiento_deuda;
        $abonosVista = $abonos->map(fn ($abono): array => [
            'fecha' => $abono->fecha_pago?->format('d/m/Y') ?? '—',
            'monto_formateado' => $abono->monto_formateado,
            'observacion' => (string) ($abono->observacion ?? ''),
        ])->all();

        return [
            'clave' => $fila instanceof DeudaTributaria ? 'd-'.$fila->id : 'o-'.$fila->id,
            'anio' => $anio,
            'mes' => $mes,
            'mes_nombre' => $this->nombreMes($mes),
            'cliente_id' => $cliente?->id,
            'cliente' => $cliente?->razon_social ?? 'Empresa sin nombre',
            'cliente_url' => $cliente ? route('clientes.show', $cliente) : null,
            'ruc' => (string) ($cliente?->ruc ?? ''),
            'codigo' => $codigo,
            'periodo' => (string) $fila->periodo,
            'tributo' => (string) $fila->tributo_nombre_corto,
            'tributo_nombre' => (string) $fila->tributo_nombre,
            'tributo_codigo' => (string) ($fila->tributo_codigo ?? ''),
            'es_igv_justo' => (bool) $fila->es_igv_justo,
            'vencimiento' => $vence?->format('d/m/Y') ?? '—',
            'monto' => (float) ($fila->monto_deuda_inicial ?? 0),
            'pagado' => (float) $fila->total_abonado,
            'monto_formateado' => $fila->monto_deuda_inicial_formateado,
            'pagado_formateado' => $fila->total_abonado_formateado,
            'saldo_formateado' => $fila->saldo_pendiente_formateado,
            'ultimo_pago' => $ultimoPago?->fecha_pago?->format('d/m/Y') ?? '—',
            'abonos' => $abonosVista,
            'buscar' => mb_strtolower(trim(implode(' ', array_filter([
                $cliente?->razon_social,
                $cliente?->ruc,
                $codigo,
                $fila->periodo,
                $fila->tributo_nombre,
                $fila->tributo_nombre_corto,
                $fila->tributo_codigo,
                $fila->tributo_etiqueta,
                ...array_column($abonosVista, 'observacion'),
            ], fn (mixed $valor): bool => $valor !== null && $valor !== '')))),
        ];
    }

    private function nombreMes(int $mes): string
    {
        return [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ][$mes] ?? 'Sin periodo';
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    private function partesPeriodo(string $periodo): ?array
    {
        if (preg_match('/^(0[1-9]|1[0-2])-(\d{4})$/', $periodo, $coincidencias) !== 1) {
            return null;
        }

        return [(int) $coincidencias[1], (int) $coincidencias[2]];
    }

    /**
     * Cada tributo es una fila. Las deudas antiguas sin detalle siguen saliendo de la operación.
     *
     * @param  array<int, int>|null  $clienteIds
     * @return Collection<int, DeudaTributaria|Operacion>
     */
    private function filasDeuda(?array $clienteIds): Collection
    {
        $hijas = DeudaTributaria::query()
            ->with(['operacion.cliente', 'abonos'])
            ->whereHas('operacion', function ($query) use ($clienteIds) {
                $query->where('tiene_deuda', true);
                if (! empty($clienteIds)) {
                    $query->whereIn('cliente_id', $clienteIds);
                }
            })
            ->get();

        $legacy = Operacion::query()
            ->where('tiene_deuda', true)
            ->whereDoesntHave('deudasTributarias')
            ->with(['cliente', 'abonosDeuda'])
            ->when(! empty($clienteIds), fn ($query) => $query->whereIn('cliente_id', $clienteIds))
            ->get();

        return $hijas->concat($legacy)->sortBy(function (DeudaTributaria|Operacion $fila) {
            return $fila->vencimiento_deuda?->timestamp ?? PHP_INT_MAX;
        })->values();
    }
}
