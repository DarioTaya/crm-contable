<?php

namespace App\Services;

use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Support\NodeBinary;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

class SunafilBuzonSyncService
{
    /**
     * Callback opcional para sobrescribir la ejecución del scraper (ideal para pruebas unitarias).
     *
     * @var (Closure(string, string, string): array)|null
     */
    protected ?Closure $customRunner = null;

    /**
     * Cabeceras o filas de resumen interno que no corresponden a notificaciones laborales legítimas.
     *
     * @var array<int, string>
     */
    protected array $cabecerasBasura = [
        'Nro',
        'Número',
        'Numero',
        'Fecha',
        'Asunto',
        'Documento',
        'Estado',
        'Acciones',
        'Total',
        'Tipo',
        'Remitente',
        'Descargar',
        'Ver',
        'Detalle',
        'Tipo de Requerimiento',
        'Fecha de Depósito',
        'Fecha de Deposito',
        'Registro',
        'Fecha Acuse de Recibo',
        'Bandeja',
        'Casilla Electrónica',
        'Casilla Electronica',
        'Página de inicio',
        'Pagina de inicio',
        'Inicio',
        'Sunafil te Orienta',
        'Sunafil te orienta',
        'Actos Administrativos y Actuaciones Inspectivas',
        'Actos Administrativos',
        'Actuaciones Inspectivas',
        'Acciones Previas',
        'Notificaciones de Fiscalización',
        'Notificaciones de Fiscalizacion',
        'Notificaciones de Cobranza',
        'Formalización',
        'Formalizacion',
        'Seguridad y Salud en el Trabajo',
        'Cerrar Sesión',
        'Cerrar Sesion',
        'Salir',
        'Ningún dato disponible en esta tabla',
        'Ningun dato disponible en esta tabla',
        'Sin notificaciones',
    ];

    /**
     * Palabras clave del Diccionario Laboral de Alta Prioridad SUNAFIL.
     *
     * @var array<int, string>
     */
    protected array $altaKeywords = [
        'Carta Disuasiva',
        'Cartas Disuasivas',
        'Disuasiva',
        'Inductiva',
        'Carta Inductiva',
        'Cartas Inductivas',
        'Resolución',
        'Resolucion',
        'Infracción',
        'Infraccion',
        'Formalización',
        'Formalizacion',
        'SST',
        'Seguridad y Salud en el Trabajo',
        'Seguridad y Salud',
        'Comparecencia',
        'Inspectiva',
        'Multa',
        'Requerimiento',
        'Medida',
        'Sancion',
        'Sanción',
        'Paralizacion',
        'Paralización',
        'Cierre',
        'Acta de Infracción',
        'Acta de Infraccion',
    ];

    /**
     * Palabras clave para nivel de prioridad Media.
     *
     * @var array<int, string>
     */
    protected array $mediaKeywords = [
        'Aviso',
        'Recordatorio',
        'Prevenci',
        'Orientaci',
        'Informativ',
        'Comunicaci',
        'Plazo',
        'Cronograma',
    ];

    /**
     * Permite fijar un runner personalizado para pruebas automáticas.
     *
     * @param  (Closure(string, string, string): array)|null  $runner
     */
    public function setCustomRunner(?Closure $runner): void
    {
        $this->customRunner = $runner;
    }

