<?php

namespace App\Services;

use App\Models\AjusteSistema;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Support\NodeBinary;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class SunatBuzonSyncService
{
    /**
     * Callback opcional para sobrescribir la ejecución del scraper (ideal para pruebas).
     *
     * @var (Closure(string, string, string): array)|null
     */
    protected ?Closure $customRunner = null;

    /**
     * Permite fijar un runner personalizado para tests.
     *
     * @param  (Closure(string, string, string): array)|null  $runner
     */
    public function setCustomRunner(?Closure $runner): void
    {
        $this->customRunner = $runner;
    }

    /**
     * Detecta la ruta del ejecutable de Node.js en el sistema o en Laragon.
     */
    public function getNodeExecutable(): string
    {
        return NodeBinary::path();
    }

    /**
     * Obtiene las variables de entorno necesarias para la ejecución segura de Puppeteer en Windows.
     *
     * @return array<string, string>
     */
    public function getEnvironmentVariables(): array
    {
        $nodeDir = dirname($this->getNodeExecutable());
        $phpDir = dirname(PHP_BINARY);
        $systemPath = $nodeDir.';'.$phpDir.';C:\\Windows\\system32;C:\\Windows;C:\\Windows\\System32\\Wbem;'.(getenv('PATH') ?: '');

        $localAppData = getenv('LOCALAPPDATA') ?: (getenv('USERPROFILE') ? getenv('USERPROFILE').'\\AppData\\Local' : 'C:\\Windows\\Temp');
        $progFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
        $progFilesX86 = getenv('ProgramFiles(x86)') ?: 'C:\\Program Files (x86)';

        $env = [
            'PATH' => $systemPath,
            'SYSTEMROOT' => getenv('SYSTEMROOT') ?: 'C:\\Windows',
            'WINDIR' => getenv('WINDIR') ?: 'C:\\Windows',
            'USERPROFILE' => getenv('USERPROFILE') ?: 'C:\\Users\\Default',
            'LOCALAPPDATA' => $localAppData,
            'APPDATA' => getenv('APPDATA') ?: ($localAppData.'\\..\\Roaming'),
        ];

        $possibleChrome = [
            $progFiles.'\\Google\\Chrome\\Application\\chrome.exe',
            $progFilesX86.'\\Google\\Chrome\\Application\\chrome.exe',
            $localAppData.'\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            $progFiles.'\\Microsoft\\Edge\\Application\\msedge.exe',
            $progFilesX86.'\\Microsoft\\Edge\\Application\\msedge.exe',
            $localAppData.'\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            $progFiles.'\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
            $localAppData.'\\BraveSoftware\\Brave-Browser\\Application\\brave.exe',
        ];

        foreach ($possibleChrome as $chrome) {
            if ($chrome && file_exists($chrome)) {
                $env['PUPPETEER_EXECUTABLE_PATH'] = $chrome;
                break;
            }
        }

        return $env;
    }

    /**
     * Ejecuta el scraper de SUNAT para un RUC, Usuario y Clave SOL específicos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runScraper(string $ruc, string $usuario, string $clave): array
    {
        if ($this->customRunner !== null) {
            return ($this->customRunner)($ruc, $usuario, $clave);
        }

        $scriptPath = base_path('scripts/sunat_buzon.cjs');
        $nodeExecutable = $this->getNodeExecutable();
        $env = $this->getEnvironmentVariables();

        $ajustes = AjusteSistema::getSettings();
        $descargarPdf = $ajustes->descargar_adjuntos_pdf ? 'true' : 'false';
        $limite = (string) ($ajustes->limite_mensajes_buzon ?? 15);
        $pausa = (string) ($ajustes->pausa_anti_bloqueo_segundos ?? 3);

        $process = new Process(
            [$nodeExecutable, $scriptPath, $ruc, $usuario, $clave, $descargarPdf, $limite, $pausa],
            base_path(),
            $env,
            null,
            180
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput() ?: $process->getOutput());
            Log::warning("Fallo en ejecución de sunat_buzon.cjs para RUC {$ruc}: {$errorOutput}");
            throw new \RuntimeException($errorOutput ?: 'Error desconocido en el scraper de SUNAT.');
        }

        $output = trim($process->getOutput());

        $jsonStart = strpos($output, '[');
        $jsonEnd = strrpos($output, ']');

        if ($jsonStart === false || $jsonEnd === false) {
            Log::info("No se encontró arreglo JSON en la salida de SUNAT para RUC {$ruc}: {$output}");

            return [];
        }

        $jsonStr = substr($output, $jsonStart, ($jsonEnd - $jsonStart) + 1);
        $items = json_decode($jsonStr, true);

        return is_array($items) ? $items : [];
    }

    /**
     * Sincroniza las alertas del buzón SOL de un cliente individual.
     *
     * @return array{success: bool, nuevas: int, total: int, message: string}
     */
    /**
     * Términos que deben ser ignorados y NO guardados en alertas_buzon.
     *
     * @var array<int, string>
     */
    protected array $terminosSpam = [
        'RVIE',
        'RCE',
        'Plataforma de confirmación',
        'Plataforma de confirmacion',
        'Reporte Resumen',
        'Comprobantes',
        'Factura Electrónica',
        'Factura Electronica',
    ];

    /**
     * Cabeceras o filas de resumen interno de tablas SUNAT que no son notificaciones.
     *
     * @var array<int, string>
     */
    protected array $cabecerasBasura = [
        'Periodo',
        'Tributo',
        'T.Asociado',
        'Documento',
        'Pago',
        'Total :',
        'Total:',
    ];

    /**
     * Palabras clave del Diccionario Tributario de Alta Prioridad.
     *
     * @var array<int, string>
     */
    protected array $altaKeywords = [
        'Esquela',
        'Omiso',
        'Multa',
        'Embargo',
        'Valor',
        'Resolución',
        'Resolucion',
        'Coactiva',
        'Requerimiento',
        'Orden de Pago',
    ];

    /**
     * Evalúa si un asunto corresponde a spam o basura técnica que debe ser ignorado.
     */
    public function esSpamOBasura(string $asunto): bool
    {
        $asuntoTrim = trim($asunto);

        if (mb_strlen($asuntoTrim) < 4) {
            return true;
        }

        foreach ($this->cabecerasBasura as $cabecera) {
            if (strcasecmp($asuntoTrim, $cabecera) === 0) {
                return true;
            }
        }

        foreach ($this->terminosSpam as $termino) {
            if (stripos($asuntoTrim, $termino) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clasifica la prioridad del mensaje basándose en el diccionario tributario.
     */
    public function determinarPrioridad(string $asunto, ?string $contenido = null): string
    {
        $textoEvaluacion = $asunto.' '.($contenido ? strip_tags($contenido) : '');

        foreach ($this->altaKeywords as $kw) {
            if (stripos($textoEvaluacion, $kw) !== false) {
                return 'Alta';
            }
        }

        $mediaKeywords = [
            'declaraci',
            'vencimiento',
            'aviso',
            'recordatorio',
            'cronograma',
            'regulariz',
        ];

        foreach ($mediaKeywords as $kw) {
            if (stripos($textoEvaluacion, $kw) !== false) {
                return 'Media';
            }
        }

        return 'Baja';
    }

    /**
     * Sincroniza las alertas del buzón SOL de un cliente individual.
     *
     * @return array{success: bool, nuevas: int, total: int, message: string}
     */
    public function syncCliente(Cliente $cliente): array
    {
        if (! $cliente->isActivo() || $cliente->trashed()) {
            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'message' => "El cliente {$cliente->razon_social} no se encuentra activo o está en la papelera. Sincronización omitida para optimizar recursos.",
            ];
        }

        $cliente->loadMissing('credencial');

        if (! $cliente->credencial || empty($cliente->credencial->usuario_sol) || empty($cliente->credencial->clave_sol)) {
            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'message' => "El cliente {$cliente->razon_social} no tiene credenciales SOL configuradas.",
            ];
        }

        $ruc = (string) $cliente->ruc;
        $usuario = (string) $cliente->credencial->usuario_sol;
        $clave = (string) $cliente->credencial->clave_sol; // Desencriptado automático

        try {
            $alertasExtraidas = $this->runScraper($ruc, $usuario, $clave);

            $nuevas = 0;
            $totalValidas = 0;
            $nuevasAlertasItems = [];

            foreach ($alertasExtraidas as $alerta) {
                if (empty($alerta['asunto'])) {
                    continue;
                }

                $asunto = trim(preg_replace('/^ASUNTO:\s*/i', '', (string) $alerta['asunto']));

                // ─── 1. Filtro Anti-Spam y Descarte de Basura ────────────────────────
                if ($this->esSpamOBasura($asunto)) {
                    Log::info("Alerta descartada por filtro anti-spam para {$cliente->razon_social}: '{$asunto}'");

                    continue;
                }

                $contenidoBruto = ! empty($alerta['contenido']) ? trim((string) $alerta['contenido']) : null;
                $textoPlano = $contenidoBruto ? trim(strip_tags($contenidoBruto)) : '';

                $tienePdf = ! empty($alerta['tiene_pdf']) || ! empty($alerta['ruta_pdf']) || ! empty($alerta['ruta_constancia_pdf']);
                $rutaPdf = ! empty($alerta['ruta_pdf']) ? (string) $alerta['ruta_pdf'] : null;
                $nombreArchivoPdf = ! empty($alerta['nombre_archivo_pdf']) ? (string) $alerta['nombre_archivo_pdf'] : null;
                $rutaConstanciaPdf = ! empty($alerta['ruta_constancia_pdf']) ? (string) $alerta['ruta_constancia_pdf'] : null;
                $nombreArchivoConstanciaPdf = ! empty($alerta['nombre_archivo_constancia_pdf']) ? (string) $alerta['nombre_archivo_constancia_pdf'] : null;
                $numeroNotificacion = ! empty($alerta['numero_notificacion']) ? trim((string) $alerta['numero_notificacion']) : null;

                if (! $numeroNotificacion && preg_match('/(?:N[°o]\.?|Número|Numero|Valor|Resolución|Resolucion)\s*([0-9]{3,4}-[0-9]{3,4}-[0-9]{5,10}|[0-9]{10,16})/i', $asunto.' '.$textoPlano, $m)) {
                    $numeroNotificacion = trim($m[1]);
                }

                // ─── 2. Lectura fiel del mensaje tal cual llega de SUNAT ─────
                $contenido = ! empty($contenidoBruto) ? $contenidoBruto : 'Notificación oficial registrada en el Buzón Electrónico SOL.';
                $tienePdf = ! empty($alerta['tiene_pdf']) || ! empty($rutaPdf) || ! empty($rutaConstanciaPdf);
                $estado = in_array($alerta['estado'] ?? '', ['Pendiente', 'Leído']) ? $alerta['estado'] : 'Pendiente';
                $prioridad = $this->determinarPrioridad($asunto, $contenido);

                $fecha = ! empty($alerta['fecha_recepcion'])
                    ? Carbon::parse($alerta['fecha_recepcion'])
                    : (! empty($alerta['fecha']) ? Carbon::parse($alerta['fecha']) : now());

                // 3. Persistir en alertas_buzon con deduplicación inteligente
                $queryExistente = AlertaBuzon::where('cliente_id', $cliente->id)
                    ->where('plataforma', 'SUNAT');

                $registroExistente = null;
                if (! empty($numeroNotificacion)) {
                    $registroExistente = (clone $queryExistente)
                        ->where('numero_notificacion', $numeroNotificacion)
                        ->first();
                }

                if (! $registroExistente) {
                    $registroExistente = (clone $queryExistente)
                        ->where('asunto', $asunto)
                        ->whereDate('fecha_recepcion', $fecha->toDateString())
                        ->first();
                }

                if ($registroExistente) {
                    $registro = $registroExistente;
                    $registro->asunto = $asunto;
                    $registro->numero_notificacion = $numeroNotificacion ?? $registro->numero_notificacion;
                    $registro->contenido = $contenido;
                    $registro->prioridad = $prioridad;
                    if (! empty($rutaPdf)) {
                        $registro->ruta_pdf = $rutaPdf;
                        $registro->tiene_pdf = true;
                    }
                    if (! empty($nombreArchivoPdf)) {
                        $registro->nombre_archivo_pdf = $nombreArchivoPdf;
                    }
                    if (! empty($rutaConstanciaPdf)) {
                        $registro->ruta_constancia_pdf = $rutaConstanciaPdf;
                        $registro->tiene_pdf = true;
                    }
                    if (! empty($nombreArchivoConstanciaPdf)) {
                        $registro->nombre_archivo_constancia_pdf = $nombreArchivoConstanciaPdf;
                    }
                    if ($fecha->greaterThan($registro->fecha_recepcion ?? Carbon::minValue())) {
                        $registro->fecha_recepcion = $fecha;
                    }
                    $registro->save();
                    $fueCreadoReciente = false;
                } else {
                    $registro = AlertaBuzon::create([
                        'estudio_id' => $cliente->estudio_id,
                        'cliente_id' => $cliente->id,
                        'plataforma' => 'SUNAT',
                        'asunto' => $asunto,
                        'fecha_recepcion' => $fecha,
                        'numero_notificacion' => $numeroNotificacion,
                        'contenido' => $contenido,
                        'estado' => $estado,
                        'prioridad' => $prioridad,
                        'tiene_pdf' => $tienePdf,
                        'ruta_pdf' => $rutaPdf,
                        'nombre_archivo_pdf' => $nombreArchivoPdf,
                        'ruta_constancia_pdf' => $rutaConstanciaPdf,
                        'nombre_archivo_constancia_pdf' => $nombreArchivoConstanciaPdf,
                    ]);
                    $fueCreadoReciente = true;
                }

                $totalValidas++;

                $fueCreadoReciente = $registro->wasRecentlyCreated;
                $noNotificadoAun = ! $registro->yaFueNotificadaWhatsApp() && $registro->estado === 'Pendiente';

                if ($fueCreadoReciente) {
                    $nuevas++;
                }

                if ($fueCreadoReciente || $noNotificadoAun) {
                    $nuevasAlertasItems[] = $registro;
                }

                // 4. Mantener consistencia con la tabla tradicional 'notificaciones' si existe
                try {
                    $esImportante = ($prioridad === 'Alta');
                    $cliente->notificaciones()->updateOrCreate(
                        [
                            'fecha' => $fecha->toDateString(),
                            'asunto' => $asunto,
                        ],
                        [
                            'emisor' => 'SUNAT',
                            'leido' => ($estado === 'Leído'),
                            'es_importante' => $esImportante,
                        ]
                    );
                } catch (Throwable) {
                    // Si falla notificaciones legacy no detenemos el flujo principal de alertas_buzon
                }
            }

            Log::info("Sincronización SUNAT completada para {$cliente->razon_social}: {$nuevas} nuevas, {$totalValidas} válidas.");

            return [
                'success' => true,
                'nuevas' => $nuevas,
                'total' => $totalValidas,
                'nuevas_alertas_items' => $nuevasAlertasItems,
                'message' => "Se sincronizaron {$totalValidas} alertas ({$nuevas} nuevas) para {$cliente->razon_social}.",
            ];
        } catch (Throwable $e) {
            Log::error("Error sincronizando buzón SUNAT para {$cliente->razon_social}: ".$e->getMessage(), [
                'cliente_id' => $cliente->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'nuevas_alertas_items' => [],
                'message' => "Error al sincronizar con SUNAT: {$e->getMessage()}",
            ];
        }
    }

    /**
     * Itera y sincroniza todos los clientes activos con credenciales SOL.
     *
     * @return array{total_clientes: int, clientes_procesados: int, nuevas_alertas: int, nuevas_alertas_items: array<int, AlertaBuzon>, errores: array<int, string>}
     */
    public function syncAll(): array
    {
        $clientes = Cliente::activos()
            ->whereHas('credencial', function ($query) {
                $query->whereNotNull('usuario_sol')
                    ->where('usuario_sol', '!=', '')
                    ->whereNotNull('clave_sol')
                    ->where('clave_sol', '!=', '');
            })
            ->with('credencial')
            ->get();

        $procesados = 0;
        $totalNuevas = 0;
        $nuevasAlertasItems = [];
        $errores = [];

        $ajustes = AjusteSistema::getSettings();
        $pausaEntreClientes = max(2, (int) $ajustes->pausa_anti_bloqueo_segundos);

        $totalClientes = $clientes->count();
        $indice = 0;

        foreach ($clientes as $cliente) {
            $indice++;
            $resultado = $this->syncCliente($cliente);
            if ($resultado['success']) {
                $procesados++;
                $totalNuevas += $resultado['nuevas'];
                if (! empty($resultado['nuevas_alertas_items'])) {
                    $nuevasAlertasItems = array_merge($nuevasAlertasItems, $resultado['nuevas_alertas_items']);
                }
            } else {
                $errores[] = "{$cliente->razon_social} ({$cliente->ruc}): {$resultado['message']}";
            }

            // Pausa calibrada anti-bloqueo entre clientes
            if ($indice < $totalClientes) {
                sleep($pausaEntreClientes);
            }
        }

        $ajustes->update(['ultima_sincronizacion_at' => now()]);

        return [
            'total_clientes' => $clientes->count(),
            'clientes_procesados' => $procesados,
            'nuevas_alertas' => $totalNuevas,
            'nuevas_alertas_items' => $nuevasAlertasItems,
            'errores' => $errores,
        ];
    }
}
