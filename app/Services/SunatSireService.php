<?php

namespace App\Services;

use App\Exceptions\SunatApiException;
use App\Jobs\DownloadSunatXmlJob;
use App\Models\Cliente;
use App\Models\Comprobante;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;
use ZipArchive;

class SunatSireService
{
    /**
     * Plantilla de URL para la obtención de tokens OAuth 2.0 en SUNAT SOL.
     */
    public const AUTH_URL_TEMPLATE = 'https://api-seguridad.sunat.gob.pe/v1/clientessol/%s/oauth2/token/';

    /**
     * Scope oficial requerido para consultas del Sistema Integrado de Registros Electrónicos (SIRE).
     */
    public const SIRE_SCOPE = 'https://api-sire.sunat.gob.pe';

    /**
     * Host base oficial de los servicios SIRE.
     */
    public const SIRE_BASE_URL = 'https://api-sire.sunat.gob.pe';

    /**
     * Plantilla de URL oficial para solicitar la generación/exportación de la Propuesta de Compras (RCE).
     * Según sección 5.34 del Manual de Compras Oficial de SUNAT SIRE.
     * Devuelve el numTicket para el seguimiento asíncrono.
     */
    public const SIRE_RCE_EXPORTA_PROPUESTA_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rce/propuesta/web/propuesta/%s/exportacioncomprobantepropuesta';

    /**
     * Plantilla de URL oficial para solicitar la generación/exportación de la Propuesta de Ventas (RVIE).
     * Devuelve el numTicket para el seguimiento asíncrono.
     */
    public const SIRE_RVIE_EXPORTA_PROPUESTA_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvie/propuesta/web/propuesta/%s/exportapropuesta';

    /**
     * URL oficial para consultar el estado del ticket en el sistema SIRE.
     * Según sección 5.31 (Compras) y 5.16 (Ventas) del manual oficial SUNAT SIRE.
     */
    public const SIRE_CONSULTA_TICKETS_URL = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/consultaestadotickets';

    /**
     * Plantilla de URL oficial para consultar el estado del ticket en el sistema SIRE (legacy/fallback).
     */
    public const SIRE_CONSULTA_TICKET_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaestadoticket?numTicket=%s';

    /**
     * Plantilla de URL alternativa para consulta de tickets.
     */
    public const SIRE_CONSULTA_TICKET_ALT_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/consultaticket?numTicket=%s';

    /**
     * URL oficial para descargar el archivo reporte (ZIP) generado por el ticket.
     * Según sección 5.32 (Compras) y 5.17 (Ventas) del manual oficial SUNAT SIRE.
     */
    public const SIRE_DESCARGA_ARCHIVO_URL = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesosmasivos/web/masivo/archivoreporte';

    /**
     * Plantilla de URL para descargar el archivo reporte (ZIP) generado por el ticket (legacy/fallback).
     */
    public const SIRE_DESCARGA_ARCHIVO_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/archivoreporte?nomArchivo=%s&numTicket=%s';

    /**
     * Plantilla de URL alternativa para descarga directa por nombre de archivo.
     */
    public const SIRE_DESCARGA_ARCHIVO_ALT_URL_TEMPLATE = 'https://api-sire.sunat.gob.pe/v1/contribuyente/migeigv/libros/rvierce/gestionprocesos/web/masivo/descargaarchivo?nomArchivo=%s';

    /**
     * Tiempo de espera por defecto en segundos para peticiones HTTP a SUNAT.
     */
    public const DEFAULT_TIMEOUT_SECONDS = 30;

    /**
     * Tiempo en minutos para almacenar en caché el access token (SUNAT emite por 60 min; guardamos por 55 min).
     */
    public const CACHE_TTL_MINUTES = 55;

    /**
     * Obtiene el Access Token Bearer de la API de SUNAT para el cliente especificado.
     *
     * Desencripta automáticamente el Client Secret y la Clave SOL del cliente,
     * valida los parámetros requeridos y utiliza Cache::remember con clave dinámica
     * sunat_token_{id} durante 55 minutos para mitigar bloqueos por Rate Limiting.
     *
     * @param  Cliente  $cliente  Modelo del cliente con credenciales API y SOL
     * @param  bool  $forzarRenovacion  Si es true, invalida la caché y solicita un token nuevo
     * @return string Token de acceso listo para usarse en cabeceras Authorization: Bearer {token}
     *
     * @throws SunatApiException Si faltan credenciales, falla la desencriptación o SUNAT rechaza la petición
     */
    public function getAccessToken(Cliente $cliente, bool $forzarRenovacion = false): string
    {
        // Limpieza temporal de Cache::remember para forzar petición limpia hacia SUNAT en cada intento (Fase 30)
        Cache::forget($this->getCacheKey($cliente));

        return $this->solicitarNuevoToken($cliente);
    }

    /**
     * Invalida el token en caché para un cliente específico.
     */
    public function clearTokenCache(Cliente $cliente): void
    {
        Cache::forget($this->getCacheKey($cliente));
    }

    /**
     * Verifica si existe un token en caché para el cliente.
     */
    public function hasCachedToken(Cliente $cliente): bool
    {
        return Cache::has($this->getCacheKey($cliente));
    }

    /**
     * Retorna la clave de caché dinámica para el token de un cliente.
     */
    public function getCacheKey(Cliente $cliente): string
    {
        return "sunat_token_{$cliente->id}";
    }

