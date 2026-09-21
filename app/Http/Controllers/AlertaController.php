<?php

namespace App\Http\Controllers;

use App\Models\AlertaBuzon;
use App\Services\SunafilBuzonSyncService;
use App\Services\SunatBuzonSyncService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

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
        $totalAlertas = AlertaBuzon::count();
        $totalPendientes = AlertaBuzon::where('estado', 'Pendiente')->count();
        $totalLeidos = AlertaBuzon::where('estado', 'Leído')->count();
        $totalAltaPrioridad = AlertaBuzon::where('estado', 'Pendiente')->where('prioridad', 'Alta')->count();
        $totalSunatPendientes = AlertaBuzon::where('estado', 'Pendiente')->where('plataforma', 'SUNAT')->count();
        $totalSunafilPendientes = AlertaBuzon::where('estado', 'Pendiente')->where('plataforma', 'SUNAFIL')->count();

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
}
