<?php

namespace App\Services;

use App\Models\AjusteSistema;
use App\Models\AlertaBuzon;
use App\Support\NodeBinary;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    /**
     * Callback personalizado para emular o interceptar el despacho en pruebas unitarias.
     *
     * @var (callable(string, string): array{success: bool, message: string, destinatario?: string})|null
     */
    protected $customRunner = null;

    /**
     * Configura un runner personalizado para tests.
     */
    public function setCustomRunner(?callable $runner): void
    {
        $this->customRunner = $runner;
    }

    /**
     * Normaliza un número telefónico al formato internacional E.164 (por defecto +51 para Perú).
     */
    public function normalizarTelefono(?string $telefono): ?string
    {
        if ($telefono === null) {
            return null;
        }

        // Quitar espacios, guiones, paréntesis y caracteres invisibles
        $limpio = preg_replace('/[^\d+]/', '', trim($telefono));

        if (empty($limpio)) {
            return null;
        }

        // Si ya tiene prefijo +, verificar dígitos
        if (str_starts_with($limpio, '+')) {
            $soloDigitos = substr($limpio, 1);
            if (strlen($soloDigitos) < 8 || strlen($soloDigitos) > 15) {
                return null;
            }

            return $limpio;
        }

        // Si tiene 9 dígitos (móvil estándar en Perú: ej. 987654321), anteponer +51
        if (preg_match('/^9\d{8}$/', $limpio)) {
            return '+51'.$limpio;
        }

        // Si tiene 11 dígitos y empieza con 51 (ej. 51987654321)
        if (preg_match('/^51(9\d{8})$/', $limpio)) {
            return '+'.$limpio;
        }

        // Si tiene entre 8 y 15 dígitos numéricos
        if (strlen($limpio) >= 8 && strlen($limpio) <= 15) {
            return '+'.$limpio;
        }

        return null;
    }

    /**
     * Formatea una alerta del buzón SOL según el estándar solicitado.
     */
    public function formatearMensajeAlerta(AlertaBuzon $alerta): string
    {
        $alerta->loadMissing('cliente');

        $empresa = $alerta->cliente ? $alerta->cliente->razon_social : 'Cliente No Especificado';
        $asunto = $alerta->asunto;
        $prioridad = $alerta->prioridad ?? 'Alta';

        $fechaRecepcion = $alerta->fecha_recepcion;
        $fecha = $fechaRecepcion
            ? ($fechaRecepcion instanceof \DateTimeInterface ? $fechaRecepcion->format('d/m/Y H:i') : (string) $fechaRecepcion)
            : now()->format('d/m/Y H:i');

        $plataforma = strtoupper($alerta->plataforma ?: 'SUNAT');

        return "🚨 ALERTA {$plataforma} DETECTADA\n"
            ."Empresa: {$empresa}\n"
            ."Asunto: {$asunto}\n"
            ."Prioridad: {$prioridad}\n"
            ."Fecha: {$fecha}";
    }

    /**
     * Formatea un mensaje de prueba para verificar la conectividad del canal.
     */
    public function formatearMensajePrueba(string $nombreEstudio = 'Grupo DC Soluciones Contables SAC'): string
    {
        $fecha = now()->format('d/m/Y H:i');

        return "🚨 ALERTA SUNAT DETECTADA (PRUEBA)\n"
            ."Empresa: {$nombreEstudio}\n"
            ."Asunto: NOTIFICACIÓN DE PRUEBA - VERIFICACIÓN DE CONEXIÓN WHATSAPP\n"
            ."Prioridad: Alta\n"
            ."Fecha: {$fecha}\n\n"
            .'Este es un mensaje de prueba emitido desde el panel de Ajustes del Sistema de CRM Contable.';
    }

    /**
     * Detecta la ruta del ejecutable de Node.js en el sistema o Laragon.
     */
    public function getNodeExecutable(): string
    {
        return NodeBinary::path();
    }

    /**
     * Despacha un mensaje de texto formateado hacia un destinatario utilizando el microservicio de WhatsApp.
     *
     * @return array{success: bool, message: string, destinatario?: string, data?: mixed}
     */
    public function enviarMensaje(string $destinatario, string $mensaje): array
    {
        $telefonoNormalizado = $this->normalizarTelefono($destinatario);

        if (! $telefonoNormalizado) {
            return [
                'success' => false,
                'message' => "El número de teléfono '{$destinatario}' no es válido o no cumple con el formato telefónico.",
            ];
        }

        // Si se inyectó un runner de prueba personalizado (para tests unitarios)
        if ($this->customRunner !== null) {
            return ($this->customRunner)($telefonoNormalizado, $mensaje);
        }

        // Conexión HTTP al Microservicio de WhatsApp (puerto 3001)
        $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/send-message';

        try {
            $response = Http::timeout(10)->asJson()->post($serverUrl, [
                'phone' => $telefonoNormalizado,
                'message' => $mensaje,
            ]);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'destinatario' => $telefonoNormalizado,
                    'message' => $data['message'] ?? "Mensaje despachado exitosamente vía WhatsApp a {$telefonoNormalizado}.",
                    'data' => $data,
                ];
            }

            // Si el microservicio respondió con error controlado (ej. 503 cliente no listo / QR pendiente)
            $errorData = $response->json();
            $errorMsg = $errorData['error'] ?? $errorData['message'] ?? ('Error HTTP '.$response->status().' desde el microservicio de WhatsApp.');
            Log::warning("Microservicio de WhatsApp retornó HTTP {$response->status()}: {$errorMsg}");

            return [
                'success' => false,
                'destinatario' => $telefonoNormalizado,
                'message' => $errorMsg,
                'data' => $errorData,
            ];
        } catch (\Throwable $e) {
            // Manejo de error si el servidor Node.js está apagado o no responde
            Log::warning("No se pudo conectar con el microservicio de WhatsApp ({$serverUrl}): {$e->getMessage()}");

            return [
                'success' => false,
                'destinatario' => $telefonoNormalizado,
                'message' => 'El microservicio de WhatsApp (puerto 3001) no está disponible o se encuentra apagado. Inicie el servidor con "npm run whatsapp" o "node scripts/whatsapp_server.cjs".',
            ];
        }
    }

    /**
     * Despacha una alerta oficial de SUNAT por WhatsApp.
     *
     * @return array{success: bool, message: string, destinatario?: string}
     */
    public function enviarAlerta(AlertaBuzon $alerta, ?string $destinatario = null): array
    {
        if (empty($destinatario)) {
            $ajustes = AjusteSistema::first() ?? AjusteSistema::getSettings();
            $destinatario = $ajustes->telefono_whatsapp;
        }

        if (empty($destinatario)) {
            return [
                'success' => false,
                'message' => 'No se ha configurado un número de WhatsApp en los ajustes del sistema.',
            ];
        }

        $mensaje = $this->formatearMensajeAlerta($alerta);

        return $this->enviarMensaje($destinatario, $mensaje);
    }

    /**
     * Despacha un conjunto de alertas recién capturadas a WhatsApp según la configuración del sistema,
     * garantizando idempotencia (solo despacha si no han sido notificadas previamente).
     *
     * @param  array<int, AlertaBuzon>  $alertas
     * @return int Cantidad de alertas despachadas exitosamente
     */
    public function despacharNuevasAlertas(array $alertas): int
    {
        if (empty($alertas)) {
            return 0;
        }

        $ajustes = AjusteSistema::first() ?? AjusteSistema::getSettings();

        if (! $ajustes->notificar_whatsapp || empty($ajustes->telefono_whatsapp)) {
            return 0;
        }

        $nivel = $ajustes->nivel_alerta_whatsapp;
        $despachadas = 0;

        foreach ($alertas as $alerta) {
            // Protección de idempotencia: nunca reenviar si ya tiene notificado_whatsapp_at
            if ($alerta->yaFueNotificadaWhatsApp()) {
                continue;
            }

            $esAlta = ($alerta->prioridad === 'Alta');

            if ($nivel === 'Todas' || ($nivel === 'Solo Altas' && $esAlta)) {
                $resultado = $this->enviarAlerta($alerta, $ajustes->telefono_whatsapp);

                if ($resultado['success']) {
                    $alerta->update(['notificado_whatsapp_at' => now()]);
                    $despachadas++;
                    Log::info("📲 [WhatsApp] Alerta despachada ({$alerta->prioridad}) para {$alerta->cliente?->razon_social}: '{$alerta->asunto}'");
                    if (! app()->runningUnitTests()) {
                        usleep(300000); // 0.3s para evitar saturación de socket
                    }
                } else {
                    Log::warning("⚠️ [WhatsApp] Fallo al enviar alerta: {$resultado['message']}");
                }
            }
        }

        return $despachadas;
    }

    /**
     * Despacha un mensaje de prueba al destinatario especificado o configurado.
     *
     * @return array{success: bool, message: string, destinatario?: string}
     */
    public function enviarMensajePrueba(?string $destinatario = null): array
    {
        $ajustes = AjusteSistema::first() ?? AjusteSistema::getSettings();

        if (empty($destinatario)) {
            $destinatario = $ajustes->telefono_whatsapp;
        }

        if (empty($destinatario)) {
            return [
                'success' => false,
                'message' => 'Debe ingresar o configurar un número de WhatsApp para realizar la prueba.',
            ];
        }

        $nombreEstudio = $ajustes->razon_social ?: 'Grupo DC Soluciones Contables SAC';
        $mensaje = $this->formatearMensajePrueba($nombreEstudio);

        return $this->enviarMensaje($destinatario, $mensaje);
    }

    /**
     * Verifica de forma ultra-rápida (sub-milisegundo) si el microservicio está escuchando en el puerto local.
     */
    public function isPortListening(?string $host = null, ?int $port = null, float $timeout = 0.15): bool
    {
        // En entorno de pruebas automatizadas con Http::fake(), evitar verificación de socket real
        if (app()->runningUnitTests()) {
            return true;
        }

        $url = config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001'));
        $targetHost = $host ?: (parse_url($url, PHP_URL_HOST) ?: '127.0.0.1');
        if ($targetHost === 'localhost') {
            $targetHost = '127.0.0.1';
        }
        $targetPort = $port ?: (parse_url($url, PHP_URL_PORT) ?: 3001);

        $fp = @fsockopen($targetHost, (int) $targetPort, $errno, $errstr, $timeout);
        if ($fp) {
            fclose($fp);

            return true;
        }

        return false;
    }

    /**
     * Consulta el estado en tiempo real del microservicio de WhatsApp y el QR disponible.
     *
     * @return array{status: string, qr: ?string, ready: bool, loading_percent?: ?int, loading_message?: ?string, error?: ?string, message?: string}
     */
    public function obtenerEstado(bool $autoStartIfOffline = false): array
    {
        if (! $this->isPortListening()) {
            if ($autoStartIfOffline && ! app()->runningUnitTests()) {
                $throttleKey = 'whatsapp_autostart_throttle';
                if (! Cache::has($throttleKey)) {
                    Cache::put($throttleKey, true, now()->addSeconds(30));
                    $this->iniciarServicio();
                }
            }

            return [
                'status' => 'offline',
                'qr' => null,
                'ready' => false,
                'message' => 'Microservicio de WhatsApp apagado o no disponible.',
            ];
        }

        $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/status';

        try {
            $response = Http::timeout(2)->get($serverUrl);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'status' => $data['status'] ?? 'loading',
                    'qr' => $data['qr'] ?? null,
                    'ready' => (bool) ($data['ready'] ?? false),
                    'loading_percent' => $data['loading_percent'] ?? null,
                    'loading_message' => $data['loading_message'] ?? null,
                    'error' => $data['error'] ?? null,
                ];
            }

            return [
                'status' => 'error',
                'qr' => null,
                'ready' => false,
                'message' => 'Error HTTP '.$response->status().' desde el microservicio.',
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'offline',
                'qr' => null,
                'ready' => false,
                'message' => 'Microservicio de WhatsApp apagado o no disponible.',
            ];
        }
    }

    /**
     * Envía la instrucción de cierre de sesión (logout) al microservicio de WhatsApp.
     *
     * @return array{success: bool, message: string}
     */
    public function cerrarSesion(): array
    {
        if (! $this->isPortListening()) {
            return [
                'success' => false,
                'message' => 'El microservicio de WhatsApp se encuentra apagado.',
            ];
        }

        $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/logout';

        try {
            $response = Http::timeout(5)->post($serverUrl);

            if ($response->successful()) {
                $data = $response->json();

                return [
                    'success' => true,
                    'message' => $data['message'] ?? 'Dispositivo desvinculado exitosamente.',
                ];
            }

            return [
                'success' => false,
                'message' => 'El microservicio retornó error al intentar desvincular.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'No se pudo conectar con el microservicio para cerrar la sesión.',
            ];
        }
    }

    /**
     * Verifica si el microservicio de WhatsApp está en ejecución.
     */
    public function isServiceRunning(): bool
    {
        if (! $this->isPortListening()) {
            return false;
        }

        $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/status';

        try {
            $response = Http::timeout(2)->get($serverUrl);

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Inicia el microservicio de WhatsApp en segundo plano sin abrir consola en Windows.
     *
     * @return array{success: bool, message: string}
     */
    public function iniciarServicio(): array
    {
        if ($this->isServiceRunning()) {
            return [
                'success' => true,
                'message' => 'El microservicio de WhatsApp ya se encuentra en ejecución.',
            ];
        }

        $nodeExecutable = $this->getNodeExecutable();
        $scriptPath = base_path('scripts/whatsapp_server.cjs');

        if (! file_exists($scriptPath)) {
            return [
                'success' => false,
                'message' => "No se encontró el script del servidor en: {$scriptPath}",
            ];
        }

        try {
            if (DIRECTORY_SEPARATOR === '\\') {
                $vbsPath = base_path('scripts/run_hidden.vbs');
                $logArg = '--log-file='.base_path('storage/logs/whatsapp_server.log');
                $command = sprintf(
                    'wscript.exe //B "%s" "%s" "%s" "%s" "%s"',
                    $vbsPath,
                    base_path(),
                    $logArg,
                    $nodeExecutable,
                    $scriptPath
                );
            } else {
                $command = sprintf(
                    'cd "%s" && "%s" "%s" > /dev/null 2>&1 &',
                    base_path(),
                    $nodeExecutable,
                    $scriptPath
                );
            }

            $handle = popen($command, 'r');
            if (is_resource($handle)) {
                pclose($handle);
            }

            // Espera activa breve para verificar el arranque
            for ($i = 0; $i < 6; $i++) {
                usleep(500000); // 0.5s
                if ($this->isServiceRunning()) {
                    return [
                        'success' => true,
                        'message' => 'Microservicio de WhatsApp iniciado correctamente.',
                    ];
                }
            }

            return [
                'success' => true,
                'message' => 'El proceso de arranque de WhatsApp fue despachado en segundo plano.',
            ];
        } catch (\Throwable $e) {
            Log::error('Error al iniciar microservicio de WhatsApp: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Error al iniciar el microservicio: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Detiene el microservicio de WhatsApp de forma controlada.
     *
     * @return array{success: bool, message: string}
     */
    public function detenerServicio(): array
    {
        if ($this->isPortListening()) {
            $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/shutdown';

            try {
                $response = Http::timeout(2)->post($serverUrl);
                if ($response->successful()) {
                    return [
                        'success' => true,
                        'message' => 'Microservicio de WhatsApp detenido exitosamente.',
                    ];
                }
            } catch (\Throwable) {
                // Si no responde por HTTP, forzar cierre del puerto 3001 en Windows si es necesario
            }
        }

        // Si sigue abierto o no respondió al HTTP shutdown en Windows
        if (DIRECTORY_SEPARATOR === '\\') {
            try {
                $cmd = 'powershell -NoProfile -Command "Get-NetTCPConnection -LocalPort 3001 -ErrorAction SilentlyContinue | Select-Object -ExpandProperty OwningProcess | ForEach-Object { Stop-Process -Id $_ -Force -ErrorAction SilentlyContinue }"';
                shell_exec($cmd);
            } catch (\Throwable) {
            }
        }

        return [
            'success' => true,
            'message' => 'Microservicio de WhatsApp detenido.',
        ];
    }

    /**
     * Reinicia el microservicio de WhatsApp.
     *
     * @return array{success: bool, message: string}
     */
    public function reiniciarServicio(): array
    {
        $this->detenerServicio();
        sleep(1);

        return $this->iniciarServicio();
    }

    /**
     * Restablece por completo la sesión (limpia caché/tokens corruptos y genera un nuevo QR).
     *
     * @return array{success: bool, message: string}
     */
    public function restablecerSesion(): array
    {
        if ($this->isPortListening()) {
            $serverUrl = rtrim(config('services.whatsapp.url', env('WHATSAPP_SERVER_URL', 'http://localhost:3001')), '/').'/reset-session';

            try {
                $response = Http::timeout(8)->post($serverUrl);
                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'success' => true,
                        'message' => $data['message'] ?? 'Sesión restablecida exitosamente.',
                    ];
                }
            } catch (\Throwable) {
            }
        }

        // Si el servicio no responde, detener y limpiar manualmente el directorio de sesión
        $this->detenerServicio();
        $authPath = base_path('.wwebjs_auth');
        if (is_dir($authPath)) {
            try {
                if (DIRECTORY_SEPARATOR === '\\') {
                    $cmd = sprintf('powershell -NoProfile -Command "Remove-Item -Path \'%s\' -Recurse -Force -ErrorAction SilentlyContinue"', $authPath);
                    shell_exec($cmd);
                } else {
                    shell_exec(sprintf('rm -rf "%s"', $authPath));
                }
            } catch (\Throwable) {
            }
        }

        return $this->iniciarServicio();
    }
}
