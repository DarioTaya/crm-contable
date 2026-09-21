<?php

namespace App\Http\Controllers;

use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\ControlBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlBalanceController extends Controller
{
    /**
     * Muestra la Matriz Anual de Control de Balances y Estados Financieros.
     */
    public function index(Request $request): View
    {
        $anioActual = (int) now()->year;
        $anio = (int) $request->query('anio', $anioActual);

        if ($anio < 2000 || $anio > 2100) {
            $anio = $anioActual;
        }

        // Rango de años para selector (2 años antes, 2 años después)
        $aniosDisponibles = range($anioActual - 2, $anioActual + 2);

        $mesesEncabezado = [
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

        $kpis = $this->calcularKpis($anio);

        // FILTRO INTELIGENTE: Consulta estricta para regímenes MYPE y General (excluye RUS y RER).
        // Carga clientes vigentes en el año fiscal, excluyendo bajas previas al año consultado.
        $clientes = Cliente::where(function ($query) use ($anio) {
            $query->where('estado', 'activo')
                ->orWhere('estado', 'suspendido')
                ->orWhere(function ($q) use ($anio) {
                    $q->where('estado', 'retirado')
                        ->whereNotNull('mes_baja')
                        ->where('mes_baja', '>=', "{$anio}-01");
                });
        })
            ->whereIn('regimen_tributario', RegimenTributario::valoresConLibrosElectronicos())
            ->with(['controlBalances' => fn ($query) => $query->where('anio', $anio)])
            ->orderBy('numero')
            ->get();

        $matrizClientes = [];

        foreach ($clientes as $cliente) {
            $balance = $cliente->controlBalances->first();
            $meses = [];
            $cDeclaradas = 0;
            $cExigibles = 0;

            // Determinar mes de baja efectivo si el cliente está retirado
            $mesBaja = null;
            if ($cliente->isRetirado()) {
                $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
            }

            for ($m = 1; $m <= 12; $m++) {
                $periodoComparable = sprintf('%04d-%02d', $anio, $m);
                $esBajaMes = ($mesBaja !== null && $periodoComparable >= $mesBaja);

                if ($esBajaMes) {
                    $meses[] = [
                        'numero' => $m,
                        'nombre' => $mesesEncabezado[$m]['nombre'],
                        'corto' => $mesesEncabezado[$m]['corto'],
                        'estado' => 'Baja',
                        'es_baja' => true,
                        'nota' => 'Cliente no activo en este periodo (Baja del Estudio Contable)',
                    ];

                    continue;
                }

                $cExigibles++;
                $estado = $balance?->getEstadoMes($m) ?? 'Pendiente';
                $nota = $balance?->getNotaMes($m) ?? '';

                if ($estado === 'Declarado') {
                    $cDeclaradas++;
                }

                $meses[] = [
                    'numero' => $m,
                    'nombre' => $mesesEncabezado[$m]['nombre'],
                    'corto' => $mesesEncabezado[$m]['corto'],
                    'estado' => $estado,
                    'es_baja' => false,
                    'nota' => $nota,
                ];
            }

            $cPorcentaje = $cExigibles > 0 ? (int) round(($cDeclaradas / $cExigibles) * 100) : 100;
            $djAnualEstado = $balance?->dj_anual ?? 'Pendiente';

            $matrizClientes[] = [
                // CORRELATIVO ORIGINAL: Conserva su ID/Posición original del directorio general de clientes
                'numero' => $cliente->numero,
                'cliente' => $cliente,
                'meses' => $meses,
                'dj_anual' => $djAnualEstado,
                'declaradas' => $cDeclaradas,
                'porcentaje' => $cPorcentaje,
            ];
        }

        return view('balances.index', array_merge(compact(
            'anio',
            'aniosDisponibles',
            'mesesEncabezado',
            'matrizClientes'
        ), $kpis));
    }

    /**
     * Guarda o actualiza el estado de un mes, DJ Anual o notas de balance vía AJAX.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'anio' => ['required', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
            'estado' => ['nullable', 'string', 'in:Pendiente,Proceso,Procesando,Declarado'],
            'dj_anual' => ['nullable', 'string', 'in:Pendiente,Proceso,Procesando,Declarado'],
            'nota' => ['nullable', 'string', 'max:1000'],
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente no existe.',
            'anio.required' => 'El año fiscal es obligatorio.',
            'mes.between' => 'El mes debe estar entre 1 y 12.',
        ]);

        $clienteId = (int) $validated['cliente_id'];
        $anio = (int) $validated['anio'];

        $controlBalance = ControlBalance::firstOrNew([
            'cliente_id' => $clienteId,
            'anio' => $anio,
        ]);

        if (! $controlBalance->exists) {
            $mesesIniciales = [];
            for ($m = 1; $m <= 12; $m++) {
                $mesesIniciales[$m] = 'Pendiente';
            }
            $controlBalance->meses = $mesesIniciales;
            $controlBalance->dj_anual = 'Pendiente';
        }

        // Actualizar mes específico
        if (isset($validated['mes']) && isset($validated['estado'])) {
            $mes = (int) $validated['mes'];
            $estado = $validated['estado'] === 'Proceso' ? 'Procesando' : $validated['estado'];
            $controlBalance->setEstadoMes($mes, $estado);
        }

        // Actualizar DJ Anual del año anterior
        if (isset($validated['dj_anual'])) {
            $djAnual = $validated['dj_anual'] === 'Proceso' ? 'Procesando' : $validated['dj_anual'];
            $controlBalance->dj_anual = $djAnual;
        }

        // Actualizar nota de mes
        if (isset($validated['mes']) && $request->has('nota')) {
            $mes = (int) $validated['mes'];
            $controlBalance->setNotaMes($mes, $validated['nota'] ?? '');
        }

        $controlBalance->save();

        return response()->json([
            'success' => true,
            'message' => 'Balance actualizado exitosamente.',
            'balance' => $controlBalance,
            'kpis' => $this->calcularKpis($anio),
        ]);
    }

    /**
     * Calcula los KPIs unificados para el módulo de Control de Balances.
     *
     * @return array{totalClientes: int, totalObligacionesAnio: int, balancesDeclarados: int, balancesProcesando: int, balancesPendientes: int, porcentajeAvance: int, djAnualDeclaradas: int, djAnualPendientes: int}
     */
    public function calcularKpis(int $anio): array
    {
        $clientes = Cliente::where(function ($query) use ($anio) {
            $query->where('estado', 'activo')
                ->orWhere('estado', 'suspendido')
                ->orWhere(function ($q) use ($anio) {
                    $q->where('estado', 'retirado')
                        ->whereNotNull('mes_baja')
                        ->where('mes_baja', '>=', "{$anio}-01");
                });
        })
            ->whereIn('regimen_tributario', RegimenTributario::valoresConLibrosElectronicos())
            ->with(['controlBalances' => fn ($query) => $query->where('anio', $anio)])
            ->get();

        $totalClientes = $clientes->count();
        $totalObligacionesAnio = 0;
        $balancesDeclarados = 0;
        $balancesProcesando = 0;
        $balancesPendientes = 0;
        $djAnualDeclaradas = 0;
        $djAnualPendientes = 0;

        foreach ($clientes as $cliente) {
            $balance = $cliente->controlBalances->first();

            $mesBaja = null;
            if ($cliente->isRetirado()) {
                $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
            }

            for ($m = 1; $m <= 12; $m++) {
                $periodoComparable = sprintf('%04d-%02d', $anio, $m);

                // Si el cliente cesó en o antes de este periodo, no computa como obligación pendiente
                if ($mesBaja !== null && $periodoComparable >= $mesBaja) {
                    continue;
                }

                $totalObligacionesAnio++;

                $estado = $balance?->getEstadoMes($m) ?? 'Pendiente';

                match ($estado) {
                    'Declarado' => $balancesDeclarados++,
                    'Proceso', 'Procesando' => $balancesProcesando++,
                    default => $balancesPendientes++,
                };
            }

            $djAnual = $balance?->dj_anual ?? 'Pendiente';
            if ($djAnual === 'Declarado') {
                $djAnualDeclaradas++;
            } else {
                $djAnualPendientes++;
            }
        }

        $porcentajeAvance = $totalObligacionesAnio > 0
            ? (int) round(($balancesDeclarados / $totalObligacionesAnio) * 100)
            : 0;

        return [
            'totalClientes' => $totalClientes,
            'totalObligacionesAnio' => $totalObligacionesAnio,
            'balancesDeclarados' => $balancesDeclarados,
            'balancesProcesando' => $balancesProcesando,
            'balancesPendientes' => $balancesPendientes,
            'porcentajeAvance' => $porcentajeAvance,
            'djAnualDeclaradas' => $djAnualDeclaradas,
            'djAnualPendientes' => $djAnualPendientes,
        ];
    }
}
