<?php

namespace App\Http\Controllers;

use App\Enums\RegimenTributario;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Services\CronogramaSunatService;
use App\Services\TipoCambioSunatService;
use Carbon\Carbon;
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

        // Cargar clientes activos con su operación del mes actual
        $clientesActivos = Cliente::where('estado', 'activo')
            ->with(['operaciones' => fn ($query) => $query->where('periodo', $periodoActual)])
            ->get();

        $totalObligaciones = $clientesActivos->count(); // 1 obligación DJ unificada por cliente/mes

        $declarados = 0;
        $procesando = 0;
        $pendientes = 0;

        $alertasVencimiento = [];

        foreach ($clientesActivos as $cliente) {
            $operacion = $cliente->operaciones->first();

            $sire = $operacion->estado_compras_ventas ?? 'Pendiente';
            $pdt = $operacion->estado_pdt621 ?? 'Pendiente';
            $ple = $operacion->estado_ple ?? 'Pendiente';

            // Conteo unificado: 1 mes = 1 DJ (estadoMaestro)
            if ($sire === 'Declarado' && $pdt === 'Declarado' && $ple === 'Declarado') {
                $declarados++;
            } elseif ($sire === 'Procesando' || $pdt === 'Procesando' || $ple === 'Procesando'
                || $sire === 'Declarado' || $pdt === 'Declarado' || $ple === 'Declarado') {
                $procesando++;
            } else {
                $pendientes++;
            }

            // Detalle individual para alertas de vencimiento (útil para saber cuál falta)
            $faltantes = [];
            if ($sire !== 'Declarado') {
                $faltantes[] = ['nombre' => 'SIRE', 'estado' => $sire];
            }
            if ($pdt !== 'Declarado') {
                $faltantes[] = ['nombre' => 'PDT 621', 'estado' => $pdt];
            }
            if ($ple !== 'Declarado') {
                $faltantes[] = ['nombre' => 'PDT 601', 'estado' => $ple];
            }

            if (! empty($faltantes)) {
                $ultimoDigito = substr($cliente->ruc, -1);
                $fechaVencimientoPdt = $this->cronogramaService->obtenerFechaVencimiento('pdt_621', $periodoActual, $cliente);
                $fechaVencimientoSire = $this->cronogramaService->obtenerFechaVencimiento('sire', $periodoActual, $cliente);

                // Determinar fecha crítica según lo que falte
                $fechaCritica = ($sire !== 'Declarado' && $fechaVencimientoSire) ? $fechaVencimientoSire : $fechaVencimientoPdt;
                $estadoCritico = $this->cronogramaService->calcularEstadoVencimiento($fechaCritica);

                $alertasVencimiento[] = [
                    'cliente' => $cliente,
                    'ultimo_digito' => (int) $ultimoDigito,
                    'ultimo_digito_str' => $ultimoDigito,
                    'faltantes' => $faltantes,
                    'total_faltantes' => count($faltantes),
                    'fecha_pdt' => $fechaVencimientoPdt,
                    'fecha_sire' => $fechaVencimientoSire,
                    'fecha_critica' => $fechaCritica,
                    'estado_critico' => $estadoCritico,
                ];
            }
        }

        // Ordenar con inteligencia de negocio:
        // 1. Vence Hoy y Vencidos primero (urgencia fiscal crítica)
        // 2. Por fecha crítica más próxima
        // 3. Por último dígito del RUC (0 al 9)
        usort($alertasVencimiento, function ($a, $b) {
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
