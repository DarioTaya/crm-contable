<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Operacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ControlMensualController extends Controller
{
    /**
     * Muestra la Matriz Anual estilo Spreadsheet (Sábana de datos Excel).
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

        // Cargar clientes vigentes en el año seleccionado ordenados por número.
        // Se excluyen clientes que se retiraron ANTES del año en consulta.
        $clientes = Cliente::where(function ($query) use ($anio) {
            $query->where('estado', 'activo')
                ->orWhere('estado', 'suspendido')
                ->orWhere(function ($q) use ($anio) {
                    $q->where('estado', 'retirado')
                        ->whereNotNull('mes_baja')
                        ->where('mes_baja', '>=', "{$anio}-01");
                });
        })
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $this->periodosDelAnio($anio))])
            ->orderBy('numero')
            ->get();

        $matrizClientes = [];

        foreach ($clientes as $cliente) {
            $opsMap = $cliente->operaciones->keyBy('periodo');
            $meses = [];
            $cDeclaradas = 0;
            $cExigibles = 0;

            // Determinar mes de baja efectivo si el cliente está retirado
            $mesBaja = null;
            if ($cliente->isRetirado()) {
                $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
            }

            for ($m = 1; $m <= 12; $m++) {
                $periodo = sprintf('%02d-%d', $m, $anio);
                $periodoComparable = sprintf('%04d-%02d', $anio, $m);
                $op = $opsMap->get($periodo);

                $esBajaMes = ($mesBaja !== null && $periodoComparable >= $mesBaja);

                if ($esBajaMes) {
                    $meses[] = [
                        'numero' => $m,
                        'nombre' => $mesesEncabezado[$m]['nombre'],
                        'corto' => $mesesEncabezado[$m]['corto'],
                        'periodo' => $periodo,
                        'estado' => 'Baja',
                        'es_baja' => true,
                        'motivo_baja' => $cliente->motivo_baja ?: 'Retirado / Baja del Estudio',
                        'honorarios' => '—',
                        'sire' => '—',
                        'pdt' => '—',
                        'ple' => '—',
                        'notas' => 'Cliente no activo en este periodo (Baja del Estudio Contable)',
                    ];

                    continue;
                }

                $cExigibles++;

                $sire = $op->estado_compras_ventas ?? 'Pendiente';
                $pdt = $op->estado_pdt621 ?? 'Pendiente';
                $ple = $op->estado_ple ?? 'Pendiente';
                $notasMes = $op->notas ?? '';
                $honorariosMes = $op->estado_pago_honorarios ?? 'Pendiente';

                $estadoMaestro = $this->determinarEstadoMaestro($sire, $pdt, $ple);

                if ($estadoMaestro === 'Declarado') {
                    $cDeclaradas++;
                }

                $meses[] = [
                    'numero' => $m,
                    'nombre' => $mesesEncabezado[$m]['nombre'],
                    'corto' => $mesesEncabezado[$m]['corto'],
                    'periodo' => $periodo,
                    'estado' => $estadoMaestro,
                    'es_baja' => false,
                    'motivo_baja' => null,
                    'honorarios' => $honorariosMes,
                    'sire' => $sire,
                    'pdt' => $pdt,
                    'ple' => $ple,
                    'notas' => $notasMes,
                ];
            }

            $cPorcentaje = $cExigibles > 0 ? (int) round(($cDeclaradas / $cExigibles) * 100) : 100;

            $matrizClientes[] = [
                'numero' => $cliente->numero,
                'cliente' => $cliente,
                'meses' => $meses,
                'declaradas' => $cDeclaradas,
                'porcentaje' => $cPorcentaje,
            ];
        }

        return view('control.index', array_merge(compact(
            'anio',
            'aniosDisponibles',
            'mesesEncabezado',
            'matrizClientes'
        ), $kpis));
    }

    /**
     * Calcula los KPIs unificados para el año seleccionado.
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
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $this->periodosDelAnio($anio))])
            ->get();

        $totalClientes = $clientes->count();
        $totalObligacionesAnio = 0;
        $declaradasTotal = 0;
        $procesandoTotal = 0;
        $pendientesTotal = 0;
        $honorariosPagadosTotal = 0;
        $honorariosPendientesTotal = 0;

        foreach ($clientes as $cliente) {
            $opsMap = $cliente->operaciones->keyBy('periodo');

            $mesBaja = null;
            if ($cliente->isRetirado()) {
                $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
            }

            for ($m = 1; $m <= 12; $m++) {
                $periodoComparable = sprintf('%04d-%02d', $anio, $m);

                // Si el cliente se retiró en o antes de este periodo, este mes ya no es exigible para el estudio
                if ($mesBaja !== null && $periodoComparable >= $mesBaja) {
                    continue;
                }

                $totalObligacionesAnio++;

                $periodo = sprintf('%02d-%d', $m, $anio);
                $op = $opsMap->get($periodo);

                $sire = $op->estado_compras_ventas ?? 'Pendiente';
                $pdt = $op->estado_pdt621 ?? 'Pendiente';
                $ple = $op->estado_ple ?? 'Pendiente';
                $honorariosMes = $op->estado_pago_honorarios ?? 'Pendiente';

                $estadoMaestro = $this->determinarEstadoMaestro($sire, $pdt, $ple);

                match ($estadoMaestro) {
                    'Declarado' => $declaradasTotal++,
                    'Procesando' => $procesandoTotal++,
                    default => $pendientesTotal++,
                };

                if ($honorariosMes === 'Pagado') {
                    $honorariosPagadosTotal++;
                } else {
                    $honorariosPendientesTotal++;
                }
            }
        }

        $porcentajeAvanceAnual = $totalObligacionesAnio > 0
            ? (int) round(($declaradasTotal / $totalObligacionesAnio) * 100)
            : 0;

        return [
            'totalClientes' => $totalClientes,
            'totalObligacionesAnio' => $totalObligacionesAnio,
            'declaradasTotal' => $declaradasTotal,
            'procesandoTotal' => $procesandoTotal,
            'pendientesTotal' => $pendientesTotal,
            'porcentajeAvanceAnual' => $porcentajeAvanceAnual,
            'honorariosPagadosTotal' => $honorariosPagadosTotal,
            'honorariosPendientesTotal' => $honorariosPendientesTotal,
        ];
    }

    /**
     * Determina el estado maestro unificado de un mes a partir de sus 3 sub-estados.
     */
    private function determinarEstadoMaestro(string $sire, string $pdt, string $ple): string
    {
        if ($sire === 'Declarado' && $pdt === 'Declarado' && $ple === 'Declarado') {
            return 'Declarado';
        }

        if ($sire === 'Procesando' || $pdt === 'Procesando' || $ple === 'Procesando'
            || $sire === 'Declarado' || $pdt === 'Declarado' || $ple === 'Declarado') {
            return 'Procesando';
        }

        return 'Pendiente';
    }

    /**
     * Guarda o actualiza los datos de operaciones vía AJAX (soporta guardado de interruptor maestro, honorarios, notas o fila anual).
     */
    public function update(Request $request): JsonResponse
    {
        // 1. Guardado por lote anual de la fila completa del cliente
        if ($request->has('meses') && is_array($request->input('meses'))) {
            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
                'meses' => ['required', 'array'],
                'meses.*.periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
                'meses.*.sire' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'meses.*.pdt' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'meses.*.ple' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'El cliente no existe.',
                'meses.required' => 'Los meses son obligatorios.',
            ]);

            $clienteId = $validated['cliente_id'];
            $notas = $validated['notas'] ?? null;

            foreach ($validated['meses'] as $m) {
                Operacion::updateOrCreate(
                    [
                        'cliente_id' => $clienteId,
                        'periodo' => $m['periodo'],
                    ],
                    [
                        'estado_compras_ventas' => $m['sire'],
                        'estado_pdt621' => $m['pdt'],
                        'estado_ple' => $m['ple'],
                        'notas' => $notas,
                    ]
                );
            }

            $anio = (int) explode('-', $validated['meses'][0]['periodo'])[1];

            return response()->json([
                'success' => true,
                'message' => 'Fila anual guardada exitosamente.',
                'kpis' => $this->calcularKpis($anio),
            ]);
        }

        // Normalizar periodo si viene YYYY-MM
        if ($request->has('periodo') && preg_match('/^(\d{4})-(\d{2})$/', (string) $request->input('periodo'), $m)) {
            $request->merge(['periodo' => "{$m[2]}-{$m[1]}"]);
        }

        // 2. Guardado del interruptor maestro mensual, honorarios o nota mensual (Fase 11.5 + Honorarios)
        if ($request->has('estado') || $request->has('honorarios') || $request->has('estado_pago_honorarios') || (! $request->has('estado_compras_ventas') && $request->has('notas'))) {
            $validated = $request->validate([
                'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
                'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
                'estado' => ['nullable', 'string', 'in:Pendiente,Procesando,Declarado'],
                'honorarios' => ['nullable', 'string', 'in:Pendiente,Pagado'],
                'estado_pago_honorarios' => ['nullable', 'string', 'in:Pendiente,Pagado'],
                'notas' => ['nullable', 'string', 'max:1000'],
            ], [
                'cliente_id.required' => 'El cliente es obligatorio.',
                'cliente_id.exists' => 'El cliente no existe.',
                'periodo.required' => 'El periodo contable es obligatorio.',
                'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
                'estado.in' => 'El estado maestro debe ser Pendiente, Procesando o Declarado.',
                'honorarios.in' => 'El estado de honorarios debe ser Pendiente o Pagado.',
            ]);

            $operacion = Operacion::firstOrNew([
                'cliente_id' => $validated['cliente_id'],
                'periodo' => $validated['periodo'],
            ]);

            if (isset($validated['estado'])) {
                $operacion->estado_compras_ventas = $validated['estado'];
                $operacion->estado_pdt621 = $validated['estado'];
                $operacion->estado_ple = $validated['estado'];
            } elseif (! $operacion->exists) {
                $operacion->estado_compras_ventas = 'Pendiente';
                $operacion->estado_pdt621 = 'Pendiente';
                $operacion->estado_ple = 'Pendiente';
            }

            if ($request->has('honorarios')) {
                $operacion->estado_pago_honorarios = $validated['honorarios'];
            } elseif ($request->has('estado_pago_honorarios')) {
                $operacion->estado_pago_honorarios = $validated['estado_pago_honorarios'];
            }

            if ($request->has('notas')) {
                $operacion->notas = $validated['notas'];
            }

            $operacion->save();

            $anio = (int) explode('-', $validated['periodo'])[1];

            return response()->json([
                'success' => true,
                'message' => 'Operación actualizada exitosamente.',
                'operacion' => $operacion,
                'kpis' => $this->calcularKpis($anio),
            ]);
        }

        // 3. Guardado individual de mes con detalle de obligaciones (compatibilidad previa)
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'periodo' => ['required', 'string', 'regex:/^\d{2}-\d{4}$/'],
            'estado_compras_ventas' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_pdt621' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'estado_ple' => ['required', 'string', 'in:Pendiente,Procesando,Declarado'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ], [
            'cliente_id.required' => 'El cliente es obligatorio.',
            'cliente_id.exists' => 'El cliente especificado no existe.',
            'periodo.required' => 'El periodo contable es obligatorio.',
            'periodo.regex' => 'El formato del periodo debe ser MM-AAAA.',
            'estado_compras_ventas.in' => 'El estado de Compras/Ventas (SIRE) es inválido.',
            'estado_pdt621.in' => 'El estado de PDT 621 es inválido.',
            'estado_ple.in' => 'El estado de PLE es inválido.',
        ]);

        $operacion = Operacion::updateOrCreate(
            [
                'cliente_id' => $validated['cliente_id'],
                'periodo' => $validated['periodo'],
            ],
            [
                'estado_compras_ventas' => $validated['estado_compras_ventas'],
                'estado_pdt621' => $validated['estado_pdt621'],
                'estado_ple' => $validated['estado_ple'],
                'notas' => $validated['notas'] ?? null,
            ]
        );

        $anio = (int) explode('-', $validated['periodo'])[1];

        return response()->json([
            'success' => true,
            'message' => 'Operación guardada exitosamente.',
            'operacion' => $operacion,
            'kpis' => $this->calcularKpis($anio),
        ]);
    }

    /**
     * Los doce periodos MM-AAAA de un ejercicio, para filtrar con whereIn y
     * usar el indice en lugar de un LIKE que no puede aprovecharlo.
     *
     * @return array<int, string>
     */
    private function periodosDelAnio(int $anio): array
    {
        return array_map(
            fn (int $mes) => sprintf('%02d-%d', $mes, $anio),
            range(1, 12),
        );
    }
}
