<?php

namespace App\Http\Controllers;

use App\Models\AjusteSistema;
use App\Models\Estudio;
use App\Models\QuickLink;
use App\Services\WhatsAppService;
use App\Support\IdentidadMarca;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AjusteSistemaController extends Controller
{
    /**
     * Muestra el panel de ajustes y configuración del estudio contable.
     */
    public function index(): View
    {
        $ajustes = AjusteSistema::getSettings();
        $user = auth()->user();
        $estudio = $user?->estudio ?? Estudio::firstOrCreate(
            ['id' => $user?->estudio_id ?? 1],
            [
                'nombre' => $ajustes->razon_social,
                'ruc' => $ajustes->ruc,
                'representante' => $ajustes->representante,
                'correo' => $ajustes->correo_corporativo,
                'telefono' => $ajustes->telefono_whatsapp,
            ]
        );

        $enlacesRapidos = QuickLink::orderBy('orden')->orderBy('id')->get();
        $identidad = IdentidadMarca::desdeEstudio($estudio);

        return view('ajustes.index', compact('ajustes', 'estudio', 'enlacesRapidos', 'identidad'));
    }

    /**
     * Devuelve la paleta calculada para que Ajustes pueda mostrarla antes de guardarla.
     */
    public function previsualizarMarca(Request $request): JsonResponse
    {
        $base = (string) $request->query('color_base', IdentidadMarca::BASE);
        $acento = (string) $request->query('color_acento', IdentidadMarca::ACENTO);
        $problema = IdentidadMarca::problema($base, $acento);
        $baseNormalizada = IdentidadMarca::normalizar($base);
        $acentoNormalizado = IdentidadMarca::normalizar($acento);

        if ($problema !== null || $baseNormalizada === null || $acentoNormalizado === null) {
            return response()->json([
                'message' => $problema !== null ? reset($problema) : 'El color de marca no es válido.',
                'errors' => $problema ?? ['color_base' => ['El color de marca no es válido.']],
            ], 422);
        }

        $marca = new IdentidadMarca($baseNormalizada, $acentoNormalizado);

        return response()->json([
            'variables' => $marca->variables(),
        ]);
    }

    /**
     * Actualiza la configuración global del sistema (Perfil, RPA y WhatsApp).
     */
    public function update(Request $request): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'razon_social' => ['required_without:nombre', 'string', 'max:255'],
            'nombre' => ['required_without:razon_social', 'string', 'max:255'],
            'nombre_comercial' => ['nullable', 'string', 'max:150'],
            'ruc' => ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'],
            'representante' => ['required', 'string', 'max:255'],
            'colegiatura' => ['nullable', 'string', 'max:50'],
            'slogan' => ['nullable', 'string', 'max:255'],
            'correo' => ['nullable', 'email', 'max:255'],
            'correo_corporativo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:50'],
            'telefono_whatsapp' => ['nullable', 'string', 'max:50'],
            'sitio_web' => ['nullable', 'string', 'max:255'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'sidebar_display' => ['nullable', 'in:logo_nombre,logo_solo,nombre_solo'],
            'color_base' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_acento' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico,gif,avif', 'max:5120'],
            'eliminar_logo' => ['nullable', 'boolean'],
            'isotipo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico,gif,avif', 'max:5120'],
            'eliminar_isotipo' => ['nullable', 'boolean'],
            'logo_reportes' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico,gif,avif', 'max:5120'],
            'eliminar_logo_reportes' => ['nullable', 'boolean'],
            'frecuencia_cron_horas' => ['required', 'integer', 'in:2,4,6,8,12'],
            'notificar_whatsapp' => ['boolean'],
            'nivel_alerta_whatsapp' => ['required', 'in:Todas,Solo Altas'],
        ], [
            'color_base.regex' => 'El color de la barra debe ser un hexadecimal de 6 dígitos, por ejemplo #0B132B.',
            'color_acento.regex' => 'El color de acento debe ser un hexadecimal de 6 dígitos, por ejemplo #00C2CB.',
        ]);

        $nombre = $validated['nombre'] ?? $validated['razon_social'] ?? 'Estudio Contable';
        $correo = $validated['correo'] ?? $validated['correo_corporativo'] ?? null;
        $telefono = $validated['telefono'] ?? $validated['telefono_whatsapp'] ?? null;

        $user = auth()->user();
        $estudio = $user?->estudio ?? Estudio::firstOrCreate(['id' => $user?->estudio_id ?? 1], ['nombre' => $nombre]);
        $configuracion = $estudio->configuracion ?? [];

        if ($request->exists('color_base') || $request->exists('color_acento')) {
            $colorBase = (string) ($validated['color_base'] ?? ($configuracion['color_base'] ?? IdentidadMarca::BASE));
            $colorAcento = (string) ($validated['color_acento'] ?? ($configuracion['color_acento'] ?? IdentidadMarca::ACENTO));
            $problema = IdentidadMarca::problema($colorBase, $colorAcento);
            $colorBase = IdentidadMarca::normalizar($colorBase);
            $colorAcento = IdentidadMarca::normalizar($colorAcento);

            if ($problema !== null || $colorBase === null || $colorAcento === null) {
                throw ValidationException::withMessages($problema ?? [
                    'color_base' => 'El color de marca no es válido.',
                ]);
            }

            $configuracion['color_base'] = $colorBase;
            $configuracion['color_acento'] = $colorAcento;
        }

        // Manejo de logotipo completo (barra extendida)
        if ($request->boolean('eliminar_logo')) {
            if ($estudio->logo_path && Storage::disk('public')->exists($estudio->logo_path)) {
                Storage::disk('public')->delete($estudio->logo_path);
            }
            $estudio->logo_path = null;
            unset($configuracion['logo_nombre_original']);
        } elseif ($request->hasFile('logo')) {
            if ($estudio->logo_path && Storage::disk('public')->exists($estudio->logo_path)) {
                Storage::disk('public')->delete($estudio->logo_path);
            }
            $estudio->logo_path = $request->file('logo')->store('estudios/logos/'.$estudio->id, 'public');
            $configuracion['logo_nombre_original'] = $request->file('logo')->getClientOriginalName();
        }

        // Manejo de isotipo (símbolo para barra contraída)
        if ($request->boolean('eliminar_isotipo')) {
            if ($estudio->isotipo_path && Storage::disk('public')->exists($estudio->isotipo_path)) {
                Storage::disk('public')->delete($estudio->isotipo_path);
            }
            $estudio->isotipo_path = null;
            unset($configuracion['isotipo_nombre_original']);
        } elseif ($request->hasFile('isotipo')) {
            if ($estudio->isotipo_path && Storage::disk('public')->exists($estudio->isotipo_path)) {
                Storage::disk('public')->delete($estudio->isotipo_path);
            }
            $estudio->isotipo_path = $request->file('isotipo')->store('estudios/isotipos/'.$estudio->id, 'public');
            $configuracion['isotipo_nombre_original'] = $request->file('isotipo')->getClientOriginalName();
        }

        // Manejo de logotipo para documentos e impresión (fondo claro)
        if ($request->boolean('eliminar_logo_reportes')) {
            if ($estudio->logo_reportes_path && Storage::disk('public')->exists($estudio->logo_reportes_path)) {
                Storage::disk('public')->delete($estudio->logo_reportes_path);
            }
            $estudio->logo_reportes_path = null;
            unset($configuracion['logo_reportes_nombre_original']);
        } elseif ($request->hasFile('logo_reportes')) {
            if ($estudio->logo_reportes_path && Storage::disk('public')->exists($estudio->logo_reportes_path)) {
                Storage::disk('public')->delete($estudio->logo_reportes_path);
            }
            $estudio->logo_reportes_path = $request->file('logo_reportes')->store('estudios/logos_reportes/'.$estudio->id, 'public');
            $configuracion['logo_reportes_nombre_original'] = $request->file('logo_reportes')->getClientOriginalName();
        }

        $estudio->configuracion = $configuracion;
        $estudio->nombre = $nombre;
        $estudio->ruc = $validated['ruc'];
        $estudio->representante = $validated['representante'];
        $estudio->correo = $correo;
        $estudio->telefono = $telefono;
        if (array_key_exists('nombre_comercial', $validated)) {
            $estudio->nombre_comercial = $validated['nombre_comercial'];
        }
        if (array_key_exists('colegiatura', $validated)) {
            $estudio->colegiatura = $validated['colegiatura'];
        }
        if (array_key_exists('slogan', $validated)) {
            $estudio->slogan = $validated['slogan'];
        }
        if (array_key_exists('sitio_web', $validated)) {
            $estudio->sitio_web = $validated['sitio_web'];
        }
        if (array_key_exists('direccion', $validated)) {
            $estudio->direccion = $validated['direccion'];
        }
        if (array_key_exists('ciudad', $validated)) {
            $estudio->ciudad = $validated['ciudad'];
        }
        if (! empty($validated['sidebar_display'])) {
            $estudio->sidebar_display = $validated['sidebar_display'];
        }
        $estudio->save();

        $ajustes = AjusteSistema::getSettings();
        $ajustes->update([
            'razon_social' => $nombre,
            'ruc' => $validated['ruc'],
            'representante' => $validated['representante'],
            'correo_corporativo' => $correo,
            'telefono_whatsapp' => $validated['telefono_whatsapp'] ?? $telefono,
            'frecuencia_cron_horas' => (int) $validated['frecuencia_cron_horas'],
            'notificar_whatsapp' => (bool) $request->boolean('notificar_whatsapp'),
            'nivel_alerta_whatsapp' => $validated['nivel_alerta_whatsapp'],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Ajustes del sistema actualizados correctamente.',
                'ajustes' => $ajustes,
                'estudio' => $estudio,
                'logo_url' => $estudio->logoUrl(),
                'logo_nombre' => $estudio->logoNombreOriginal(),
                'isotipo_url' => $estudio->isotipo_path ? asset('storage/'.$estudio->isotipo_path) : null,
                'isotipo_nombre' => $estudio->isotipoNombreOriginal(),
                'logo_reportes_url' => $estudio->logoReportesUrl(),
                'logo_reportes_nombre' => $estudio->logoReportesNombreOriginal(),
                'marca' => IdentidadMarca::desdeEstudio($estudio)->variables(),
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