    /**
     * Detecta la ruta del ejecutable de Node.js en el entorno Laragon o sistema operativo.
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
     * Ejecuta el scraper de SUNAFIL para un RUC, Usuario y Clave SOL específicos.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runScraper(string $ruc, string $usuario, string $clave): array
    {
        if ($this->customRunner !== null) {
            return ($this->customRunner)($ruc, $usuario, $clave);
        }

        $scriptPath = base_path('scripts/sunafil_buzon.cjs');
        $nodeExecutable = $this->getNodeExecutable();
        $env = $this->getEnvironmentVariables();

        $process = new Process(
            [$nodeExecutable, $scriptPath, $ruc, $usuario, $clave],
            base_path(),
            $env,
            null,
            120
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput() ?: $process->getOutput());
            Log::warning("Fallo en ejecución de sunafil_buzon.cjs para RUC {$ruc}: {$errorOutput}");
            throw new \RuntimeException($errorOutput ?: 'Error desconocido en el scraper de SUNAFIL.');
        }

        $output = trim($process->getOutput());

        $jsonStart = strpos($output, '[');
        $jsonEnd = strrpos($output, ']');

        if ($jsonStart === false || $jsonEnd === false) {
            Log::info("No se encontró arreglo JSON en la salida de SUNAFIL para RUC {$ruc}: {$output}");

            return [];
        }

        $jsonStr = substr($output, $jsonStart, ($jsonEnd - $jsonStart) + 1);
        $items = json_decode($jsonStr, true);

        return is_array($items) ? $items : [];
    }

    /**
     * Evalúa si un asunto corresponde a cabeceras de tabla o contenido basura.
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

        return false;
    }

    /**
     * Clasifica la prioridad del mensaje basándose en el diccionario de términos laborales SUNAFIL.
     */
    public function determinarPrioridad(string $asunto, ?string $contenido = null): string
    {
        $textoEvaluacion = $asunto.' '.($contenido ? strip_tags($contenido) : '');

        foreach ($this->altaKeywords as $kw) {
            if (strcasecmp($kw, 'SST') === 0) {
                if (preg_match('/\bSST\b/i', $textoEvaluacion)) {
                    return 'Alta';
                }

                continue;
            }

            if (stripos($textoEvaluacion, $kw) !== false) {
                return 'Alta';
            }
        }

        foreach ($this->mediaKeywords as $kw) {
            if (stripos($textoEvaluacion, $kw) !== false) {
                return 'Media';
            }
        }

        return 'Baja';
    }

    /**
     * Sincroniza las alertas de la Casilla Electrónica de SUNAFIL para un cliente individual.
     *
     * @return array{success: bool, nuevas: int, total: int, nuevas_alertas_items: array<int, AlertaBuzon>, message: string}
     */
    public function syncCliente(Cliente $cliente): array
    {
        if (! $cliente->isActivo() || $cliente->trashed()) {
            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'nuevas_alertas_items' => [],
                'message' => "El cliente {$cliente->razon_social} no se encuentra activo o está en la papelera. Sincronización omitida para optimizar recursos.",
            ];
        }

        $cliente->loadMissing('credencial');

