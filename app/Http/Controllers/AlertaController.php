<?php

namespace App\Http\Controllers;

use App\Models\AlertaBuzon;
use App\Services\AlertaDocumentService;
use App\Services\SunafilBuzonSyncService;
use App\Services\SunatBuzonSyncService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AlertaController extends Controller
{
    /**
     * Muestra la bandeja de entrada global (Inbox) de notificaciones oficiales SUNAT / SUNAFIL.
     */
    public function index(Request $request): View
    {
        $query = AlertaBuzon::with('cliente');

        // Filtro por plataforma (SUNAT / SUNAFIL)
        if ($request->filled('plataforma')) {
            $query->where('plataforma', $request->query('plataforma'));
        }

        // Filtro por estado (Pendiente / Leído)
        if ($request->filled('estado')) {
            $query->where('estado', $request->query('estado'));
        }

        // Filtro por prioridad (Alta / Media / Baja)
        if ($request->filled('prioridad')) {
            $query->where('prioridad', $request->query('prioridad'));
        }

        // Búsqueda por texto (asunto, contenido o datos del cliente)
        if ($request->filled('q')) {
            $term = '%'.$request->query('q').'%';
            $query->where(function ($q) use ($term) {
                $q->where('asunto', 'like', $term)
                    ->orWhere('contenido', 'like', $term)
                    ->orWhereHas('cliente', function ($subQ) use ($term) {
                        $subQ->where('razon_social', 'like', $term)
                            ->orWhere('ruc', 'like', $term);
                    });
            });
        }

        $alertas = $query->orderBy('fecha_recepcion', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        // Estadísticas rápidas para tarjetas y filtros
        $alertaStats = AlertaBuzon::selectRaw('
            COUNT(*) as total_alertas,
            COUNT(CASE WHEN estado = "Pendiente" THEN 1 END) as total_pendientes,
            COUNT(CASE WHEN estado = "Leído" THEN 1 END) as total_leidos,
            COUNT(CASE WHEN estado = "Pendiente" AND prioridad = "Alta" THEN 1 END) as total_alta_prioridad,
            COUNT(CASE WHEN estado = "Pendiente" AND plataforma = "SUNAT" THEN 1 END) as total_sunat_pendientes,
            COUNT(CASE WHEN estado = "Pendiente" AND plataforma = "SUNAFIL" THEN 1 END) as total_sunafil_pendientes
        ')->first();

        $totalAlertas = (int) ($alertaStats->total_alertas ?? 0);
        $totalPendientes = (int) ($alertaStats->total_pendientes ?? 0);
        $totalLeidos = (int) ($alertaStats->total_leidos ?? 0);
        $totalAltaPrioridad = (int) ($alertaStats->total_alta_prioridad ?? 0);
        $totalSunatPendientes = (int) ($alertaStats->total_sunat_pendientes ?? 0);
        $totalSunafilPendientes = (int) ($alertaStats->total_sunafil_pendientes ?? 0);

        return view('alertas.index', compact(
            'alertas',
            'totalAlertas',
            'totalPendientes',
            'totalLeidos',
            'totalAltaPrioridad',
            'totalSunatPendientes',
            'totalSunafilPendientes'
        ));
    }

    /**
     * Marca o conmuta el estado de una alerta ('Leído' / 'Pendiente') vía AJAX.
     */
    public function markAsRead(Request $request, AlertaBuzon $alerta): JsonResponse
    {
        if ($request->has('estado')) {
            $alerta->estado = $request->input('estado') === 'Leído' ? 'Leído' : 'Pendiente';
        } else {
            $alerta->estado = ($alerta->estado === 'Pendiente') ? 'Leído' : 'Pendiente';
        }
        $alerta->save();

        $pendientesCount = AlertaBuzon::where('estado', 'Pendiente')->count();
        $clientePendientesCount = AlertaBuzon::where('cliente_id', $alerta->cliente_id)->where('estado', 'Pendiente')->count();

        return response()->json([
            'success' => true,
            'message' => $alerta->estado === 'Leído' ? 'Alerta marcada como leída exitosamente.' : 'Alerta marcada como pendiente.',
            'alerta' => $alerta->load('cliente'),
            'nuevo_estado' => $alerta->estado,
            'pendientesCount' => $pendientesCount,
            'clientePendientesCount' => $clientePendientesCount,
        ]);
    }

    /**
     * Dispara la sincronización masiva de todas las alertas SUNAT para clientes activos con credenciales.
     */
    public function syncAll(Request $request, SunatBuzonSyncService $syncService, WhatsAppService $whatsAppService): JsonResponse|RedirectResponse
    {
        $resultado = $syncService->syncAll();

        // Despacho automático e idempotente a WhatsApp para nuevas alertas
        $despachadas = $whatsAppService->despacharNuevasAlertas($resultado['nuevas_alertas_items'] ?? []);

        $mensaje = "Sincronización masiva finalizada. Se procesaron {$resultado['clientes_procesados']} clientes y se registraron {$resultado['nuevas_alertas']} nuevas alertas.";
        if ($despachadas > 0) {
            $mensaje .= " Se despacharon {$despachadas} alertas a WhatsApp.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => $resultado,
                'whatsapp_despachadas' => $despachadas,
            ]);
        }

        return redirect()->route('alertas.index')->with('success', $mensaje);
    }

    /**
     * Dispara la sincronización masiva de todas las alertas SUNAFIL para clientes activos con credenciales.
     */
    public function syncSunafil(Request $request, SunafilBuzonSyncService $syncService, WhatsAppService $whatsAppService): JsonResponse|RedirectResponse
    {
        $resultado = $syncService->syncAll();

        // Despacho automático e idempotente a WhatsApp para nuevas alertas
        $despachadas = $whatsAppService->despacharNuevasAlertas($resultado['nuevas_alertas_items'] ?? []);

        $mensaje = "Sincronización SUNAFIL finalizada. Se procesaron {$resultado['clientes_procesados']} clientes y se registraron {$resultado['nuevas_alertas']} nuevas alertas.";
        if ($despachadas > 0) {
            $mensaje .= " Se despacharon {$despachadas} alertas a WhatsApp.";
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $mensaje,
                'data' => $resultado,
                'whatsapp_despachadas' => $despachadas,
            ]);
        }

        return redirect()->route('alertas.index')->with('success', $mensaje);
    }

    /**
     * Descarga o visualiza el documento PDF oficial emitido por SUNAT o SUNAFIL (documento o constancia).
     */
    public function downloadPdf(Request $request, AlertaBuzon $alerta, AlertaDocumentService $docService): BinaryFileResponse|RedirectResponse|JsonResponse
    {
        $alerta->loadMissing('cliente.credencial');

        if (auth()->check() && auth()->user()->estudio_id && $alerta->cliente && (int) $alerta->cliente->estudio_id !== (int) auth()->user()->estudio_id) {
            abort(403, 'No autorizado para acceder a este documento.');
        }

        $tipo = $request->query('tipo', 'documento') === 'constancia' ? 'constancia' : 'documento';
        $ruta = $docService->asegurarDocumento($alerta, $tipo);

        // Si el archivo físico no está disponible pero el cliente tiene credenciales SOL y es de SUNAT,
        // intentamos una sincronización en demanda para obtener el archivo original.
        if (! $ruta && $alerta->plataforma === 'SUNAT' && $alerta->cliente?->credencial?->usuario_sol) {
            try {
                app(SunatBuzonSyncService::class)->syncCliente($alerta->cliente);
                $alerta->refresh();
                $ruta = $docService->asegurarDocumento($alerta, $tipo);
            } catch (\Throwable $e) {
                \Log::warning("Fallo en sincronización on-demand de PDF para alerta {$alerta->id}: {$e->getMessage()}");
            }
        }

        if (! $ruta) {
            $mensaje = ($tipo === 'constancia')
                ? 'Esta comunicación oficial no contiene una constancia de notificación adjunta en el Buzón SOL.'
                : 'Esta comunicación oficial no contiene un archivo PDF adjunto emitido por SUNAT en el Buzón SOL.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $mensaje,
                ], 404);
            }

            return redirect()->back()->with('warning', $mensaje);
        }

        return $docService->descargar($alerta, $request->boolean('inline'), $tipo);
    }
}