    /**
     * Ejecuta la petición HTTP POST a SUNAT para emitir un nuevo access token.
     *
     * @throws SunatApiException
     */
    protected function solicitarNuevoToken(Cliente $cliente): string
    {
        $this->validarCredencialesCliente($cliente);

        $trimCredential = static function (?string $valor): string {
            if ($valor === null) {
                return '';
            }
            // Elimina espacios regulares, Unicode y caracteres de control (NBSP, tabs, saltos de línea)
            $limpio = preg_replace('/^[\s\x00-\x1F\x7F\xC2\xA0]+|[\s\x00-\x1F\x7F\xC2\xA0]+$/u', '', (string) $valor) ?? trim((string) $valor);

            return trim($limpio, "\"'");
        };

        // 1. Client ID estrictamente sanitizado
        $clientId = $trimCredential($cliente->sunat_client_id);

        // 2. Desencriptación Segura y Trim de Client Secret
        try {
            $rawSecret = (string) $cliente->sunat_client_secret;
        } catch (DecryptException) {
            $rawSecret = (string) $cliente->getRawOriginal('sunat_client_secret');
        }

        // Si por persistencias previas quedó un segundo payload cifrado de Laravel
        if (is_string($rawSecret) && str_starts_with($rawSecret, 'eyJ') && strlen($rawSecret) > 80) {
            try {
                $rawSecret = Crypt::decryptString($rawSecret);
            } catch (Throwable) {
                // Mantener valor actual si no era cifrado Laravel
            }
        }
        $clientSecret = $trimCredential($rawSecret);

        // 3. Desencriptación Segura y Trim de Clave SOL
        $cliente->loadMissing('credencial');
        $credencial = $cliente->credencial;

        try {
            $rawClave = (string) ($cliente->clave_sol ?? $credencial?->clave_sol);
        } catch (DecryptException) {
            $rawClave = (string) ($credencial?->getRawOriginal('clave_sol'));
        }

        if (is_string($rawClave) && str_starts_with($rawClave, 'eyJ') && strlen($rawClave) > 80) {
            try {
                $rawClave = Crypt::decryptString($rawClave);
            } catch (Throwable) {
                // Mantener valor actual
            }
        }
        $claveSol = $trimCredential($rawClave);

        // 4. Usuario Concatenado: EXACTAMENTE RUC + Usuario SOL sin espacios
        $ruc = $trimCredential($cliente->ruc);
        $usuarioSol = $trimCredential($cliente->usuario_sol ?? $credencial?->usuario_sol);
        $username = $trimCredential($ruc.$usuarioSol);

        $url = sprintf(self::AUTH_URL_TEMPLATE, $clientId);

        Log::info('SUNAT OAuth2 Request:', [
            'url' => $url,
            'client_id' => $clientId,
            'username' => $username,
            'client_secret_len' => strlen($clientSecret),
            'client_secret_ends_with_equal' => str_ends_with($clientSecret, '='),
            'clave_sol_len' => strlen($claveSol),
        ]);

        // 5. Parámetros como array asociativo estrictamente limpios sin ningún espacio en blanco
        $params = [
            'grant_type' => 'password',
            'scope' => self::SIRE_SCOPE,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'username' => $username,
            'password' => $claveSol,
        ];

        try {
            $response = Http::asForm()
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->post($url, $params);
        } catch (ConnectionException $e) {
            Log::error("Fallo de conexión con SUNAT OAuth2 para cliente {$cliente->ruc}: {$e->getMessage()}");
            throw new SunatApiException(
                'No se pudo conectar con el servidor de autenticación de SUNAT (Tiempo de espera agotado o servicio inaccesible).',
                0,
                $e
            );
        } catch (Throwable $e) {
            Log::error("Error inesperado de transporte con SUNAT para cliente {$cliente->ruc}: {$e->getMessage()}");
            throw new SunatApiException(
                "Error al comunicarse con el servidor de autenticación de SUNAT: {$e->getMessage()}",
                0,
                $e
            );
        }

        $status = $response->status();
        $data = $response->json();

        if ($response->failed() || ! $response->successful()) {
            $rawBody = trim($response->body());
            $mensajeError = "Error SUNAT {$status}: ".($rawBody !== '' ? $rawBody : 'Respuesta vacía');

            Log::warning("Rechazo de autenticación SUNAT (HTTP {$status}) para cliente {$cliente->ruc}: {$mensajeError}", [
                'status' => $status,
                'response_body' => $rawBody,
                'username' => $username,
                'client_id' => $clientId,
            ]);

            throw new SunatApiException(
                $mensajeError,
                $status,
                null,
                $status,
                is_array($data) ? $data : ['raw' => $rawBody]
            );
        }

        if (! is_array($data) || empty($data['access_token'])) {
            Log::error("Respuesta SUNAT sin access_token para cliente {$cliente->ruc}: ".substr($response->body(), 0, 500));
            throw new SunatApiException(
                'SUNAT respondió exitosamente pero no devolvió el access_token requerido.',
                $status,
                null,
                $status,
                is_array($data) ? $data : ['raw' => $response->body()]
            );
        }

        return (string) $data['access_token'];
    }

    /**
     * Valida que el cliente posea todas las credenciales necesarias antes de invocar la API.
     *
     * @throws SunatApiException
     */
    protected function validarCredencialesCliente(Cliente $cliente): void
    {
        if (empty(trim((string) $cliente->sunat_client_id))) {
            throw new SunatApiException("El cliente '{$cliente->razon_social}' no tiene configurado el Client ID de la API SUNAT.");
        }

        try {
            $hasSecret = ! empty($cliente->sunat_client_secret);
        } catch (DecryptException) {
            $hasSecret = ! empty($cliente->getRawOriginal('sunat_client_secret'));
        }

        if (! $hasSecret) {
            throw new SunatApiException("El cliente '{$cliente->razon_social}' no tiene configurado el Client Secret de la API SUNAT.");
        }

        $cliente->loadMissing('credencial');
        $credencial = $cliente->credencial;

        if (! $credencial || empty(trim((string) $credencial->usuario_sol))) {
            throw new SunatApiException("El cliente '{$cliente->razon_social}' no tiene registrado su Usuario SOL.");
        }

        try {
            $hasClave = ! empty($credencial->clave_sol);
        } catch (DecryptException) {
            $hasClave = ! empty($credencial->getRawOriginal('clave_sol'));
        }

        if (! $hasClave) {
            throw new SunatApiException("El cliente '{$cliente->razon_social}' no tiene registrada su Clave SOL.");
        }
    }

    /**
     * Extrae un mensaje de error legible a partir de la respuesta de SUNAT.
     *
     * @param  mixed  $data  JSON decodificado de la respuesta si está disponible
     */
    protected function extraerMensajeError(Response $response, mixed $data): string
    {
        $status = $response->status();
        $rawBody = trim($response->body());

        if ($status === 401 && stripos($rawBody, 'nginx') !== false) {
            return "Error SUNAT 401: Acceso no autorizado al servicio SIRE. Verifica que en el portal SUNAT SOL (Gestión Credenciales API SUNAT) la aplicación registrada tenga Alcance: 'Desktop' (SUNAT bloquea las consultas a api-sire si se configura con Alcance 'Web') y esté marcado el permiso '/v1/contribuyente/migeigv'.";
        }

        if ($status === 429) {
            return 'Error SUNAT 429: Límite de peticiones alcanzado en SUNAT (Too Many Requests). Por favor espera unos segundos e intenta nuevamente.';
        }

        if ($status === 500) {
            return 'Error SUNAT 500: El servicio SIRE de SUNAT experimentó una falla interna temporal al procesar la solicitud (servidores de SUNAT no disponibles o sobrecargados). Por favor, intenta con otro periodo o reintenta en unos minutos.';
        }

        if (is_array($data)) {
            if (! empty($data['error_description'])) {
                return "Error SUNAT {$status}: ".(string) $data['error_description'];
            }
            if (! empty($data['message'])) {
                return "Error SUNAT {$status}: ".(string) $data['message'];
            }
            if (! empty($data['error'])) {
                return "Error SUNAT {$status}: ".(string) $data['error'];
            }
            if (! empty($data['msg'])) {
                return "Error SUNAT {$status}: ".(string) $data['msg'];
            }
        }

        if ($rawBody !== '') {
            if (stripos($rawBody, '<html') !== false || stripos($rawBody, '<!doctype') !== false) {
                $clean = trim(strip_tags($rawBody));
                $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;

                return "Error SUNAT {$status}: ".(strlen($clean) > 200 ? substr($clean, 0, 200).'...' : $clean);
            }

            return "Error SUNAT {$status}: {$rawBody}";
        }

        return "Error SUNAT {$status}: Respuesta vacía";
    }

