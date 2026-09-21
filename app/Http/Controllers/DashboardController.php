<?php

namespace App\Http\Controllers;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Services\CronogramaSunatService;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        protected CronogramaSunatService $cronogramaService
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
                $faltantes[] = ['nombre' => 'PLE', 'estado' => $ple];
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

        return view('dashboard', compact(
            'totalClientesActivos',
            'totalClientesInactivos',
            'notificacionesPendientes',
            'notificacionesImportantes',
            'periodoActual',
            'nombreMesActual',
            'mesVencimientoNombre',
            'totalObligaciones',
            'declarados',
            'procesando',
            'pendientes',
            'porcentajeAvance',
            'alertasVencimiento'
        ));
    }
}
