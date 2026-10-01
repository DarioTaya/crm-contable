<?php

namespace App\Http\Controllers;

use App\Enums\RegimenTributario;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Services\CronogramaSunatService;
use App\Services\TipoCambioSunatService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CronogramaSunatService $cronogramaService,
        protected TipoCambioSunatService $tipoCambioSunat,
    ) {}

    /**
     * Muestra el Centro de Comando (Dashboard) con métricas globales,
     * porcentaje de avance de impuestos y alertas de vencimiento por dígito de RUC y Cronograma SUNAT.
     */
    public function index(): View
    {
        $totalClientesActivos = Cliente::where('estado', 'activo')->count();
        $totalClientesSuspendidos = Cliente::where('estado', 'suspendido')->count();
        $totalClientesRetirados = Cliente::where('estado', 'retirado')->count();
        $totalClientesInactivos = $totalClientesSuspendidos + $totalClientesRetirados;

        // Notificaciones pendientes globales del Buzón de Alertas SUNAT / SUNAFIL (count real de alertas_buzon)
        $notificacionesPendientes = AlertaBuzon::where('estado', 'Pendiente')->count();
        $notificacionesImportantes = AlertaBuzon::where('estado', 'Pendiente')
            ->where('prioridad', 'Alta')
            ->count();
        $notificacionesSunat = AlertaBuzon::where('estado', 'Pendiente')
            ->where('plataforma', 'SUNAT')
            ->count();
        $notificacionesSunafil = AlertaBuzon::where('estado', 'Pendiente')
            ->where('plataforma', 'SUNAFIL')
            ->count();

        // Determinar el período tributario activo según el Cronograma Oficial SUNAT
        // (En Setiembre 2026 se declaran las operaciones de Agosto 2026 '08-2026')
        $periodoActual = $this->cronogramaService->obtenerPeriodoEnCurso('pdt_621');
        [$mesNum, $anioNum] = explode('-', $periodoActual);
        $fechaPeriodo = Carbon::createFromDate((int) $anioNum, (int) $mesNum, 1);
        $nombreMesActual = ucfirst($fechaPeriodo->locale('es')->isoFormat('MMMM [de] YYYY'));
        $mesVencimientoNombre = ucfirst(now()->locale('es')->isoFormat('MMMM [de] YYYY'));

        $periodosExigibles = $this->periodosDeclaracionExigible($periodoActual);

        // Cargar clientes activos con las declaraciones ya exigibles del ejercicio.
        $clientesActivos = Cliente::where('estado', 'activo')
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $periodosExigibles)])
            ->get();

        $totalObligaciones = $clientesActivos->count(); // 1 obligación DJ unificada por cliente/mes

        $declarados = 0;
        $procesando = 0;
        $pendientes = 0;

        foreach ($clientesActivos as $cliente) {
            $operacion = $cliente->operaciones->firstWhere('periodo', $periodoActual);

            $sire = $operacion?->estado_compras_ventas ?? 'Pendiente';
            $pdt = $operacion?->estado_pdt621 ?? 'Pendiente';
            $ple = $operacion?->estado_ple ?? 'Pendiente';

            // Conteo unificado del periodo en curso: 1 mes = 1 DJ (estadoMaestro)
            if ($sire === 'Declarado' && $pdt === 'Declarado' && $ple === 'Declarado') {
                $declarados++;
            } elseif ($sire === 'Procesando' || $pdt === 'Procesando' || $ple === 'Procesando'
                || $sire === 'Declarado' || $pdt === 'Declarado' || $ple === 'Declarado') {
                $procesando++;
            } else {
                $pendientes++;
            }
        }

        $alertasVencimiento = $this->alertasVencimientoImpuestos($clientesActivos, $periodosExigibles);

        $porcentajeAvance = $totalObligaciones > 0 ? (int) round(($declarados / $totalObligaciones) * 100) : 0;

        $saludo = $this->saludoSegunHora();
        $nombreUsuario = $this->primerNombre(auth()->user()?->name);
        $ahoraLima = now('America/Lima')->locale('es');
        $fechaHoy = str_replace('septiembre', 'setiembre', $ahoraLima->isoFormat('dddd, D [de] MMMM [de] YYYY'));
        $horaHoy = $ahoraLima->format('h:i A');
        $avanceBalances = $this->avanceBalancesEnCurso($ahoraLima);
        $alertasBalances = $this->alertasBalancesEnCurso($ahoraLima);
        $honorariosAtrasados = $this->honorariosAtrasados($ahoraLima);
        $radarVencimientos = $this->radarVencimientos($ahoraLima);
        $tipoCambio = $this->tipoCambioSunat->vigente();

        return view('dashboard', compact(
            'totalClientesActivos',
            'totalClientesSuspendidos',
            'totalClientesRetirados',
            'totalClientesInactivos',
            'notificacionesPendientes',
            'notificacionesImportantes',
            'notificacionesSunat',
            'notificacionesSunafil',
            'periodoActual',
            'nombreMesActual',
            'mesVencimientoNombre',
            'totalObligaciones',
            'declarados',
            'procesando',
            'pendientes',
            'porcentajeAvance',
            'alertasVencimiento',
            'saludo',
            'nombreUsuario',
            'fechaHoy',
            'horaHoy',
            'avanceBalances',
            'alertasBalances',
            'honorariosAtrasados',
            'radarVencimientos',
            'tipoCambio',
        ));
    }

    /**
     * Meses del ejercicio que ya se declaran, desde enero hasta el periodo en curso.
     *
     * En setiembre el periodo en curso es agosto. Setiembre y los meses
     * posteriores siguen en blanco en la matriz, pero todavía no vencen.
     *
     * @return list<string>
     */
    private function periodosDeclaracionExigible(string $periodoActual): array
    {
        [$mesTexto, $anioTexto] = explode('-', $periodoActual);
        $hasta = (int) $mesTexto;
        $anio = (int) $anioTexto;
        $periodos = [];

        for ($mes = 1; $mes <= $hasta; $mes++) {
            $periodos[] = sprintf('%02d-%d', $mes, $anio);
        }

        return $periodos;
    }

    /**
     * Clientes activos con meses de impuestos que el control todavía no marca como Declarado.
     *
     * Un mes entra si falta SIRE, PDT 621 o PDT 601, igual que el botón de la matriz.
     * El orden prioriza lo vencido y, en empate, el último dígito del RUC.
     *
     * @param  Collection<int, Cliente>  $clientes
     * @param  list<string>  $periodos
     * @return list<array{
     *     cliente: Cliente,
     *     ultimo_digito: int,
     *     ultimo_digito_str: string,
     *     faltantes: list<array{nombre: string, estado: string}>,
     *     total_faltantes: int,
     *     meses: list<array<string, mixed>>,
     *     anio: int,
     *     fecha_critica: Carbon|null,
     *     estado_critico: array<string, mixed>,
     *     etiqueta_cronograma: array<string, mixed>|null
     * }>
     */
    private function alertasVencimientoImpuestos(Collection $clientes, array $periodos): array
    {
        $cronogramas = CronogramaSunat::query()
            ->whereIn('tipo', ['pdt_621', 'sire'])
            ->whereIn('periodo', $periodos)
            ->get()
            ->groupBy(fn (CronogramaSunat $cronograma): string => $cronograma->tipo.'|'.$cronograma->periodo);

        $alertas = [];

        foreach ($clientes as $cliente) {
            $operaciones = $cliente->operaciones->keyBy('periodo');
            $meses = [];

            foreach ($periodos as $periodo) {
                if (! $cliente->rindeEnPeriodo($periodo)) {
                    continue;
                }

                $operacion = $operaciones->get($periodo);
                $sire = $operacion?->estado_compras_ventas ?? 'Pendiente';
                $pdt = $operacion?->estado_pdt621 ?? 'Pendiente';
                $ple = $operacion?->estado_ple ?? 'Pendiente';
                $estado = $this->estadoDeclaracionMaestro($sire, $pdt, $ple);

                if ($estado === 'Declarado') {
                    continue;
                }

                [$mesTexto, $anioTexto] = explode('-', $periodo);
                $mes = (int) $mesTexto;
                $anio = (int) $anioTexto;
                $fechaPdt = $this->fechaCronogramaCliente($cronogramas->get('pdt_621|'.$periodo)?->first(), $cliente);
                $fechaSire = $this->fechaCronogramaCliente($cronogramas->get('sire|'.$periodo)?->first(), $cliente);
                $fechaCritica = ($sire !== 'Declarado' && $fechaSire) ? $fechaSire : $fechaPdt;

                $meses[] = [
                    'periodo' => $periodo,
                    'anio' => $anio,
                    'mes' => $mes,
                    'nombre' => $this->nombreMesDeclaracion($anio, $mes),
                    'estado' => $estado,
                    'sire' => $sire,
                    'pdt' => $pdt,
                    'ple' => $ple,
                    'fecha' => $fechaCritica,
                    'fecha_sire' => $fechaSire,
                    'fecha_pdt' => $fechaPdt,
                    'fecha_formateada' => $fechaCritica ? $fechaCritica->format('d/m/Y') : null,
                    'estado_critico' => $this->cronogramaService->calcularEstadoVencimiento($fechaCritica),
                    'obligacion_critica' => ($sire !== 'Declarado' && $fechaSire) ? 'SIRE' : 'PDT 621',
                ];
            }

            if ($meses === []) {
                continue;
            }

            $porUrgencia = $meses;
            usort($porUrgencia, fn (array $a, array $b): int => $this->compararUrgenciaBalance($a, $b));

            $ultimoDigito = substr((string) $cliente->ruc, -1);
            $faltantes = $this->obligacionesFaltantes($meses);

            $alertas[] = [
                'cliente' => $cliente,
                'ultimo_digito' => (int) $ultimoDigito,
                'ultimo_digito_str' => $ultimoDigito,
                'faltantes' => $faltantes,
                'total_faltantes' => count($faltantes),
                'meses' => $meses,
                'anio' => $meses[0]['anio'],
                'fecha_critica' => $porUrgencia[0]['fecha'],
                'estado_critico' => $porUrgencia[0]['estado_critico'],
                'etiqueta_cronograma' => $this->construirEtiquetaCronograma($porUrgencia[0], $cliente),
            ];
        }

        usort($alertas, function (array $a, array $b): int {
            $prioridad = [
                'hoy' => 1,
                'vencido' => 2,
                'proximo' => 3,
                'al_dia' => 4,
                'sin_cronograma' => 5,
            ];

            $pesoA = $prioridad[$a['estado_critico']['estado']] ?? 9;
            $pesoB = $prioridad[$b['estado_critico']['estado']] ?? 9;

            if ($pesoA !== $pesoB) {
                return $pesoA <=> $pesoB;
            }

            if ($a['fecha_critica'] && $b['fecha_critica']) {
                $compFecha = $a['fecha_critica']->timestamp <=> $b['fecha_critica']->timestamp;
                if ($compFecha !== 0) {
                    return $compFecha;
                }
            }

            return $a['ultimo_digito'] <=> $b['ultimo_digito'];
        });

        return $alertas;
    }

    /**
     * Mismo criterio que la matriz de control: verde solo cuando las tres DJ están declaradas.
     */
    private function estadoDeclaracionMaestro(string $sire, string $pdt, string $ple): string
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
     * Obligaciones que siguen abiertas en alguno de los meses listados.
     *
     * @param  list<array{sire: string, pdt: string, ple: string}>  $meses
     * @return list<array{nombre: string, estado: string}>
     */
    private function obligacionesFaltantes(array $meses): array
    {
        $mapa = [
            'SIRE' => 'sire',
            'PDT 621' => 'pdt',
            'PDT 601' => 'ple',
        ];

        $faltantes = [];

        foreach ($mapa as $nombre => $clave) {
            $pendientes = array_values(array_filter(
                array_column($meses, $clave),
                fn (string $estado): bool => $estado !== 'Declarado'
            ));

            if ($pendientes === []) {
                continue;
            }

            $faltantes[] = [
                'nombre' => $nombre,
                'estado' => in_array('Pendiente', $pendientes, true) ? 'Pendiente' : 'Procesando',
            ];
        }

        return $faltantes;
    }

    private function fechaCronogramaCliente(?CronogramaSunat $cronograma, Cliente $cliente): ?Carbon
    {
        if (! $cronograma instanceof CronogramaSunat) {
            return null;
        }

        if ($cronograma->fecha_vencimiento_general) {
            $raw = $cronograma->fecha_vencimiento_general instanceof Carbon
                ? $cronograma->fecha_vencimiento_general->toDateString()
                : (string) $cronograma->fecha_vencimiento_general;

            return Carbon::createFromFormat('!Y-m-d', substr($raw, 0, 10), 'America/Lima');
        }

        $fecha = $cronograma->getFechaPorDigito((string) $cliente->ruc, (bool) $cliente->es_buen_contribuyente);

        if (! $fecha) {
            return null;
        }

        $raw = $fecha instanceof Carbon ? $fecha->toDateString() : (string) $fecha;

        return Carbon::createFromFormat('!Y-m-d', substr($raw, 0, 10), 'America/Lima');
    }

    private function nombreMesDeclaracion(int $anio, int $mes): string
    {
        $nombre = ucfirst(Carbon::createFromDate($anio, $mes, 1)->locale('es')->isoFormat('MMMM YYYY'));

        return str_replace(['Septiembre', 'septiembre'], ['Setiembre', 'setiembre'], $nombre);
    }

    /**
     * Construye la información de la etiqueta sutil de vencimiento según cronograma SUNAT.
     * Muestra las fechas específicas tanto de SIRE como de PDTs para máxima claridad del contador.
     *
     * @param  array<string, mixed>|null  $mesCritico
     * @return array{
     *     texto: string,
     *     dia_mes: string,
     *     dia_mes_sire: string|null,
     *     dia_mes_pdt: string|null,
     *     fecha_formateada: string,
     *     estado: string,
     *     dias: int,
     *     dias_texto: string,
     *     clase: string,
     *     tooltip: string,
     *     obligacion: string,
     *     sire_declarado: bool
     * }|null
     */
    private function construirEtiquetaCronograma(?array $mesCritico, Cliente $cliente): ?array
    {
        if (! $mesCritico || ! ($mesCritico['fecha'] instanceof Carbon)) {
            return null;
        }

        $mesesAbrev = [
            1 => 'Ene.',
            2 => 'Feb.',
            3 => 'Mar.',
            4 => 'Abr.',
            5 => 'May.',
            6 => 'Jun.',
            7 => 'Jul.',
            8 => 'Ago.',
            9 => 'Set.',
            10 => 'Oct.',
            11 => 'Nov.',
            12 => 'Dic.',
        ];

        $formatearDiaMes = function (?Carbon $fecha) use ($mesesAbrev): ?string {
            if (! $fecha) {
                return null;
            }
            $limatized = Carbon::createFromFormat('!Y-m-d', $fecha->toDateString(), 'America/Lima');

            return $limatized->day.' de '.($mesesAbrev[$limatized->month] ?? $limatized->format('m'));
        };

        $formatearCompleta = function (?Carbon $fecha): ?string {
            if (! $fecha) {
                return null;
            }
            $limatized = Carbon::createFromFormat('!Y-m-d', $fecha->toDateString(), 'America/Lima');

            return str_replace(
                ['Septiembre', 'septiembre'],
                ['Setiembre', 'setiembre'],
                ucfirst($limatized->locale('es')->isoFormat('dddd, D [de] MMMM [de] YYYY'))
            );
        };

        $fechaSire = isset($mesCritico['fecha_sire']) && $mesCritico['fecha_sire'] instanceof Carbon
            ? Carbon::createFromFormat('!Y-m-d', $mesCritico['fecha_sire']->toDateString(), 'America/Lima')
            : null;

        $fechaPdt = isset($mesCritico['fecha_pdt']) && $mesCritico['fecha_pdt'] instanceof Carbon
            ? Carbon::createFromFormat('!Y-m-d', $mesCritico['fecha_pdt']->toDateString(), 'America/Lima')
            : null;

        $diaMesSire = $formatearDiaMes($fechaSire);
        $diaMesPdt = $formatearDiaMes($fechaPdt);

        $sireDeclarado = ($mesCritico['sire'] ?? '') === 'Declarado';
        $pdtDeclarado = ($mesCritico['pdt'] ?? '') === 'Declarado';

        // Determinar fecha crítica y texto conciso (ej: "Vence: Sire: 15 de Oct. · PDTs: 16 de Oct.")
        if ($sireDeclarado && $fechaPdt) {
            $vence = $fechaPdt;
            $obligacion = 'PDT 621';
            $texto = "Vence: PDTs: {$diaMesPdt} (Sire ✓)";
        } elseif ($pdtDeclarado && $fechaSire) {
            $vence = $fechaSire;
            $obligacion = 'SIRE';
            $texto = "Vence: Sire: {$diaMesSire}";
        } elseif ($fechaSire && $fechaPdt) {
            $vence = $fechaSire;
            $obligacion = 'SIRE';
            if ($diaMesSire === $diaMesPdt) {
                $texto = "Vence: Sire y PDTs: {$diaMesSire}";
            } else {
                $texto = "Vence: Sire: {$diaMesSire} · PDTs: {$diaMesPdt}";
            }
        } elseif ($fechaSire) {
            $vence = $fechaSire;
            $obligacion = 'SIRE';
            $texto = "Vence: Sire: {$diaMesSire}";
        } elseif ($fechaPdt) {
            $vence = $fechaPdt;
            $obligacion = 'PDT 621';
            $texto = "Vence: PDTs: {$diaMesPdt}";
        } else {
            $vence = Carbon::createFromFormat('!Y-m-d', $mesCritico['fecha']->toDateString(), 'America/Lima');
            $obligacion = $mesCritico['obligacion_critica'] ?? 'PDT 621';
            $diaMesGeneral = $formatearDiaMes($vence);
            $texto = "Vence: {$obligacion}: {$diaMesGeneral}";
        }

        $diaMesCritica = $formatearDiaMes($vence);
        $hoy = now('America/Lima')->startOfDay();

        $criterio = $cliente->es_buen_contribuyente
            ? 'Buen Contribuyente'
            : 'Dígito '.substr((string) $cliente->ruc, -1);
        $periodoNombre = $mesCritico['nombre'] ?? '';

        if ($vence->lt($hoy)) {
            $dias = abs((int) $hoy->diffInDays($vence));
            $estado = 'vencido';
            $diasTexto = $dias === 1 ? 'Venció ayer' : "Venció hace {$dias} días";
            $clase = 'border-rose-200 bg-rose-50/80 text-rose-700';
        } elseif ($vence->isSameDay($hoy)) {
            $dias = 0;
            $estado = 'hoy';
            $diasTexto = 'Vence hoy';
            $clase = 'border-amber-300 bg-amber-50 text-amber-900 font-semibold ring-1 ring-amber-400/30';
        } else {
            $dias = abs((int) $hoy->diffInDays($vence));
            if ($dias <= 3) {
                $estado = 'proximo';
                $diasTexto = $dias === 1 ? 'Vence mañana' : "Vence en {$dias} días";
                $clase = 'border-amber-200 bg-amber-50/80 text-amber-800';
            } else {
                $estado = 'al_dia';
                $diasTexto = "Vence en {$dias} días";
                $clase = 'border-slate-200 bg-slate-50/90 text-slate-700';
            }
        }

        $fechasTooltip = [];
        if ($fechaSire) {
            $fechasTooltip[] = 'Sire: '.$formatearCompleta($fechaSire).($sireDeclarado ? ' (Declarado)' : '');
        }
        if ($fechaPdt) {
            $fechasTooltip[] = 'PDTs: '.$formatearCompleta($fechaPdt).($pdtDeclarado ? ' (Declarado)' : '');
        }
        $detalleFechas = implode(' | ', $fechasTooltip);

        $tooltip = "Cronograma SUNAT ({$criterio} • Período {$periodoNombre}): {$detalleFechas}. {$diasTexto}.";

        return [
            'texto' => $texto,
            'dia_mes' => $diaMesCritica,
            'dia_mes_sire' => $diaMesSire,
            'dia_mes_pdt' => $diaMesPdt,
            'fecha_formateada' => $vence->format('d/m/Y'),
            'estado' => $estado,
            'dias' => $dias,
            'dias_texto' => $diasTexto,
            'clase' => $clase,
            'tooltip' => $tooltip,
            'obligacion' => $obligacion,
            'sire_declarado' => $sireDeclarado,
        ];
    }

    /**
     * Honorarios (RH) no cobrados en los tres meses ya cerrados.
     *
     * En la matriz de control, un mes sin fila o distinto de Pagado se muestra
     * como Debe. Se excluye el mes en curso y los meses posteriores a la baja.
     *
     * @return array{
     *     total: int,
     *     anio: int,
     *     meses: list<array{etiqueta: string, cantidad: int, periodo: string}>
     * }
     */
    private function honorariosAtrasados(Carbon $fecha): array
    {
        $fecha = $fecha->copy()->timezone('America/Lima');
        $cortos = [
            1 => 'ENE',
            2 => 'FEB',
            3 => 'MAR',
            4 => 'ABR',
            5 => 'MAY',
            6 => 'JUN',
            7 => 'JUL',
            8 => 'AGO',
            9 => 'SET',
            10 => 'OCT',
            11 => 'NOV',
            12 => 'DIC',
        ];

        $ventana = [];

        for ($atraso = 1; $atraso <= 3; $atraso++) {
            $mes = $fecha->copy()->startOfMonth()->subMonthsNoOverflow($atraso);
            $ventana[] = [
                'anio' => $mes->year,
                'mes' => $mes->month,
                'periodo' => $mes->format('m-Y'),
                'comparable' => $mes->format('Y-m'),
                'etiqueta' => $cortos[$mes->month].'-'.$mes->format('y'),
            ];
        }

        $anioMinimo = min(array_column($ventana, 'anio'));
        $periodos = array_column($ventana, 'periodo');

        $clientes = Cliente::query()
            ->where(function ($query) use ($anioMinimo) {
                $query->where('estado', 'activo')
                    ->orWhere('estado', 'suspendido')
                    ->orWhere(function ($q) use ($anioMinimo) {
                        $q->where('estado', 'retirado')
                            ->whereNotNull('mes_baja')
                            ->where('mes_baja', '>=', "{$anioMinimo}-01");
                    });
            })
            ->with(['operaciones' => fn ($query) => $query->whereIn('periodo', $periodos)])
            ->get();

        $meses = [];
        $total = 0;

        foreach ($ventana as $mes) {
            $cantidad = 0;

            foreach ($clientes as $cliente) {
                $mesBaja = null;

                if ($cliente->isRetirado()) {
                    $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
                }

                if ($mesBaja !== null && $mes['comparable'] >= $mesBaja) {
                    continue;
                }

                $estado = $cliente->operaciones->firstWhere('periodo', $mes['periodo'])?->estado_pago_honorarios ?? 'Pendiente';

                if ($estado !== 'Pagado') {
                    $cantidad++;
                }
            }

            if ($cantidad === 0) {
                continue;
            }

            $total += $cantidad;
            $meses[] = [
                'etiqueta' => $mes['etiqueta'],
                'cantidad' => $cantidad,
                'periodo' => $mes['periodo'],
            ];
        }

        return [
            'total' => $total,
            'anio' => $ventana[0]['anio'],
            'meses' => $meses,
        ];
    }

    /**
     * Fechas del cronograma SUNAT para el periodo de impuestos en curso
     * y el periodo de balances que ya es exigible.
     *
     * Los dígitos 2-3, 4-5, 6-7 y 8-9 comparten fecha en la tabla oficial.
     * Si el balance solo tiene una fecha general, esa fecha vale para todos.
     *
     * @return array{
     *     impuestos: array{periodo: string, fechas: list<array{digito: string, pdt: string|null, sire: string|null, estado: string}>},
     *     balances: array{periodo: string, fechas: list<array{digito: string, pdt: string|null, sire: string|null, estado: string}>}
     * }
     */
    private function radarVencimientos(Carbon $fecha): array
    {
        $fecha = $fecha->copy()->timezone('America/Lima');
        $hoy = $fecha->toDateString();
        $periodoImpuestos = $this->cronogramaService->obtenerPeriodoEnCurso('pdt_621', $fecha);
        $periodoBalances = $this->cronogramaService->obtenerPeriodoEnCurso('balance_mensual', $fecha);

        $cronogramas = CronogramaSunat::query()
            ->where(function ($query) use ($periodoImpuestos, $periodoBalances): void {
                $query->where(function ($impuestos) use ($periodoImpuestos): void {
                    $impuestos->where('periodo', $periodoImpuestos)
                        ->whereIn('tipo', ['pdt_621', 'sire']);
                })->orWhere(function ($balances) use ($periodoBalances): void {
                    $balances->where('periodo', $periodoBalances)
                        ->where('tipo', 'balance_mensual');
                });
            })
            ->get()
            ->keyBy('tipo');

        return [
            'impuestos' => [
                'periodo' => $periodoImpuestos,
                'fechas' => $this->fechasRadar($cronogramas->get('pdt_621'), $cronogramas->get('sire'), $hoy),
            ],
            'balances' => [
                'periodo' => $periodoBalances,
                'fechas' => $this->fechasRadar($cronogramas->get('balance_mensual'), null, $hoy),
            ],
        ];
    }

    /**
     * @return list<array{digito: string, pdt: string|null, sire: string|null, estado: string}>
     */
    private function fechasRadar(?CronogramaSunat $principal, ?CronogramaSunat $sire, string $hoy): array
    {
        $campos = [
            '0' => 'digito_0',
            '1' => 'digito_1',
            '2' => 'digito_2_3',
            '3' => 'digito_2_3',
            '4' => 'digito_4_5',
            '5' => 'digito_4_5',
            '6' => 'digito_6_7',
            '7' => 'digito_6_7',
            '8' => 'digito_8_9',
            '9' => 'digito_8_9',
            'BC' => 'buenos_contribuyentes',
        ];

        $fechas = [];

        foreach ($campos as $digito => $campo) {
            $pdt = $principal?->{$campo} ?? $principal?->fecha_vencimiento_general;
            $fechaSire = $sire?->{$campo};

            if (! $pdt instanceof Carbon && ! $fechaSire instanceof Carbon) {
                continue;
            }

            $fechas[] = [
                'digito' => $digito,
                'pdt' => $pdt instanceof Carbon ? $pdt->format('d/m') : null,
                'sire' => $fechaSire instanceof Carbon ? $fechaSire->format('d/m') : null,
                'estado' => $this->estadoRadar($hoy, $pdt instanceof Carbon ? $pdt : null, $fechaSire instanceof Carbon ? $fechaSire : null),
            ];
        }

        return $fechas;
    }

    private function estadoRadar(string $hoy, ?Carbon $pdt, ?Carbon $sire): string
    {
        $estados = [];

        foreach ([$pdt, $sire] as $fecha) {
            if (! $fecha instanceof Carbon) {
                continue;
            }

            $dia = $fecha->toDateString();

            if ($dia === $hoy) {
                return 'hoy';
            }

            $estados[] = $dia < $hoy ? 'pasado' : 'futuro';
        }

        if ($estados === [] || in_array('futuro', $estados, true)) {
            return 'futuro';
        }

        return 'pasado';
    }

    /**
     * Avance de balances mensuales del periodo que vence este mes.
     *
     * SUNAT (R.S. N.° 234-2006/SUNAT) da tres meses de atraso máximo para
     * generar los libros y balances que se envían por el PLE. Solo entran
     * los regímenes obligados a llevarlos: MYPE y General.
     *
     * @return array{
     *     declarados: int,
     *     procesando: int,
     *     pendientes: int,
     *     total: int,
     *     porcentaje: int,
     *     periodo: string,
     *     anio: int,
     *     mes: int,
     *     nombre: string,
     *     vence: string|null
     * }
     */
    private function avanceBalancesEnCurso(Carbon $fecha): array
    {
        $periodo = $this->cronogramaService->obtenerPeriodoEnCurso('balance_mensual', $fecha);
        [$mesTexto, $anioTexto] = explode('-', $periodo);
        $mes = (int) $mesTexto;
        $anio = (int) $anioTexto;
        $periodoComparable = sprintf('%04d-%02d', $anio, $mes);

        $clientes = Cliente::query()
            ->whereIn('regimen_tributario', RegimenTributario::valoresConLibrosElectronicos())
            ->where(function ($query) use ($anio) {
                $query->where('estado', 'activo')
                    ->orWhere('estado', 'suspendido')
                    ->orWhere(function ($q) use ($anio) {
                        $q->where('estado', 'retirado')
                            ->whereNotNull('mes_baja')
                            ->where('mes_baja', '>=', "{$anio}-01");
                    });
            })
            ->with(['controlBalances' => fn ($query) => $query->where('anio', $anio)])
            ->get();

        $declarados = 0;
        $procesando = 0;
        $pendientes = 0;
        $total = 0;

        foreach ($clientes as $cliente) {
            $mesBaja = null;
            if ($cliente->isRetirado()) {
                $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
            }

            if ($mesBaja !== null && $periodoComparable >= $mesBaja) {
                continue;
            }

            $total++;
            $estado = $cliente->controlBalances->first()?->getEstadoMes($mes) ?? 'Pendiente';

            if ($estado === 'Declarado') {
                $declarados++;
            } elseif ($estado === 'Procesando' || $estado === 'Proceso') {
                $procesando++;
            } else {
                $pendientes++;
            }
        }

        $fechaVence = $this->cronogramaService->obtenerFechaVencimiento('balance_mensual', $periodo, '');
        $nombre = ucfirst(Carbon::createFromDate($anio, $mes, 1)->locale('es')->isoFormat('MMMM [de] YYYY'));

        return [
            'declarados' => $declarados,
            'procesando' => $procesando,
            'pendientes' => $pendientes,
            'total' => $total,
            'porcentaje' => $total > 0 ? (int) round(($declarados / $total) * 100) : 0,
            'periodo' => $periodo,
            'anio' => $anio,
            'mes' => $mes,
            'nombre' => str_replace(['Septiembre', 'septiembre'], ['Setiembre', 'setiembre'], $nombre),
            'vence' => $fechaVence?->format('d/m/Y'),
        ];
    }

    /**
     * Clientes con balances mensuales exigibles que aún no están declarados.
     *
     * Incluye el periodo que vence este mes y los que ya vencieron dentro
     * del último año. Solo MYPE y Régimen General llevan libros por el PLE.
     *
     * @return array<int, array{
     *     cliente: Cliente,
     *     meses: array<int, array{
     *         periodo: string,
     *         anio: int,
     *         mes: int,
     *         nombre: string,
     *         estado: string,
     *         estado_critico: array<string, mixed>
     *     }>,
     *     estado_critico: array<string, mixed>,
     *     fecha_critica: Carbon|null,
     *     anio: int
     * }>
     */
    private function alertasBalancesEnCurso(Carbon $fecha): array
    {
        $fecha = $fecha->copy()->timezone('America/Lima');
        $finMes = $fecha->copy()->endOfMonth()->toDateString();
        $desde = $fecha->copy()->subMonthsNoOverflow(12)->startOfMonth()->toDateString();

        $cronogramas = CronogramaSunat::query()
            ->where('tipo', 'balance_mensual')
            ->whereNotNull('fecha_vencimiento_general')
            ->whereDate('fecha_vencimiento_general', '>=', $desde)
            ->whereDate('fecha_vencimiento_general', '<=', $finMes)
            ->orderBy('fecha_vencimiento_general')
            ->get();

        $periodos = $cronogramas->map(function (CronogramaSunat $cronograma): array {
            $partes = explode('-', $cronograma->periodo);

            return [
                'periodo' => $cronograma->periodo,
                'anio' => (int) ($cronograma->anio ?: ($partes[1] ?? 0)),
                'mes' => (int) ($cronograma->mes ?: ($partes[0] ?? 0)),
                'fecha' => $cronograma->fecha_vencimiento_general
                    ? Carbon::parse($cronograma->fecha_vencimiento_general)
                    : null,
            ];
        })->filter(fn (array $periodo): bool => $periodo['mes'] >= 1 && $periodo['mes'] <= 12)->values();

        if ($periodos->isEmpty()) {
            $periodoAbierto = $this->cronogramaService->obtenerPeriodoEnCurso('balance_mensual', $fecha);
            [$mesTexto, $anioTexto] = explode('-', $periodoAbierto);
            $periodos->push([
                'periodo' => $periodoAbierto,
                'anio' => (int) $anioTexto,
                'mes' => (int) $mesTexto,
                'fecha' => $this->cronogramaService->obtenerFechaVencimiento('balance_mensual', $periodoAbierto, ''),
            ]);
        }

        $anios = $periodos->pluck('anio')->unique()->values();
        $anioMinimo = (int) $anios->min();

        $clientes = Cliente::query()
            ->whereIn('regimen_tributario', RegimenTributario::valoresConLibrosElectronicos())
            ->where(function ($query) use ($anioMinimo) {
                $query->where('estado', 'activo')
                    ->orWhere('estado', 'suspendido')
                    ->orWhere(function ($q) use ($anioMinimo) {
                        $q->where('estado', 'retirado')
                            ->whereNotNull('mes_baja')
                            ->where('mes_baja', '>=', "{$anioMinimo}-01");
                    });
            })
            ->with(['controlBalances' => fn ($query) => $query->whereIn('anio', $anios->all())])
            ->orderBy('razon_social')
            ->get();

        $alertas = [];

        foreach ($clientes as $cliente) {
            if (! $cliente->llevaBalances()) {
                continue;
            }

            $meses = [];

            foreach ($periodos as $periodo) {
                $periodoComparable = sprintf('%04d-%02d', $periodo['anio'], $periodo['mes']);
                $mesBaja = null;

                if ($cliente->isRetirado()) {
                    $mesBaja = $cliente->mes_baja ?: ($cliente->fecha_baja ? $cliente->fecha_baja->format('Y-m') : null);
                }

                if ($mesBaja !== null && $periodoComparable >= $mesBaja) {
                    continue;
                }

                $estado = $cliente->controlBalances->firstWhere('anio', $periodo['anio'])?->getEstadoMes($periodo['mes']) ?? 'Pendiente';

                if ($estado === 'Declarado') {
                    continue;
                }

                $nombre = str_replace(
                    ['Septiembre', 'septiembre'],
                    ['Setiembre', 'setiembre'],
                    ucfirst(Carbon::createFromDate($periodo['anio'], $periodo['mes'], 1)->locale('es')->isoFormat('MMMM YYYY'))
                );

                $meses[] = [
                    'periodo' => $periodo['periodo'],
                    'anio' => $periodo['anio'],
                    'mes' => $periodo['mes'],
                    'nombre' => $nombre,
                    'estado' => $estado,
                    'fecha' => $periodo['fecha'],
                    'estado_critico' => $this->cronogramaService->calcularEstadoVencimiento($periodo['fecha'], $fecha),
                ];
            }

            if ($meses === []) {
                continue;
            }

            usort($meses, fn (array $a, array $b): int => $this->compararUrgenciaBalance($a, $b));

            $alertas[] = [
                'cliente' => $cliente,
                'meses' => $meses,
                'estado_critico' => $meses[0]['estado_critico'],
                'fecha_critica' => $meses[0]['fecha'],
                'anio' => $meses[0]['anio'],
            ];
        }

        usort($alertas, fn (array $a, array $b): int => $this->compararUrgenciaBalance($a, $b));

        return $alertas;
    }

    /**
     * @param  array{estado_critico: array{estado: string}, fecha_critica?: Carbon|null, fecha?: Carbon|null}  $a
     * @param  array{estado_critico: array{estado: string}, fecha_critica?: Carbon|null, fecha?: Carbon|null}  $b
     */
    private function compararUrgenciaBalance(array $a, array $b): int
    {
        $prioridad = [
            'hoy' => 1,
            'vencido' => 2,
            'proximo' => 3,
            'al_dia' => 4,
            'sin_cronograma' => 5,
        ];

        $pesoA = $prioridad[$a['estado_critico']['estado']] ?? 9;
        $pesoB = $prioridad[$b['estado_critico']['estado']] ?? 9;

        if ($pesoA !== $pesoB) {
            return $pesoA <=> $pesoB;
        }

        $fechaA = $a['fecha_critica'] ?? $a['fecha'] ?? null;
        $fechaB = $b['fecha_critica'] ?? $b['fecha'] ?? null;

        if ($fechaA && $fechaB) {
            return $fechaA->timestamp <=> $fechaB->timestamp;
        }

        return 0;
    }

    /**
     * Saludo según la hora en Lima: mañana, tarde o noche.
     */
    private function saludoSegunHora(): string
    {
        $hora = now('America/Lima')->hour;

        if ($hora >= 5 && $hora < 12) {
            return 'Buenos días';
        }

        if ($hora >= 12 && $hora < 19) {
            return 'Buenas tardes';
        }

        return 'Buenas noches';
    }

    /**
     * Primer nombre del usuario autenticado, listo para mostrar en el saludo.
     */
    private function primerNombre(?string $nombreCompleto): string
    {
        $nombreCompleto = trim((string) $nombreCompleto);

        if ($nombreCompleto === '') {
            return '';
        }

        $partes = preg_split('/\s+/u', $nombreCompleto) ?: [];
        $primero = $partes[0] ?? '';

        if ($primero === '') {
            return '';
        }

        return mb_convert_case(mb_strtolower($primero, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
    }
}