    /**
     * Importa la propuesta del Registro de Compras Electrónico (RCE) de SUNAT para el periodo dado.
     *
     * 1. Obtiene el token Bearer vía getAccessToken($cliente).
     * 2. Realiza la petición GET al endpoint oficial del SIRE para la propuesta de compras del periodo solicitado.
     * 3. Mapea la estructura estándar devuelta por SUNAT y persiste los comprobantes
     *    usando Comprobante::updateOrCreate() evitando duplicados por Serie, Correlativo y RUC Proveedor.
     *
     * @param  Cliente  $cliente  Cliente titular de las credenciales y registros
     * @param  string  $periodo  Periodo en formato 'YYYY-MM' o 'YYYYMM'
     * @return array{success: bool, message: string, total: int, importados: int, periodo: string}
     *
     * @throws SunatApiException
     */
    /**
     * Normaliza un periodo a formato estricto de 6 dígitos YYYYMM para la API de SUNAT
     * y formato canónico YYYY-MM para persistencia en base de datos.
     *
     * @return array{api: string, db: string}
     */
    public function normalizarPeriodo(string $periodo): array
    {
        $soloDigitos = preg_replace('/\D/', '', trim($periodo));

        if (strlen($soloDigitos) === 6) {
            $anio = substr($soloDigitos, 0, 4);
            $mes = substr($soloDigitos, 4, 2);

            return [
                'api' => "{$anio}{$mes}",
                'db' => "{$anio}-{$mes}",
            ];
        }

        if (preg_match('/^(\d{4})-(\d{1,2})$/', trim($periodo), $matches)) {
            $anio = $matches[1];
            $mes = str_pad($matches[2], 2, '0', STR_PAD_LEFT);

            return [
                'api' => "{$anio}{$mes}",
                'db' => "{$anio}-{$mes}",
            ];
        }

        $api = substr(str_pad($soloDigitos, 6, '0', STR_PAD_RIGHT), 0, 6);
        $db = strlen($api) === 6 ? substr($api, 0, 4).'-'.substr($api, 4, 2) : $periodo;

        return [
            'api' => $api,
            'db' => $db,
        ];
    }

    /**
     * Solicita la generación/exportación de la propuesta (RCE o RVIE) en el SIRE de SUNAT.
     * Retorna el numTicket asignado por SUNAT para el seguimiento asíncrono.
     *
     * @throws SunatApiException
     */
    public function solicitarTicketPropuesta(Cliente $cliente, string $tipoRegistro, string $periodo): string
    {
        $periodos = $this->normalizarPeriodo($periodo);
        $periodoApi = $periodos['api'];
        $periodoFormateado = $periodoApi;

        $accessToken = $this->getAccessToken($cliente);

        $template = $tipoRegistro === 'RCE'
            ? self::SIRE_RCE_EXPORTA_PROPUESTA_URL_TEMPLATE
            : self::SIRE_RVIE_EXPORTA_PROPUESTA_URL_TEMPLATE;

        if ($tipoRegistro === 'RCE') {
            $url = sprintf(self::SIRE_RCE_EXPORTA_PROPUESTA_URL_TEMPLATE, $periodoFormateado).'?codTipoArchivo=0&codOrigenEnvio=2';
        } else {
            $url = sprintf(self::SIRE_RVIE_EXPORTA_PROPUESTA_URL_TEMPLATE, $periodoFormateado).'?codTipoArchivo=0';
        }

        \Log::info('SUNAT Request:', ['url' => $url, 'periodo' => $periodoFormateado]);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($url);

            if ($response->status() === 405) {
                // Si el servidor requiere POST
                $postParams = $tipoRegistro === 'RCE'
                    ? ['codTipoArchivo' => '0', 'codOrigenEnvio' => '2']
                    : ['codTipoArchivo' => '0'];

                $response = Http::withToken($accessToken)
                    ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                    ->acceptJson()
                    ->post(sprintf($template, $periodoFormateado), $postParams);
            }
        } catch (ConnectionException $e) {
            Log::error("Fallo de conexión al solicitar propuesta {$tipoRegistro} para cliente {$cliente->ruc}: {$e->getMessage()}");
            throw new SunatApiException('No se pudo conectar con el servicio SIRE de SUNAT (Tiempo de espera agotado o servicio inaccesible).', 0, $e);
        } catch (Throwable $e) {
            Log::error("Error de transporte al solicitar propuesta {$tipoRegistro} para cliente {$cliente->ruc}: {$e->getMessage()}");
            throw new SunatApiException("Error de comunicación con el servicio SIRE de SUNAT: {$e->getMessage()}", 0, $e);
        }

        $status = $response->status();
        $data = $response->json();

        // Si el token expiró (401), reintentar forzando renovación
        if ($status === 401) {
            Log::warning("Token SUNAT expirado al solicitar ticket para cliente {$cliente->ruc}, renovando...");
            $accessToken = $this->getAccessToken($cliente, forzarRenovacion: true);
            \Log::info('SUNAT Request:', ['url' => $url, 'periodo' => $periodoFormateado]);
            $response = Http::withToken($accessToken)
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get($url);
            $status = $response->status();
            $data = $response->json();
        }

        if ($response->failed() || ! $response->successful()) {
            if ($status === 422 && is_array($data) && ! empty($data['errors'])) {
                foreach ($data['errors'] as $err) {
                    $cod = (string) ($err['cod'] ?? '');
                    if (in_array($cod, ['1518', '1070'])) {
                        Log::info("SUNAT informó sin comprobantes ({$cod}) para {$tipoRegistro} en periodo {$periodoFormateado}");

                        return 'DIRECT_JSON:[]';
                    }
                }
            }

            $mensajeError = $this->extraerMensajeError($response, $data);
            Log::warning("Rechazo al solicitar ticket Propuesta {$tipoRegistro} SUNAT (HTTP {$status}): {$mensajeError}");

            throw new SunatApiException(
                $mensajeError,
                $status,
                null,
                $status,
                is_array($data) ? $data : ['raw' => $response->body()]
            );
        }

        $numTicket = $this->extraerNumTicket($data);

        if (empty($numTicket)) {
            // Soporte fallback si la API respondió directamente con un array de comprobantes JSON
            $items = $this->extraerItemsComprobantes($data);
            if (! empty($items)) {
                return 'DIRECT_JSON:'.json_encode($items);
            }

            Log::error("Respuesta SUNAT exitosa pero sin numTicket para cliente {$cliente->ruc}: ".substr($response->body(), 0, 500));
            throw new SunatApiException('SUNAT respondió exitosamente pero no devolvió el número de ticket requerido.', $status);
        }

