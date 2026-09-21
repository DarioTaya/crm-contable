<?php

namespace App\Http\Controllers;

use App\Models\AjusteSistema;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AjusteSistemaController extends Controller
{
    /**
     * Muestra el panel de ajustes y configuración del estudio contable.
     */
    public function index(): View
    {
        $ajustes = AjusteSistema::getSettings();

        return view('ajustes.index', compact('ajustes'));
    }

    /**
     * Actualiza la configuración global del sistema (Perfil, RPA y WhatsApp).
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'ruc' => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'representante' => ['required', 'string', 'max:255'],
            'correo_corporativo' => ['nullable', 'email', 'max:255'],
            'telefono_whatsapp' => ['nullable', 'string', 'max:50'],
            'frecuencia_cron_horas' => ['required', 'integer', 'in:2,4,6,8,12'],
            'notificar_whatsapp' => ['boolean'],
            'nivel_alerta_whatsapp' => ['required', 'in:Todas,Solo Altas'],
        ]);

        $ajustes = AjusteSistema::getSettings();

        $ajustes->update([
            'razon_social' => $validated['razon_social'],
            'ruc' => $validated['ruc'],
            'representante' => $validated['representante'],
            'correo_corporativo' => $validated['correo_corporativo'] ?? null,
            'telefono_whatsapp' => $validated['telefono_whatsapp'] ?? null,
            'frecuencia_cron_horas' => (int) $validated['frecuencia_cron_horas'],
            'notificar_whatsapp' => (bool) ($request->boolean('notificar_whatsapp')),
            'nivel_alerta_whatsapp' => $validated['nivel_alerta_whatsapp'],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ajustes del sistema actualizados correctamente.',
                'ajustes' => $ajustes,
            ]);
        }

        return redirect()->route('ajustes.index')->with('success', 'Ajustes del sistema actualizados correctamente.');
    }

    /**
     * Despacha una alerta de prueba al número configurado para validar la integración con WhatsApp.
     */
    public function testWhatsApp(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $ajustes = AjusteSistema::first() ?? AjusteSistema::getSettings();
        $telefono = $request->input('telefono_whatsapp') ?: $ajustes->telefono_whatsapp;

        if (empty($telefono)) {
            return response()->json([
                'success' => false,
                'message' => 'Debe ingresar o guardar un número de WhatsApp antes de realizar la prueba de conexión.',
            ], 422);
        }

        $resultado = $whatsAppService->enviarMensajePrueba($telefono);

        if ($resultado['success']) {
            return response()->json([
                'success' => true,
                'message' => $resultado['message'],
                'destinatario' => $resultado['destinatario'] ?? $telefono,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $resultado['message'] ?? 'Ocurrió un error al despachar la alerta de prueba.',
        ], 400);
    }

    /**
     * Consulta el estado en tiempo real del microservicio de WhatsApp y el QR disponible.
     */
    public function whatsappStatus(Request $request, WhatsAppService $whatsAppService): JsonResponse
    {
        $autoStart = $request->boolean('auto_start', true);
        $estado = $whatsAppService->obtenerEstado($autoStart);

        return response()->json($estado);
    }

    /**
     * Cierra la sesión de WhatsApp en el microservicio para permitir reescanear el QR.
     */
    public function whatsappLogout(WhatsAppService $whatsAppService): JsonResponse
    {
        $resultado = $whatsAppService->cerrarSesion();

        return response()->json($resultado, $resultado['success'] ? 200 : 400);
    }

    /**
     * Inicia el microservicio de WhatsApp en segundo plano sin consola.
     */
    public function whatsappStart(WhatsAppService $whatsAppService): JsonResponse
    {
        $resultado = $whatsAppService->iniciarServicio();

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * Detiene el microservicio de WhatsApp.
     */
    public function whatsappStop(WhatsAppService $whatsAppService): JsonResponse
    {
        $resultado = $whatsAppService->detenerServicio();

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * Reinicia el microservicio de WhatsApp.
     */
    public function whatsappRestart(WhatsAppService $whatsAppService): JsonResponse
    {
        $resultado = $whatsAppService->reiniciarServicio();

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }

    /**
     * Restablece por completo la sesión de WhatsApp para generar un nuevo código QR limpio.
     */
    public function whatsappResetSession(WhatsAppService $whatsAppService): JsonResponse
    {
        $resultado = $whatsAppService->restablecerSesion();

        return response()->json($resultado, $resultado['success'] ? 200 : 500);
    }
}
