<?php

namespace App\Services\Detracciones;

use App\Models\AbonoDeuda;
use App\Models\Cliente;
use App\Models\MovimientoDetraccion;
use App\Models\SaldoDetraccion;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ExtractoDetraccionesService
{
    /**
     * Sincroniza un abono de deuda individual con la cuenta de detracciones.
     */
    public function sincronizarAbonoDeuda(AbonoDeuda $abono): ?MovimientoDetraccion
    {
        if (! $abono->esPagoDetracciones()) {
            MovimientoDetraccion::where('abono_deuda_id', $abono->id)->delete();

            return null;
        }

        $abono->loadMissing(['operacion.cliente', 'deudaTributaria']);

        $cliente = $abono->operacion?->cliente;
        if (! $cliente) {
            return null;
        }

        $periodo = $abono->operacion?->periodo ?? '';
        $tributoNombre = $abono->deudaTributaria?->tributo_nombre_corto ?? 'Impuesto SUNAT';
        $npsOOperacion = $this->extraerNpsUOperacion($abono->observacion);

        $descripcion = "Cargo SUNAT - {$tributoNombre}";
        if ($periodo !== '') {
            $descripcion .= " ({$periodo})";
        }
        if ($npsOOperacion) {
            $descripcion .= " - NPS {$npsOOperacion}";
        }

        return $this->actualizarOCrearMovimientoRetiro($abono, $cliente, $periodo, $tributoNombre, $npsOOperacion, $descripcion);
    }

    private function actualizarOCrearMovimientoRetiro(
        AbonoDeuda $abono,
        Cliente $cliente,
        string $periodo,
        string $tributoNombre,
        ?string $npsOOperacion,
        string $descripcion,
    ): MovimientoDetraccion {
        // Buscar si ya existe un movimiento de retiro para este abono o para el mismo pago
        $movimiento = MovimientoDetraccion::withoutGlobalScopes()
            ->where('cliente_id', $cliente->id)
            ->where('tipo', 'retiro')
            ->where(function ($q) use ($abono, $npsOOperacion, $periodo, $tributoNombre): void {
                $q->where('abono_deuda_id', $abono->id);

                if ($npsOOperacion) {
                    $q->orWhere('numero_operacion', $npsOOperacion);
                }

                $q->orWhere(function ($sub) use ($abono, $periodo, $tributoNombre): void {
                    $sub->where('origen', 'control_impuestos')
                        ->where('periodo', $periodo)
                        ->where('monto', $abono->monto)
                        ->where(function ($crit) use ($abono, $tributoNombre): void {
                            $crit->where('descripcion', 'like', "%{$tributoNombre}%")
                                ->orWhere('metadatos->deuda_tributaria_id', $abono->deuda_tributaria_id);
                        });
                });
            })
            ->orderByRaw('CASE WHEN abono_deuda_id = ? THEN 0 ELSE 1 END', [$abono->id])
            ->first();

        $datos = [
            'estudio_id' => $abono->estudio_id ?? $cliente->estudio_id,
            'cliente_id' => $cliente->id,
            'tipo' => 'retiro',
            'fecha' => $abono->fecha_pago ?? now()->toDateString(),
            'periodo' => $periodo ?: null,
            'monto' => $abono->monto,
            'descripcion' => $descripcion,
            'numero_operacion' => $npsOOperacion,
            'numero_constancia' => null,
            'comprobante_referencia' => 'PDT / Impuestos',
            'adquiriente_ruc' => null,
            'adquiriente_razon_social' => null,
            'tipo_bien' => null,
            'origen' => 'control_impuestos',
            'abono_deuda_id' => $abono->id,
            'metadatos' => [
                'abono_id' => $abono->id,
                'operacion_id' => $abono->operacion_id,
                'deuda_tributaria_id' => $abono->deuda_tributaria_id,
                'observacion_original' => $abono->observacion,
                'medio_pago' => $abono->medio_pago,
            ],
        ];

        if ($movimiento) {
            $movimiento->update($datos);

            // Eliminar duplicados residuales si existieran con el mismo abono_deuda_id o mismo NPS en el mismo cliente
            MovimientoDetraccion::withoutGlobalScopes()
                ->where('cliente_id', $cliente->id)
                ->where('id', '!=', $movimiento->id)
                ->where('tipo', 'retiro')
                ->where(function ($q) use ($abono, $npsOOperacion, $periodo, $tributoNombre): void {
                    $q->where('abono_deuda_id', $abono->id);
                    if ($npsOOperacion) {
                        $q->orWhere('numero_operacion', $npsOOperacion);
                    }
                    $q->orWhere(function ($sub) use ($abono, $periodo, $tributoNombre): void {
                        $sub->where('origen', 'control_impuestos')
                            ->where('periodo', $periodo)
                            ->where('monto', $abono->monto)
                            ->where('descripcion', 'like', "%{$tributoNombre}%");
                    });
                })
                ->delete();

            return $movimiento;
        }

        return MovimientoDetraccion::withoutGlobalScopes()->create($datos);
    }

    /**
     * Elimina el movimiento de retiro asociado a un abono de deuda.
     */
    public function eliminarSincronizacion(AbonoDeuda $abono): void
    {
        MovimientoDetraccion::withoutGlobalScopes()
            ->where(function ($q) use ($abono): void {
                $q->where('abono_deuda_id', $abono->id)
                    ->orWhere('metadatos->abono_id', $abono->id);
            })
            ->delete();
    }

    /**
     * Sincroniza de forma masiva e idempotente todos los abonos históricos con detracciones de un cliente.
     */
    public function sincronizarHistoricoCliente(Cliente $cliente): int
    {
        // Limpiar movimientos huérfanos generados desde control_impuestos cuyo abono fue eliminado
        MovimientoDetraccion::withoutGlobalScopes()
            ->where('cliente_id', $cliente->id)
            ->where('origen', 'control_impuestos')
            ->whereNotNull('abono_deuda_id')
            ->whereNotIn('abono_deuda_id', AbonoDeuda::withoutGlobalScopes()->select('id'))
            ->delete();

        $abonos = AbonoDeuda::query()
            ->whereHas('operacion', function ($q) use ($cliente) {
                $q->where('cliente_id', $cliente->id);
            })
            ->with(['operacion', 'deudaTributaria'])
            ->get();

        $sincronizados = 0;
        foreach ($abonos as $abono) {
            if ($abono->esPagoDetracciones()) {
                $this->sincronizarAbonoDeuda($abono);
                $sincronizados++;
            }
        }

        return $sincronizados;
    }

    /**
     * Genera el extracto bancario oficial y completo de la cuenta de detracciones.
     *
     * @return array{
     *     cliente: Cliente,
     *     anio: int,
     *     mes: ?int,
     *     mes_nombre: ?string,
     *     anios_disponibles: list<int>,
     *     saldo_inicial: float,
     *     total_depositos: float,
     *     total_retiros: float,
     *     saldo_final: float,
     *     conteo_total: int,
     *     conteo_depositos: int,
     *     conteo_retiros: int,
     *     movimientos: Collection<int, MovimientoDetraccion>,
     *     meses_resumen: array<int, array{mes: int, nombre: string, corto: string, depositos: float, retiros: float, saldo_mes: float, conteo: int}>
     * }
     */
    public function obtenerExtracto(Cliente $cliente, int $anio, ?int $mes = null): array
    {
        // 1. Sincronizar abonos huérfanos solo si existen pagos no vinculados (evita escrituras redundantes en GET)
        $haySinSincronizar = AbonoDeuda::query()
            ->whereHas('operacion', fn ($q) => $q->where('cliente_id', $cliente->id))
            ->where(function ($q) {
                $q->where('medio_pago', 'like', '%detracc%')
                    ->orWhere('observacion', 'like', '%detracc%');
            })
            ->whereDoesntHave('movimientoDetraccion')
            ->exists();

        if ($haySinSincronizar) {
            $this->sincronizarHistoricoCliente($cliente);
        }

        // 2. Determinar años disponibles con movimientos o saldo (usando DISTINCT rápido)
        $aniosSaldos = SaldoDetraccion::where('cliente_id', $cliente->id)
            ->distinct()
            ->pluck('anio')
            ->map(fn ($y) => (int) $y)
            ->all();

        $aniosMovimientos = MovimientoDetraccion::where('cliente_id', $cliente->id)
            ->distinct()
            ->pluck('fecha')
            ->map(fn ($f) => (int) substr((string) ($f instanceof \DateTimeInterface ? $f->format('Y-m-d') : $f), 0, 4))
            ->unique()
            ->values()
            ->all();

        $anioActual = (int) now()->year;
        $aniosDisponibles = array_values(array_unique(array_merge(
            [$anio, $anioActual - 1, $anioActual, $anioActual + 1],
            $aniosMovimientos,
            $aniosSaldos
        )));
        sort($aniosDisponibles);

        // 3. Saldo inicial base del año
        $saldoInicialAnio = $this->obtenerSaldoInicialAnio($cliente, $anio);

        // 4. Una sola consulta indexada para todos los movimientos del año
        $inicioAnioStr = "{$anio}-01-01";
        $finAnioStr = "{$anio}-12-31";

        $todosMovimientosAnio = MovimientoDetraccion::where('cliente_id', $cliente->id)
            ->whereBetween('fecha', [$inicioAnioStr, $finAnioStr])
            ->orderBy('fecha', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        // 5. Filtrar mes si aplica o tomar el año completo en memoria (sin consultas SQL extra)
        $saldoInicialPeriodo = $saldoInicialAnio;

        if ($mes !== null && $mes >= 1 && $mes <= 12) {
            $fechaInicioMesStr = sprintf('%04d-%02d-01', $anio, $mes);
            $ultimoDiaMes = Carbon::create($anio, $mes, 1)->endOfMonth()->day;
            $fechaFinMesStr = sprintf('%04d-%02d-%02d', $anio, $mes, $ultimoDiaMes);

            $acumuladoPrevio = 0.0;
            foreach ($todosMovimientosAnio as $m) {
                $fStr = $m->fecha instanceof \DateTimeInterface ? $m->fecha->format('Y-m-d') : (string) $m->fecha;
                if ($fStr < $fechaInicioMesStr) {
                    $monto = (float) $m->monto;
                    $acumuladoPrevio += $m->isDeposito() ? $monto : -$monto;
                }
            }

            $saldoInicialPeriodo = round($saldoInicialAnio + $acumuladoPrevio, 2);

            $movimientos = $todosMovimientosAnio->filter(function ($m) use ($fechaInicioMesStr, $fechaFinMesStr) {
                $fStr = $m->fecha instanceof \DateTimeInterface ? $m->fecha->format('Y-m-d') : (string) $m->fecha;

                return $fStr >= $fechaInicioMesStr && $fStr <= $fechaFinMesStr;
            })->values();
        } else {
            $movimientos = $todosMovimientosAnio;
        }

        // 6. Arrastre de saldo correlativo línea a línea
        $saldoCorrido = $saldoInicialPeriodo;
        $totalDepositos = 0.0;
        $totalRetiros = 0.0;
        $conteoDepositos = 0;
        $conteoRetiros = 0;

        foreach ($movimientos as $mov) {
            $monto = (float) $mov->monto;
            if ($mov->isDeposito()) {
                $saldoCorrido = round($saldoCorrido + $monto, 2);
                $totalDepositos += $monto;
                $conteoDepositos++;
            } else {
                $saldoCorrido = round($saldoCorrido - $monto, 2);
                $totalRetiros += $monto;
                $conteoRetiros++;
            }
            $mov->saldo_acumulado = $saldoCorrido;
            $mov->saldo_formateado = 'S/ '.number_format($saldoCorrido, 2);
        }

        $saldoFinal = $saldoCorrido;

        // 7. Resumen de los 12 meses del ejercicio para navegación rápida calculado en memoria
        $mesesNombres = [
            1 => ['nombre' => 'Enero', 'corto' => 'ENE'],
            2 => ['nombre' => 'Febrero', 'corto' => 'FEB'],
            3 => ['nombre' => 'Marzo', 'corto' => 'MAR'],
            4 => ['nombre' => 'Abril', 'corto' => 'ABR'],
            5 => ['nombre' => 'Mayo', 'corto' => 'MAY'],
            6 => ['nombre' => 'Junio', 'corto' => 'JUN'],
            7 => ['nombre' => 'Julio', 'corto' => 'JUL'],
            8 => ['nombre' => 'Agosto', 'corto' => 'AGO'],
            9 => ['nombre' => 'Setiembre', 'corto' => 'SET'],
            10 => ['nombre' => 'Octubre', 'corto' => 'OCT'],
            11 => ['nombre' => 'Noviembre', 'corto' => 'NOV'],
            12 => ['nombre' => 'Diciembre', 'corto' => 'DIC'],
        ];

        $mesesResumen = [];
        $saldoArrastreMes = $saldoInicialAnio;

        for ($m = 1; $m <= 12; $m++) {
            $movsMes = $todosMovimientosAnio->filter(function ($mov) use ($m) {
                return (int) ($mov->fecha instanceof \DateTimeInterface ? $mov->fecha->format('n') : substr((string) $mov->fecha, 5, 2)) === $m;
            });
            $depMes = (float) $movsMes->where('tipo', 'deposito')->sum('monto');
            $retMes = (float) $movsMes->where('tipo', 'retiro')->sum('monto');
            $saldoArrastreMes = round($saldoArrastreMes + $depMes - $retMes, 2);

            $mesesResumen[$m] = [
                'mes' => $m,
                'nombre' => $mesesNombres[$m]['nombre'],
                'corto' => $mesesNombres[$m]['corto'],
                'depositos' => $depMes,
                'retiros' => $retMes,
                'saldo_mes' => $saldoArrastreMes,
                'conteo' => $movsMes->count(),
            ];
        }

        $mesNombre = ($mes !== null && isset($mesesNombres[$mes])) ? $mesesNombres[$mes]['nombre'] : null;

        return [
            'cliente' => $cliente,
            'anio' => $anio,
            'mes' => $mes,
            'mes_nombre' => $mesNombre,
            'anios_disponibles' => $aniosDisponibles,
            'saldo_inicial' => $saldoInicialPeriodo,
            'total_depositos' => round($totalDepositos, 2),
            'total_retiros' => round($totalRetiros, 2),
            'saldo_final' => round($saldoFinal, 2),
            'conteo_total' => $movimientos->count(),
            'conteo_depositos' => $conteoDepositos,
            'conteo_retiros' => $conteoRetiros,
            'movimientos' => $movimientos,
            'meses_resumen' => $mesesResumen,
        ];
    }

    /**
     * Obtiene el saldo inicial configurado para un año.
     * Si no existe configuración explícita, calcula arrastrando del año anterior en una sola agregación SQL.
     */
    public function obtenerSaldoInicialAnio(Cliente $cliente, int $anio): float
    {
        $registro = SaldoDetraccion::where('cliente_id', $cliente->id)
            ->where('anio', $anio)
            ->first();

        if ($registro !== null) {
            return (float) $registro->saldo_inicial;
        }

        // Revisar el saldo inicial del año más cercano anterior configurado
        $saldoMasCercano = SaldoDetraccion::where('cliente_id', $cliente->id)
            ->where('anio', '<', $anio)
            ->orderByDesc('anio')
            ->first();

        $anioBase = $saldoMasCercano ? (int) $saldoMasCercano->anio : null;
        $saldoBase = $saldoMasCercano ? (float) $saldoMasCercano->saldo_inicial : 0.0;

        $fechaInicioAcumulacion = $anioBase !== null
            ? "{$anioBase}-01-01"
            : '2000-01-01';

        $fechaFinAcumulacion = ($anio - 1).'-12-31';

        // Sumar depósitos y restar retiros directamente en base de datos mediante agregación SQL indexada
        $acum = (float) MovimientoDetraccion::where('cliente_id', $cliente->id)
            ->whereBetween('fecha', [$fechaInicioAcumulacion, $fechaFinAcumulacion])
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'deposito' THEN monto ELSE -monto END), 0) as acum")
            ->value('acum');

        return round($saldoBase + $acum, 2);
    }

    /**
     * Extrae un número de NPS u operación bancaria a partir de la observación de un abono.
     */
    private function extraerNpsUOperacion(?string $observacion): ?string
    {
        if (! $observacion) {
            return null;
        }

        if (preg_match('/NPS\s*(\d{8,14})/i', $observacion, $m)) {
            return $m[1];
        }

        if (preg_match('/(?:operaci[eēoó]n|op|constancia)[^\d]*(\d{6,14})/i', $observacion, $m)) {
            return $m[1];
        }

        return null;
    }
}