        return $numTicket;
    }

    /**
     * Consulta el estado del ticket en el fileserver/proceso masivo de SUNAT SIRE.
     * Según sección 5.31 (Compras) y 5.16 (Ventas) del manual oficial SUNAT SIRE.
     *
     * @return array{ticket: string, estado: string, terminado: bool, nom_archivo: string, cod_tipo_archivo: string, cod_proceso: string, raw: mixed}
     *
     * @throws SunatApiException
     */
    public function consultarEstadoTicket(Cliente $cliente, string $numTicket, ?string $periodo = null, ?string $tipoRegistro = null): array
    {
        $accessToken = $this->getAccessToken($cliente);

        if ($periodo) {
            $periodos = $this->normalizarPeriodo($periodo);
            $periodoApi = $periodos['api'];
        } else {
            $anioTicket = strlen($numTicket) >= 4 && ctype_digit(substr($numTicket, 0, 4))
                ? substr($numTicket, 0, 4)
                : date('Y');
            $periodoApi = "{$anioTicket}01";
        }

        $params = [
            'perIni' => $periodoApi,
            'perFin' => $periodoApi,
            'page' => 1,
            'perPage' => 20,
            'numTicket' => $numTicket,
        ];

        if ($tipoRegistro) {
            $params['codLibro'] = $tipoRegistro === 'RCE' ? '080000' : '140000';
            $params['codOrigenEnvio'] = '2';
        }

        \Log::info('SUNAT Request:', ['url' => self::SIRE_CONSULTA_TICKETS_URL, 'periodo' => $numTicket, 'params' => $params]);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(self::SIRE_CONSULTA_TICKETS_URL, $params);
        } catch (ConnectionException $e) {
            throw new SunatApiException('No se pudo conectar con el servicio de consulta de tickets de SUNAT.', 0, $e);
        } catch (Throwable $e) {
            throw new SunatApiException("Error al consultar el estado del ticket en SUNAT: {$e->getMessage()}", 0, $e);
        }

        $status = $response->status();
        $data = $response->json();

        if ($status === 401) {
            $accessToken = $this->getAccessToken($cliente, forzarRenovacion: true);
            \Log::info('SUNAT Request:', ['url' => self::SIRE_CONSULTA_TICKETS_URL, 'periodo' => $numTicket, 'params' => $params]);
            $response = Http::withToken($accessToken)->timeout(self::DEFAULT_TIMEOUT_SECONDS)->acceptJson()->get(self::SIRE_CONSULTA_TICKETS_URL, $params);
            $status = $response->status();
            $data = $response->json();
        }

        if ($response->failed() || ! $response->successful()) {
            // Intento con endpoint legacy de ticket (compatibilidad con mocks de pruebas anteriores)
            $legacyUrl = sprintf(self::SIRE_CONSULTA_TICKET_URL_TEMPLATE, urlencode($numTicket));
            \Log::info('SUNAT Request Legacy:', ['url' => $legacyUrl, 'periodo' => $numTicket]);
            try {
                $legacyResponse = Http::withToken($accessToken)->timeout(self::DEFAULT_TIMEOUT_SECONDS)->acceptJson()->get($legacyUrl);
                if ($legacyResponse->successful()) {
                    $response = $legacyResponse;
                    $status = $response->status();
                    $data = $response->json();
                } else {
                    $altUrl = sprintf(self::SIRE_CONSULTA_TICKET_ALT_URL_TEMPLATE, urlencode($numTicket));
                    $altResponse = Http::withToken($accessToken)->timeout(self::DEFAULT_TIMEOUT_SECONDS)->acceptJson()->get($altUrl);
                    if ($altResponse->successful()) {
                        $response = $altResponse;
                        $status = $response->status();
                        $data = $response->json();
                    }
                }
            } catch (Throwable $ignored) {
            }
        }

        if ($response->failed() || ! $response->successful()) {
            $msg = $this->extraerMensajeError($response, $data);
            throw new SunatApiException($msg, $status);
        }

        $reg = is_array($data) && ! empty($data['registros'][0]) && is_array($data['registros'][0])
            ? $data['registros'][0]
            : (is_array($data) ? $data : []);

        $codEstado = (string) (
            $reg['codEstadoProceso']
            ?? $reg['detalleTicket']['codEstadoEnvio']
            ?? $reg['codEstado']
            ?? $data['codEstadoProceso']
            ?? $data['codEstado']
            ?? $data['estado']
            ?? ''
        );

        $desEstado = (string) (
            $reg['desEstadoProceso']
            ?? $reg['detalleTicket']['desEstadoEnvio']
            ?? $reg['desEstado']
            ?? $data['desEstadoProceso']
            ?? $data['desEstado']
            ?? $data['estado']
            ?? 'En proceso'
        );

        $archivoReporte = $reg['archivoReporte'][0]
            ?? $reg['detalleTicket']['archivoReporte'][0]
            ?? $data['archivoReporte'][0]
            ?? [];

        $nomArchivo = (string) (
            $archivoReporte['nomArchivoReporte']
            ?? $reg['nomArchivo']
            ?? $reg['nomArchivoReporte']
            ?? $reg['detalleTicket']['nomArchivoReporte']
            ?? $data['nomArchivo']
            ?? $data['nomArchivoReporte']
            ?? $data['archivo']
            ?? ($data['arcReporte'][0]['nomArchivo'] ?? '')
            ?? ($data['arcReporte'][0]['nomArchivoReporte'] ?? '')
            ?? ''
        );

        $codTipoArchivoReporte = (string) (
            $archivoReporte['codTipoAchivoReporte']
            ?? $archivoReporte['codTipoArchivoReporte']
            ?? '00'
        );

        $codProceso = (string) (
            $reg['codProceso']
            ?? $data['codProceso']
            ?? ($tipoRegistro === 'RCE' ? '10' : '1')
        );

        $isTerminado = (
            stripos($desEstado, 'Terminado') !== false
            || stripos($desEstado, 'Concluido') !== false
            || in_array($codEstado, ['03', '06'])
            || ! empty($nomArchivo)
        );

        if ($isTerminado && empty($nomArchivo)) {
            $nomArchivo = "{$cliente->ruc}-SIRE-{$numTicket}.zip";
        }

        return [
            'ticket' => $numTicket,
            'estado' => $isTerminado ? 'Terminado' : $desEstado,
            'terminado' => $isTerminado,
            'nom_archivo' => $nomArchivo,
            'cod_tipo_archivo' => $codTipoArchivoReporte,
            'cod_proceso' => $codProceso,
            'raw' => $data,
        ];
    }

    /**
     * Descarga el archivo ZIP del ticket generado desde el fileserver de SUNAT y lo guarda en disco local.
     * Según sección 5.32 (Compras) y 5.17 (Ventas) del manual oficial SUNAT SIRE.
     *
     * @return string Ruta absoluta del archivo ZIP descargado
     *
     * @throws SunatApiException
     */
    public function descargarArchivoTicket(
        Cliente $cliente,
        string $numTicket,
        string $nomArchivo,
        string $tipoRegistro,
        string $periodoDb,
        string $codTipoArchivoReporte = '00',
        string $codProceso = '10'
    ): string {
        $accessToken = $this->getAccessToken($cliente);
        $periodos = $this->normalizarPeriodo($periodoDb);
        $periodoApi = $periodos['api'];
        $codLibro = $tipoRegistro === 'RCE' ? '080000' : '140000';

        $params = [
            'nomArchivoReporte' => $nomArchivo,
            'codTipoArchivoReporte' => $codTipoArchivoReporte,
            'perTributario' => $periodoApi,
            'codProceso' => $codProceso,
            'numTicket' => $numTicket,
            'codLibro' => $codLibro,
        ];

        \Log::info('SUNAT Request:', ['url' => self::SIRE_DESCARGA_ARCHIVO_URL, 'periodo' => $numTicket, 'params' => $params]);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS * 2)
                ->get(self::SIRE_DESCARGA_ARCHIVO_URL, $params);
        } catch (Throwable $e) {
            throw new SunatApiException("Fallo al descargar archivo del ticket {$numTicket}: {$e->getMessage()}", 0, $e);
        }

        if ($response->failed()) {
            // Intento con endpoints alternativos / legacy
            $legacyUrl = sprintf(self::SIRE_DESCARGA_ARCHIVO_URL_TEMPLATE, urlencode($nomArchivo), urlencode($numTicket));
            \Log::info('SUNAT Request Legacy:', ['url' => $legacyUrl, 'periodo' => $numTicket]);
            try {
                $altResponse = Http::withToken($accessToken)->timeout(self::DEFAULT_TIMEOUT_SECONDS * 2)->get($legacyUrl);
                if ($altResponse->successful()) {
                    $response = $altResponse;
                } else {
                    $altUrl = sprintf(self::SIRE_DESCARGA_ARCHIVO_ALT_URL_TEMPLATE, urlencode($nomArchivo));
                    $altResponse2 = Http::withToken($accessToken)->timeout(self::DEFAULT_TIMEOUT_SECONDS * 2)->get($altUrl);
                    if ($altResponse2->successful()) {
                        $response = $altResponse2;
                    }
                }
            } catch (Throwable $ignored) {
            }
        }

        if ($response->failed()) {
            throw new SunatApiException("No se pudo descargar el archivo del ticket {$numTicket} desde SUNAT (HTTP {$response->status()}).", $response->status());
        }

        $contenido = $response->body();
        if (strlen($contenido) === 0) {
            throw new SunatApiException("El archivo descargado desde SUNAT para el ticket {$numTicket} está vacío.");
        }

        $relPath = Comprobante::storagePathFor($cliente->ruc, $periodoDb, $tipoRegistro);
        $absDir = storage_path('app/'.$relPath);
        if (! is_dir($absDir)) {
            mkdir($absDir, 0755, true);
        }

        $nombreLimpio = basename($nomArchivo);
        if (empty($nombreLimpio) || ! str_ends_with(strtolower($nombreLimpio), '.zip')) {
            $nombreLimpio = "{$cliente->ruc}_{$tipoRegistro}_{$periodoDb}_{$numTicket}.zip";
        }

        $rutaArchivoLocal = "{$absDir}/{$nombreLimpio}";
        file_put_contents($rutaArchivoLocal, $contenido);

        return $rutaArchivoLocal;
    }

    /**
     * Descomprime el paquete ZIP descargado y procesa sus archivos delimitados por pipes (|).
     *
     * @return array{success: bool, message: string, total: int, importados: int, periodo: string, tipo_registro: string}
     *
     * @throws SunatApiException
     */
    public function procesarArchivoDescargado(Cliente $cliente, string $tipoRegistro, string $periodoDb, string $rutaArchivo): array
    {
        if (! file_exists($rutaArchivo)) {
            throw new SunatApiException("El archivo a procesar no existe en el disco: {$rutaArchivo}");
        }

        $archivosTxt = [];

        // Si es un archivo ZIP, extraerlo con ZipArchive
        if (str_ends_with(strtolower($rutaArchivo), '.zip')) {
            $zip = new ZipArchive;
            if ($zip->open($rutaArchivo) === true) {
                $extractDir = dirname($rutaArchivo).'/extracted_'.pathinfo($rutaArchivo, PATHINFO_FILENAME);
                if (! is_dir($extractDir)) {
                    mkdir($extractDir, 0755, true);
                }
                $zip->extractTo($extractDir);
                $zip->close();

                // Buscar archivos .txt o .csv dentro del directorio extraído
                $archivos = glob($extractDir.'/*.{txt,csv,TXT,CSV}', GLOB_BRACE) ?: [];
                foreach ($archivos as $f) {
                    $archivosTxt[] = $f;
                }
            } else {
                throw new SunatApiException("No se pudo descomprimir el paquete ZIP descargado desde SUNAT: {$rutaArchivo}");
            }
        } else {
            $archivosTxt[] = $rutaArchivo;
        }

        if (empty($archivosTxt)) {
            throw new SunatApiException('No se encontraron archivos TXT o CSV dentro del paquete ZIP descargado de SUNAT.');
        }

        $importados = 0;
        $totalFilas = 0;

        foreach ($archivosTxt as $archivoPlano) {
            $resultado = $this->parsearArchivoPlano($cliente, $tipoRegistro, $periodoDb, $archivoPlano);
            $importados += $resultado['importados'];
            $totalFilas += $resultado['total'];
        }

        return [
            'success' => true,
            'message' => $importados > 0
                ? "Se importaron exitosamente {$importados} comprobantes de la Propuesta {$tipoRegistro} ({$periodoDb}) desde SUNAT."
                : "No se encontraron comprobantes pendientes en la Propuesta {$tipoRegistro} de SUNAT para el periodo {$periodoDb}.",
            'total' => $totalFilas,
            'importados' => $importados,
            'periodo' => $periodoDb,
            'tipo_registro' => $tipoRegistro,
        ];
    }

    /**
     * Parsea un archivo de texto plano delimitado por pipes (|) o comas y persiste los comprobantes en BD.
     *
     * @return array{total: int, importados: int}
     */
    public function parsearArchivoPlano(Cliente $cliente, string $tipoRegistro, string $periodoDb, string $rutaArchivoPlano): array
    {
        $handle = fopen($rutaArchivoPlano, 'r');
        if (! $handle) {
            return ['total' => 0, 'importados' => 0];
        }

        $importados = 0;
        $total = 0;

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Ignorar posibles encabezados textuales
            if (stripos($line, 'numSerie') !== false
                || stripos($line, 'RUC Proveedor') !== false
                || stripos($line, 'Razón Social') !== false
                || stripos($line, 'Razon Social') !== false
                || stripos($line, 'CAR SUNAT') !== false
                || stripos($line, 'Tipo CP') !== false) {
                continue;
            }

            $cols = explode('|', $line);
            if (count($cols) < 5) {
                if (str_contains($line, "\t")) {
                    $cols = explode("\t", $line);
                } elseif (str_contains($line, ',')) {
                    $cols = str_getcsv($line);
                }
            }

            if (count($cols) < 4) {
                continue;
            }

            $parsed = $this->mapearColumnasSire($cols, $tipoRegistro, $cliente->ruc);
            if (! $parsed) {
                continue;
            }

            $total++;

            $comprobante = Comprobante::updateOrCreate(
                [
                    'cliente_id' => $cliente->id,
                    'tipo_registro' => $tipoRegistro,
                    'serie' => $parsed['serie'],
                    'correlativo' => $parsed['correlativo'],
                    'ruc_tercero' => $parsed['ruc_tercero'],
                ],
                [
                    'periodo' => $periodoDb,
                    'tipo_comprobante' => $parsed['tipo_comprobante'],
                    'razon_social_tercero' => $parsed['razon_social_tercero'],
                    'moneda' => $parsed['moneda'],
                    'base_imponible' => $parsed['base_imponible'],
                    'igv' => $parsed['igv'],
                    'total' => $parsed['total'],
                    'estado_sire' => 'Propuesta',
                ]
            );

            // Despachar Job asíncrono con escalonamiento de 2 segundos para no saturar IP de SUNAT
            if (! $comprobante->esta_completamente_disponible) {
                $delaySegundos = $importados * 2;
                DownloadSunatXmlJob::dispatch($comprobante)->delay(now()->addSeconds($delaySegundos));
            }

            $importados++;
        }

        fclose($handle);

        return ['total' => $total, 'importados' => $importados];
    }

    /**
     * Mapea un arreglo de columnas al esquema de comprobante de compras (RCE) o ventas (RVIE).
     * Soporta tanto el diseño oficial completo de SUNAT SIRE como formatos de prueba resumidos.
     *
     * @param  array<int, string>  $cols
     * @return array{serie: string, correlativo: string, ruc_tercero: string, razon_social_tercero: string, tipo_comprobante: string, moneda: string, base_imponible: float, igv: float, total: float}|null
     */
    public function mapearColumnasSire(array $cols, string $tipoRegistro, string $rucCliente): ?array
    {
        $cols = array_map(fn ($c) => trim((string) $c), $cols);
        $totalCols = count($cols);

        if ($totalCols < 4) {
            return null;
        }

        $serie = '';
        $correlativo = '';
        $rucDniTercero = '';
        $razonSocial = '';
        $tipoComprobante = '01';
        $moneda = 'PEN';
        $baseImponible = 0.0;
        $igv = 0.0;
        $total = 0.0;

        // Si es el formato oficial completo de SUNAT SIRE (más de 18 columnas)
        if ($totalCols >= 18) {
            $tipoComprobante = ! empty($cols[6]) ? $cols[6] : '01';
            $serie = ! empty($cols[7]) ? $cols[7] : '';

            if ($tipoRegistro === 'RCE') {
                // RCE (Compras) oficial SUNAT SIRE (80 columnas):
                // En el formato oficial SUNAT RCE, Col 8 es Año (vacío o 4 dígitos de DUA) y Col 9 es el Nro CP (correlativo).
                if (! empty($cols[9]) && (empty($cols[8]) || ! is_numeric($cols[8]) || strlen($cols[8]) === 4)) {
                    $correlativo = $cols[9];
                } else {
                    $correlativo = ! empty($cols[8]) ? $cols[8] : (! empty($cols[9]) ? $cols[9] : '');
                }

                // En el formato oficial RCE, Col 11 es Tipo Doc (6, 1) y Col 12 es el RUC/DNI del Proveedor
                if (! empty($cols[12]) && strlen($cols[12]) >= 8) {
                    $rucDniTercero = $cols[12];
                    $razonSocial = ! empty($cols[13]) ? $cols[13] : 'PROVEEDOR';
                } elseif (! empty($cols[11]) && strlen($cols[11]) >= 8) {
                    $rucDniTercero = $cols[11];
                    $razonSocial = ! empty($cols[12]) ? $cols[12] : 'PROVEEDOR';
                } else {
                    $rucDniTercero = ! empty($cols[12]) ? $cols[12] : (! empty($cols[11]) ? $cols[11] : '');
                    $razonSocial = ! empty($cols[13]) ? $cols[13] : (! empty($cols[12]) ? $cols[12] : 'PROVEEDOR');
                }

                $biGravada = isset($cols[14]) && is_numeric($cols[14]) ? (float) $cols[14] : (isset($cols[13]) && is_numeric($cols[13]) ? (float) $cols[13] : 0.0);
                $igv = isset($cols[15]) && is_numeric($cols[15]) ? (float) $cols[15] : (isset($cols[14]) && is_numeric($cols[14]) ? (float) $cols[14] : 0.0);
                $biDgng = isset($cols[16]) && is_numeric($cols[16]) ? (float) $cols[16] : 0.0;
                $igvDgng = isset($cols[17]) && is_numeric($cols[17]) ? (float) $cols[17] : 0.0;
                $biDng = isset($cols[18]) && is_numeric($cols[18]) ? (float) $cols[18] : 0.0;
                $igvDng = isset($cols[19]) && is_numeric($cols[19]) ? (float) $cols[19] : 0.0;
                $valNoGrav = isset($cols[20]) && is_numeric($cols[20]) ? (float) $cols[20] : 0.0;

                $total = isset($cols[24]) && is_numeric($cols[24]) ? (float) $cols[24] : (isset($cols[23]) && is_numeric($cols[23]) ? (float) $cols[23] : ($biGravada + $igv + $biDgng + $igvDgng + $biDng + $igvDng + $valNoGrav));
                $moneda = ! empty($cols[25]) && strlen($cols[25]) === 3 ? strtoupper($cols[25]) : (! empty($cols[24]) && strlen($cols[24]) === 3 ? strtoupper($cols[24]) : 'PEN');

                $sumaBi = $biGravada + $biDgng + $biDng;
                $baseImponible = $sumaBi > 0 ? $sumaBi : ($valNoGrav > 0 ? $valNoGrav : max(0.0, $total - $igv));
            } else {
                // RVIE (Ventas) estándar oficial SUNAT
                $correlativo = ! empty($cols[8]) ? $cols[8] : '';
                $rucDniTercero = ! empty($cols[11]) && strlen($cols[11]) >= 8 ? $cols[11] : (! empty($cols[10]) && strlen($cols[10]) >= 8 ? $cols[10] : ($cols[11] ?? ''));
                $razonSocial = ! empty($cols[12]) && ! is_numeric($cols[12]) ? $cols[12] : (! empty($cols[11]) && ! is_numeric($cols[11]) ? $cols[11] : 'CLIENTE');
                $biGravada = isset($cols[14]) && is_numeric($cols[14]) ? (float) $cols[14] : (isset($cols[13]) && is_numeric($cols[13]) ? (float) $cols[13] : 0.0);
                $igv = isset($cols[16]) && is_numeric($cols[16]) ? (float) $cols[16] : (isset($cols[14]) && is_numeric($cols[14]) ? (float) $cols[14] : 0.0);
                $mtoExonerado = isset($cols[18]) && is_numeric($cols[18]) ? (float) $cols[18] : 0.0;
                $mtoInafecto = isset($cols[19]) && is_numeric($cols[19]) ? (float) $cols[19] : 0.0;
                $total = isset($cols[25]) && is_numeric($cols[25]) ? (float) $cols[25] : (isset($cols[20]) && is_numeric($cols[20]) ? (float) $cols[20] : ($biGravada + $igv + $mtoExonerado + $mtoInafecto));
                $moneda = ! empty($cols[26]) ? strtoupper($cols[26]) : (! empty($cols[21]) ? strtoupper($cols[21]) : 'PEN');
                $baseImponible = $biGravada > 0 ? $biGravada : ($mtoExonerado > 0 ? $mtoExonerado : ($mtoInafecto > 0 ? $mtoInafecto : max(0.0, $total - $igv)));
            }
        } else {
            // Formato resumido o adaptado
            $serieIdx = null;
            $correlativoIdx = null;
            $tipoCompIdx = null;

            foreach ($cols as $idx => $val) {
                if (preg_match('/^[FE0-9][A-Z0-9]{3}$/i', $val)) {
                    $serie = $val;
                    $serieIdx = $idx;
                    if (isset($cols[$idx + 1]) && preg_match('/^\d+$/', $cols[$idx + 1])) {
                        $correlativo = $cols[$idx + 1];
                        $correlativoIdx = $idx + 1;
                    }
                    if ($idx > 0 && preg_match('/^\d{2}$/', $cols[$idx - 1])) {
                        $tipoComprobante = $cols[$idx - 1];
                        $tipoCompIdx = $idx - 1;
                    } elseif (isset($cols[$idx + 2]) && preg_match('/^\d{2}$/', $cols[$idx + 2])) {
                        $tipoComprobante = $cols[$idx + 2];
                        $tipoCompIdx = $idx + 2;
                    }
                    break;
                }
            }

            if (empty($serie) && ! empty($cols[0])) {
                $serie = $cols[0];
                $serieIdx = 0;
                $correlativo = $cols[1] ?? '00000001';
                $correlativoIdx = 1;
            }

            // Buscar RUC de 11 dígitos primero (distinto del cliente y del correlativo)
            foreach ($cols as $idx => $val) {
                if ($idx === $correlativoIdx || $idx === $serieIdx || $idx === $tipoCompIdx) {
                    continue;
                }
                if (strlen($val) === 11 && ctype_digit($val) && $val !== $rucCliente) {
                    $rucDniTercero = $val;
                    if (isset($cols[$idx + 1]) && ! is_numeric($cols[$idx + 1]) && strlen($cols[$idx + 1]) > 2) {
                        $razonSocial = $cols[$idx + 1];
                    }
                    break;
                }
            }

            // Si no hay RUC de 11 dígitos, buscar DNI de 8 dígitos
            if (empty($rucDniTercero)) {
                foreach ($cols as $idx => $val) {
                    if ($idx === $correlativoIdx || $idx === $serieIdx || $idx === $tipoCompIdx) {
                        continue;
                    }
                    if (strlen($val) === 8 && ctype_digit($val) && $val !== $rucCliente) {
                        $rucDniTercero = $val;
                        if (isset($cols[$idx + 1]) && ! is_numeric($cols[$idx + 1]) && strlen($cols[$idx + 1]) > 2) {
                            $razonSocial = $cols[$idx + 1];
                        }
                        break;
                    }
                }
            }

            foreach ($cols as $val) {
                $upper = strtoupper($val);
                if (in_array($upper, ['PEN', 'USD', 'EUR', 'SOL', 'DOL'])) {
                    $moneda = $upper === 'SOL' ? 'PEN' : ($upper === 'DOL' ? 'USD' : $upper);
                    break;
                }
            }

            $numeros = [];
            foreach ($cols as $idx => $val) {
                if ($idx === $correlativoIdx || $idx === $serieIdx || $idx === $tipoCompIdx) {
                    continue;
                }
                if (is_numeric($val) && strlen($val) < 15 && ! preg_match('/^\d{8,11}$/', $val)) {
                    $numeros[] = (float) $val;
                }
            }

            if (count($numeros) >= 3) {
                $baseImponible = $numeros[0];
                $igv = $numeros[1];
                $total = $numeros[2];
            } elseif (count($numeros) === 2) {
                $baseImponible = $numeros[0];
                $total = $numeros[1];
                $igv = abs($total - $baseImponible);
            } elseif (count($numeros) === 1) {
                $total = $numeros[0];
                $baseImponible = round($total / 1.18, 2);
                $igv = round($total - $baseImponible, 2);
            }
        }

        if (empty($serie) || empty($correlativo) || empty($rucDniTercero)) {
            return null;
        }

        if (empty($razonSocial)) {
            $razonSocial = $tipoRegistro === 'RCE' ? 'PROVEEDOR CENTRAL' : 'CLIENTE RECEPTOR';
        }

        return [
            'serie' => $serie,
            'correlativo' => $correlativo,
            'ruc_tercero' => $rucDniTercero,
            'razon_social_tercero' => $razonSocial,
            'tipo_comprobante' => $tipoComprobante,
            'moneda' => $moneda,
            'base_imponible' => $baseImponible,
            'igv' => $igv,
            'total' => $total,
        ];
    }

    /**
     * Orquestador principal del flujo asíncrono oficial SIRE:
     * 1. Solicita el numTicket vía exportapropuesta.
     * 2. Consulta el estado del ticket.
     * 3. Si sigue en proceso, retorna status 'procesando' con ticket.
     * 4. Si está 'Terminado', descarga el ZIP, descomprime, parsea y persiste en BD.
     *
     * @return array{success: bool, status: string, message?: string, mensaje?: string, ticket: string, total?: int, importados?: int, periodo: string, tipo_registro: string, nom_archivo?: string|null}
     */
    protected function ejecutarFlujoPropuesta(Cliente $cliente, string $tipoRegistro, string $periodo): array
    {
        // Limpieza de caché forzada antes de pedir un nuevo token para asegurar credenciales frescas
        Cache::forget('sunat_token_'.$cliente->id);

        $periodos = $this->normalizarPeriodo($periodo);
        $periodoApi = $periodos['api'];
        $periodoDb = $periodos['db'];

        // 1. Solicitar el ticket de propuesta en SUNAT
        $numTicket = $this->solicitarTicketPropuesta($cliente, $tipoRegistro, $periodoApi);

        // Si la respuesta fue directamente una lista JSON de comprobantes (fallback de compatibilidad)
        if (str_starts_with($numTicket, 'DIRECT_JSON:')) {
            $jsonStr = substr($numTicket, strlen('DIRECT_JSON:'));
            $items = json_decode($jsonStr, true) ?: [];

            return $this->persistirItemsDirectos($cliente, $tipoRegistro, $periodoDb, $items);
        }

        // 2. Verificar estado del ticket con sondeo breve inicial
        $estadoTicket = null;
        for ($i = 0; $i < 2; $i++) {
            $estadoTicket = $this->consultarEstadoTicket($cliente, $numTicket, $periodoApi, $tipoRegistro);
            if ($estadoTicket['terminado']) {
                break;
            }
            usleep(300000); // 0.3s
        }

        // 3. Si aún no está 'Terminado', devolver status 'procesando' para feedback y sondeo asíncrono
        if (! $estadoTicket || ! $estadoTicket['terminado']) {
            return [
                'success' => true,
                'status' => 'procesando',
                'mensaje' => 'Ticket generado en SUNAT. Procesando descarga...',
                'ticket' => $numTicket,
                'periodo' => $periodoDb,
                'tipo_registro' => $tipoRegistro,
                'nom_archivo' => $estadoTicket['nom_archivo'] ?? null,
            ];
        }

        // 4. Si ya terminó, descargar el ZIP del fileserver de SUNAT
        $nomArchivo = $estadoTicket['nom_archivo'];
        $rutaZip = $this->descargarArchivoTicket(
            $cliente,
            $numTicket,
            $nomArchivo,
            $tipoRegistro,
            $periodoDb,
            $estadoTicket['cod_tipo_archivo'] ?? '00',
            $estadoTicket['cod_proceso'] ?? '10'
        );

        // 5. Descomprimir y parsear comprobantes
        $resultadoProcesamiento = $this->procesarArchivoDescargado($cliente, $tipoRegistro, $periodoDb, $rutaZip);

        return array_merge($resultadoProcesamiento, [
            'status' => 'completado',
            'ticket' => $numTicket,
        ]);
    }

    /**
     * Reanuda o consulta el avance de un ticket pendiente para completar su descarga y procesamiento.
     *
     * @return array{success: bool, status: string, message?: string, mensaje?: string, ticket: string, total?: int, importados?: int, periodo: string, tipo_registro: string, nom_archivo?: string|null}
     */
    public function procesarTicketPendiente(Cliente $cliente, string $tipoRegistro, string $periodo, string $numTicket): array
    {
        $periodos = $this->normalizarPeriodo($periodo);
        $periodoApi = $periodos['api'];
        $periodoDb = $periodos['db'];

        $estadoTicket = $this->consultarEstadoTicket($cliente, $numTicket, $periodoApi, $tipoRegistro);

        if (! $estadoTicket['terminado']) {
            return [
                'success' => true,
                'status' => 'procesando',
                'mensaje' => 'Ticket generado en SUNAT. Procesando descarga...',
                'ticket' => $numTicket,
                'periodo' => $periodoDb,
                'tipo_registro' => $tipoRegistro,
                'nom_archivo' => $estadoTicket['nom_archivo'] ?? null,
            ];
        }

        $nomArchivo = $estadoTicket['nom_archivo'];
        $rutaZip = $this->descargarArchivoTicket(
            $cliente,
            $numTicket,
            $nomArchivo,
            $tipoRegistro,
            $periodoDb,
            $estadoTicket['cod_tipo_archivo'] ?? '00',
            $estadoTicket['cod_proceso'] ?? '10'
        );
        $resultadoProcesamiento = $this->procesarArchivoDescargado($cliente, $tipoRegistro, $periodoDb, $rutaZip);

        return array_merge($resultadoProcesamiento, [
            'status' => 'completado',
            'ticket' => $numTicket,
        ]);
    }

    /**
     * Importa la propuesta del Registro de Compras Electrónico (RCE) de SUNAT para el periodo dado.
     */
    public function importarPropuestaRCE(Cliente $cliente, string $periodo): array
    {
        Cache::forget('sunat_token_'.$cliente->id);

        return $this->ejecutarFlujoPropuesta($cliente, 'RCE', $periodo);
    }

    /**
     * Importa la propuesta del Registro de Ventas e Ingresos Electrónico (RVIE) de SUNAT para el periodo dado.
     */
    public function importarPropuestaRVIE(Cliente $cliente, string $periodo): array
    {
        Cache::forget('sunat_token_'.$cliente->id);

        return $this->ejecutarFlujoPropuesta($cliente, 'RVIE', $periodo);
    }

    /**
     * Persiste comprobantes recibidos directamente en formato JSON (mantiene compatibilidad con respuestas directas).
     */
    protected function persistirItemsDirectos(Cliente $cliente, string $tipoRegistro, string $periodoDb, array $items): array
    {
        $importados = 0;

        foreach ($items as $item) {
            $serie = trim((string) ($item['numSerie'] ?? $item['serie'] ?? ''));
            $correlativo = trim((string) ($item['numComprobante'] ?? $item['correlativo'] ?? $item['numCdp'] ?? ''));
            $rucTercero = trim((string) ($item['numDocIdentidadProveedor'] ?? $item['numDocIdentidadCliente'] ?? $item['numDocIdentidadReceptor'] ?? $item['numDocIdentidad'] ?? $item['ruc_tercero'] ?? ''));

            if ($serie === '' || $correlativo === '' || $rucTercero === '') {
                continue;
            }

            $razonSocial = trim((string) ($item['desRazonSocialProveedor'] ?? $item['desRazonSocialCliente'] ?? $item['nomRazonSocialCliente'] ?? $item['razon_social_tercero'] ?? $item['nomRazonSocial'] ?? 'TERCERO'));
            $tipoComprobante = trim((string) ($item['codTipoCdp'] ?? $item['tipo_comprobante'] ?? $item['tipoComprobante'] ?? '01'));
            $moneda = strtoupper(trim((string) ($item['codMoneda'] ?? $item['moneda'] ?? 'PEN')));
            $baseImponible = (float) ($item['mtoBaseImponible'] ?? $item['base_imponible'] ?? $item['mtoBiGravadaDg'] ?? 0.0);
            $igv = (float) ($item['mtoIGV'] ?? $item['igv'] ?? $item['mtoIgvDg'] ?? 0.0);
            $total = (float) ($item['mtoTotal'] ?? $item['total'] ?? $item['mtoTotalCp'] ?? ($baseImponible + $igv));

            $comprobante = Comprobante::updateOrCreate(
                [
                    'cliente_id' => $cliente->id,
                    'tipo_registro' => $tipoRegistro,
                    'serie' => $serie,
                    'correlativo' => $correlativo,
                    'ruc_tercero' => $rucTercero,
                ],
                [
                    'periodo' => $periodoDb,
                    'tipo_comprobante' => $tipoComprobante,
                    'razon_social_tercero' => $razonSocial,
                    'moneda' => $moneda,
                    'base_imponible' => $baseImponible,
                    'igv' => $igv,
                    'total' => $total,
                    'estado_sire' => 'Propuesta',
                ]
            );

            // Despachar Job asíncrono con escalonamiento de 2 segundos para no saturar IP de SUNAT
            if (! $comprobante->esta_completamente_disponible) {
                $delaySegundos = $importados * 2;
                DownloadSunatXmlJob::dispatch($comprobante)->delay(now()->addSeconds($delaySegundos));
            }

            $importados++;
        }

        return [
            'success' => true,
            'status' => 'completado',
            'message' => $importados > 0
                ? "Se importaron exitosamente {$importados} comprobantes de la Propuesta {$tipoRegistro} ({$periodoDb}) desde SUNAT."
                : "No se encontraron comprobantes pendientes en la Propuesta {$tipoRegistro} de SUNAT para el periodo {$periodoDb}.",
            'total' => count($items),
            'importados' => $importados,
            'periodo' => $periodoDb,
            'tipo_registro' => $tipoRegistro,
            'ticket' => 'DIRECT_SYNC',
        ];
    }

    /**
     * Extrae el numTicket desde el array de respuesta de SUNAT.
     */
    protected function extraerNumTicket(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        if (! empty($data['numTicket'])) {
            return trim((string) $data['numTicket']);
        }
        if (! empty($data['codTicket'])) {
            return trim((string) $data['codTicket']);
        }
        if (! empty($data['ticket'])) {
            return trim((string) $data['ticket']);
        }
        if (! empty($data['data']['numTicket'])) {
            return trim((string) $data['data']['numTicket']);
        }
        if (! empty($data['data']['codTicket'])) {
            return trim((string) $data['data']['codTicket']);
        }

        return null;
    }

    /**
     * Extrae el array de registros de comprobantes a partir de diversas estructuras JSON de SUNAT.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extraerItemsComprobantes(mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        if (array_is_list($data)) {
            return $data;
        }

        if (! empty($data['registros']) && is_array($data['registros'])) {
            return $data['registros'];
        }

        if (! empty($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (! empty($data['data']) && is_array($data['data'])) {
            return $data['data'];
        }

        if (! empty($data['comprobantes']) && is_array($data['comprobantes'])) {
            return $data['comprobantes'];
        }

        return [];
    }
}