        if (! $cliente->credencial || empty($cliente->credencial->usuario_sol) || empty($cliente->credencial->clave_sol)) {
            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'nuevas_alertas_items' => [],
                'message' => "El cliente {$cliente->razon_social} no tiene credenciales SOL configuradas.",
            ];
        }

        $ruc = (string) $cliente->ruc;
        $usuario = (string) $cliente->credencial->usuario_sol;
        $clave = (string) $cliente->credencial->clave_sol;

        try {
            $alertasExtraidas = $this->runScraper($ruc, $usuario, $clave);

            $nuevas = 0;
            $totalValidas = 0;
            $nuevasAlertasItems = [];

            foreach ($alertasExtraidas as $alerta) {
                if (empty($alerta['asunto'])) {
                    continue;
                }

                $asunto = trim((string) $alerta['asunto']);

                if ($this->esSpamOBasura($asunto)) {
                    Log::info("Alerta SUNAFIL descartada por filtro anti-basura para {$cliente->razon_social}: '{$asunto}'");

                    continue;
                }

                $contenidoBruto = ! empty($alerta['contenido']) ? trim((string) $alerta['contenido']) : null;
                $textoPlano = $contenidoBruto ? trim(strip_tags($contenidoBruto)) : '';

                $tienePdf = ! empty($alerta['tiene_pdf']) || ! empty($alerta['ruta_pdf']);
                $rutaPdf = ! empty($alerta['ruta_pdf']) ? (string) $alerta['ruta_pdf'] : null;
                $nombreArchivoPdf = ! empty($alerta['nombre_archivo_pdf']) ? (string) $alerta['nombre_archivo_pdf'] : null;
                $numeroNotificacion = ! empty($alerta['numero_notificacion']) ? trim((string) $alerta['numero_notificacion']) : null;

                if (! $numeroNotificacion && preg_match('/(?:N[°o]\.?|Número|Numero|Valor|Resolución|Resolucion)\s*([0-9]{3,4}-[0-9]{3,4}-[0-9]{5,10}|[0-9]{10,16})/i', $asunto.' '.$textoPlano, $m)) {
                    $numeroNotificacion = trim($m[1]);
                }

                if (empty($textoPlano) || mb_strlen($textoPlano) <= 25 || preg_match('/^(ver pdf|descargar|pdf)$/i', $textoPlano)) {
                    $contenido = '<div class="p-3 bg-rose-50/80 border border-rose-200 rounded-lg text-rose-950 text-xs leading-relaxed"><strong class="block mb-1 font-bold text-rose-900">Comunicación Electrónica Oficial - SUNAFIL:</strong><p>Esta notificación corresponde a un acto laboral formal depositado en su Casilla Electrónica de SUNAFIL. Puede consultar y descargar el documento PDF oficial directamente desde los botones de acción del CRM.</p></div>';
                    $tienePdf = true;
                } else {
                    $contenido = $contenidoBruto;
                }

                if (preg_match('/(resoluci[oó]n|requerimiento|infracci[oó]n|carta|comparecencia|multa)/i', $asunto)) {
                    $tienePdf = true;
                }

                $estado = in_array($alerta['estado'] ?? '', ['Pendiente', 'Leído']) ? $alerta['estado'] : 'Pendiente';
                $prioridad = $this->determinarPrioridad($asunto, $contenido);

                $fecha = ! empty($alerta['fecha_recepcion'])
                    ? Carbon::parse($alerta['fecha_recepcion'])
                    : (! empty($alerta['fecha']) ? Carbon::parse($alerta['fecha']) : now());

                // Persistir en alertas_buzon con plataforma 'SUNAFIL'
                $registro = AlertaBuzon::updateOrCreate(
                    [
                        'cliente_id' => $cliente->id,
                        'plataforma' => 'SUNAFIL',
                        'asunto' => $asunto,
                        'fecha_recepcion' => $fecha,
                    ],
                    [
                        'numero_notificacion' => $numeroNotificacion,
                        'contenido' => $contenido,
                        'estado' => $estado,
                        'prioridad' => $prioridad,
                        'tiene_pdf' => $tienePdf,
                        'ruta_pdf' => $rutaPdf,
                        'nombre_archivo_pdf' => $nombreArchivoPdf,
                    ]
                );

                $totalValidas++;

                $fueCreadoReciente = $registro->wasRecentlyCreated;
                $noNotificadoAun = ! $registro->yaFueNotificadaWhatsApp() && $registro->estado === 'Pendiente';

                if ($fueCreadoReciente) {
                    $nuevas++;
                }

                if ($fueCreadoReciente || $noNotificadoAun) {
                    $nuevasAlertasItems[] = $registro;
                }

                // Sincronizar en tabla notificaciones legacy si existe
                try {
                    $esImportante = ($prioridad === 'Alta');
                    $cliente->notificaciones()->updateOrCreate(
                        [
                            'fecha' => $fecha->toDateString(),
                            'asunto' => $asunto,
                        ],
                        [
                            'emisor' => 'SUNAFIL',
                            'leido' => ($estado === 'Leído'),
                            'es_importante' => $esImportante,
                        ]
                    );
                } catch (Throwable) {
                    // Ignorar errores legacy
                }
            }

            Log::info("Sincronización SUNAFIL completada para {$cliente->razon_social}: {$nuevas} nuevas, {$totalValidas} válidas.");

            return [
                'success' => true,
                'nuevas' => $nuevas,
                'total' => $totalValidas,
                'nuevas_alertas_items' => $nuevasAlertasItems,
                'message' => "Se sincronizaron {$totalValidas} alertas SUNAFIL ({$nuevas} nuevas) para {$cliente->razon_social}.",
            ];
        } catch (Throwable $e) {
            Log::error("Error sincronizando buzón SUNAFIL para {$cliente->razon_social}: ".$e->getMessage(), [
                'cliente_id' => $cliente->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'nuevas' => 0,
                'total' => 0,
                'nuevas_alertas_items' => [],
                'message' => "Error al sincronizar con SUNAFIL: {$e->getMessage()}",
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

        foreach ($clientes as $cliente) {
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
        }

        return [
            'total_clientes' => $clientes->count(),
            'clientes_procesados' => $procesados,
            'nuevas_alertas' => $totalNuevas,
            'nuevas_alertas_items' => $nuevasAlertasItems,
            'errores' => $errores,
        ];
    }
}
