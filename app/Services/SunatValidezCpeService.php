<?php

namespace App\Services;

use App\Models\Cliente;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SunatValidezCpeService
{
    /**
     * Endpoint oficial para la obtención de tokens OAuth2 de Consulta de Validez de Comprobantes.
     * En SUNAT se registra en "Empresas > Comprobantes de pago > Consulta de Validez > Credenciales de API SUNAT".
     */
    public const AUTH_URL_EXTRANET_TEMPLATE = 'https://api-seguridad.sunat.gob.pe/v1/clientesextranet/%s/oauth2/token/';

    public const AUTH_URL_SUNAT_TEMPLATE = 'https://api-seguridad.sunat.gob.pe/v1/clientessunat/%s/oauth2/token/';

    /**
     * Scope oficial para consultar validez de comprobantes y padrón de contribuyentes.
     */
    public const SCOPE = 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes';

    /**
     * Endpoint oficial de validación integrada de comprobantes de pago.
     * El parámetro en la URL corresponde al RUC del contribuyente consultante.
     */
    public const VALIDA_CPE_URL_TEMPLATE = 'https://api.sunat.gob.pe/v1/contribuyente/contribuyentes/%s/validarcomprobante';

    public const DEFAULT_TIMEOUT_SECONDS = 20;

    public const CACHE_TTL_MINUTES = 50;

    /**
     * Catálogo oficial SUNAT: Estados del Comprobante de Pago (estadoCp).
     */
    public const ESTADOS_CPE = [
        '0' => [
            'codigo' => '0',
            'label' => 'NO EXISTE',
            'descripcion' => 'Comprobante no informado o no registrado en SUNAT.',
            'color' => 'danger',
            'es_valido' => false,
        ],
        '1' => [
            'codigo' => '1',
            'label' => 'ACEPTADO',
            'descripcion' => 'Comprobante válido y registrado en los sistemas de SUNAT.',
            'color' => 'success',
            'es_valido' => true,
        ],
        '2' => [
            'codigo' => '2',
            'label' => 'ANULADO',
            'descripcion' => 'Comprobante comunicado de baja o anulado ante SUNAT.',
            'color' => 'danger',
            'es_valido' => false,
        ],
        '3' => [
            'codigo' => '3',
            'label' => 'AUTORIZADO',
            'descripcion' => 'Comprobante físico con autorización de imprenta vigente.',
            'color' => 'info',
            'es_valido' => true,
        ],
        '4' => [
            'codigo' => '4',
            'label' => 'NO AUTORIZADO',
            'descripcion' => 'Comprobante físico no autorizado por imprenta.',
            'color' => 'danger',
            'es_valido' => false,
        ],
    ];

    /**
     * Catálogo oficial SUNAT: Estado del RUC Emisor (estadoRuc).
     */
    public const ESTADOS_RUC = [
        '00' => ['codigo' => '00', 'label' => 'ACTIVO', 'es_activo' => true, 'descripcion' => 'RUC Activo en el padrón SUNAT.'],
        '01' => ['codigo' => '01', 'label' => 'BAJA PROVISIONAL', 'es_activo' => false, 'descripcion' => 'Baja provisional del RUC.'],
        '02' => ['codigo' => '02', 'label' => 'BAJA PROV. DE OFICIO', 'es_activo' => false, 'descripcion' => 'Baja provisional de oficio efectuada por SUNAT.'],
        '03' => ['codigo' => '03', 'label' => 'SUSPENSIÓN TEMPORAL', 'es_activo' => false, 'descripcion' => 'Actividades suspendidas temporalmente ante SUNAT.'],
        '10' => ['codigo' => '10', 'label' => 'BAJA DEFINITIVA', 'es_activo' => false, 'descripcion' => 'Baja definitiva del RUC.'],
        '11' => ['codigo' => '11', 'label' => 'BAJA DE OFICIO', 'es_activo' => false, 'descripcion' => 'Baja definitiva de oficio por SUNAT.'],
        '22' => ['codigo' => '22', 'label' => 'INHABILITADO', 'es_activo' => false, 'descripcion' => 'Inhabilitado por ventanilla única.'],
    ];

    /**
     * Catálogo oficial SUNAT: Condición de Domicilio Fiscal (condDomiRuc).
     * Requisito sustancial del Art. 18° y 19° de la Ley del IGV y Art. 37° LIR.
     */
    public const CONDICIONES_DOMICILIO = [
        '00' => ['codigo' => '00', 'label' => 'HABIDO', 'es_habido' => true, 'descripcion' => 'Domicilio fiscal habido ante SUNAT.'],
        '09' => ['codigo' => '09', 'label' => 'PENDIENTE', 'es_habido' => false, 'descripcion' => 'Condición de domicilio pendiente de confirmación.'],
        '11' => ['codigo' => '11', 'label' => 'POR VERIFICAR', 'es_habido' => false, 'descripcion' => 'Domicilio fiscal por verificar por SUNAT.'],
        '12' => ['codigo' => '12', 'label' => 'NO HABIDO', 'es_habido' => false, 'descripcion' => 'No Habido. No da derecho a crédito fiscal ni deducción de gasto.'],
        '20' => ['codigo' => '20', 'label' => 'NO HALLADO', 'es_habido' => false, 'descripcion' => 'No Hallado en las notificaciones tributarias de SUNAT.'],
    ];

    /**
     * Tipos de Comprobantes de Pago Electrónicos más frecuentes en Perú.
     */
    public const TIPOS_COMPROBANTE = [
        '01' => '01 - Factura Electrónica',
        '03' => '03 - Boleta de Venta Electrónica',
        '07' => '07 - Nota de Crédito Electrónica',
        '08' => '08 - Nota de Débito Electrónica',
        '09' => '09 - Guía de Remisión Remitente',
        '31' => '31 - Guía de Remisión Transportista',
        'R1' => 'R1 - Recibo por Honorarios Electrónico',
        'R7' => 'R7 - Nota de Crédito por Honorarios',
        '20' => '20 - Comprobante de Retención',
        '40' => '40 - Comprobante de Percepción',
    ];

    /**
     * Obtiene el token de acceso OAuth2 para el cliente consultante.
     */
    public function getAccessToken(Cliente $cliente, bool $forzarRenovacion = false): string
    {
        $cacheKey = "sunat_cpe_token_{$cliente->id}";

        if ($forzarRenovacion) {
            Cache::forget($cacheKey);
        }

        $cachedToken = Cache::get($cacheKey);
        if (is_string($cachedToken) && filled($cachedToken)) {
            return $cachedToken;
        }

        $token = $this->solicitarNuevoToken($cliente);
        Cache::put($cacheKey, $token, now()->addMinutes(self::CACHE_TTL_MINUTES));

        return $token;
    }

    /**
     * Invalida la caché del token del cliente.
     */
    public function olvidarToken(Cliente $cliente): void
    {
        Cache::forget("sunat_cpe_token_{$cliente->id}");
    }

    /**
     * Solicita un nuevo token OAuth2 hacia el servidor de seguridad de SUNAT.
     */
    protected function solicitarNuevoToken(Cliente $cliente): string
    {
        $clientId = $this->sanitizarTexto($cliente->sunat_cpe_client_id);
        $clientSecret = $this->obtenerSecretDesencriptado($cliente);

        if (blank($clientId) || blank($clientSecret)) {
            throw new RuntimeException("El cliente '{$cliente->razon_social}' (RUC {$cliente->ruc}) no tiene configuradas las credenciales de la API de Consulta de Validez (Client ID y Client Secret).");
        }

        $params = [
            'grant_type' => 'client_credentials',
            'scope' => self::SCOPE,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];

        // 1. Intentar con clientesextranet (oficial para Credenciales de API SUNAT)
        $urlExtranet = sprintf(self::AUTH_URL_EXTRANET_TEMPLATE, $clientId);

        try {
            $response = Http::asForm()
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->post($urlExtranet, $params);

            if ($response->successful()) {
                $token = $response->json('access_token');
                if (filled($token)) {
                    return $token;
                }
            }
        } catch (ConnectionException $e) {
            Log::warning("Fallo al conectar con {$urlExtranet}: {$e->getMessage()}");
        } catch (Throwable $e) {
            Log::warning("Error inesperado en token CPE ({$urlExtranet}): {$e->getMessage()}");
        }

        // 2. Fallback a clientessunat
        $urlSunat = sprintf(self::AUTH_URL_SUNAT_TEMPLATE, $clientId);

        try {
            $responseAlt = Http::asForm()
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->post($urlSunat, $params);

            if ($responseAlt->successful()) {
                $token = $responseAlt->json('access_token');
                if (filled($token)) {
                    return $token;
                }
            }

            $status = $responseAlt->status();
            $body = $responseAlt->body();
            throw new RuntimeException("SUNAT rechazó la autenticación (HTTP {$status}): {$body}");
        } catch (Throwable $e) {
            Log::error("Error de autenticación Validez CPE para cliente {$cliente->ruc}: {$e->getMessage()}");
            throw new RuntimeException("No fue posible autenticarse con SUNAT: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Valida un comprobante de pago contra la API de SUNAT y devuelve el resultado enriquecido.
     *
     * @param  Cliente  $cliente  Cliente del estudio con cuyas credenciales se efectúa la consulta
     * @param  array  $datos  [numRuc, codComp, numeroSerie, numero, fechaEmision, monto]
     * @return array Resultado enriquecido con interpretación y diagnóstico contable/tributario
     */
    /**
     * Sanitiza y prepara el payload formal exigido por la API de Validez de SUNAT.
     *
     * @param  array  $datos  [numRuc, codComp, numeroSerie, numero, fechaEmision, monto]
     * @return array{numRuc: string, codComp: string, numeroSerie: string, numero: string, fechaEmision: string, monto: string}
     */
    public function prepararPayload(array $datos): array
    {
        $numRuc = preg_replace('/\D/', '', (string) ($datos['numRuc'] ?? $datos['ruc_emisor'] ?? ''));
        $codComp = str_pad(trim((string) ($datos['codComp'] ?? $datos['tipo_comprobante'] ?? '01')), 2, '0', STR_PAD_LEFT);
        $numeroSerie = strtoupper(trim((string) ($datos['numeroSerie'] ?? $datos['serie'] ?? '')));
        $numeroRaw = trim((string) ($datos['numero'] ?? $datos['correlativo'] ?? '0'));
        $numero = (string) (int) preg_replace('/\D/', '', $numeroRaw);
        if ($numero === '0' && filled($numeroRaw)) {
            $numero = $numeroRaw;
        }

        // Fecha de emisión normalizada a DD/MM/YYYY
        $fechaEmision = $this->normalizarFechaSunat($datos['fechaEmision'] ?? $datos['fecha_emision'] ?? null);

        // Monto formateado con 2 decimales (absoluto positivo siempre, incluso en Notas de Crédito)
        $montoFloat = abs((float) str_replace(',', '', (string) ($datos['monto'] ?? $datos['total'] ?? '0')));
        $monto = number_format($montoFloat, 2, '.', '');

        return [
            'numRuc' => $numRuc,
            'codComp' => $codComp,
            'numeroSerie' => $numeroSerie,
            'numero' => $numero,
            'fechaEmision' => $fechaEmision,
            'monto' => $monto,
        ];
    }

    /**
     * Valida un comprobante individual contra la API de SUNAT y devuelve el resultado enriquecido.
     *
     * @param  Cliente  $cliente  Cliente del estudio con cuyas credenciales se efectúa la consulta
     * @param  array  $datos  [numRuc, codComp, numeroSerie, numero, fechaEmision, monto]
     * @return array Resultado enriquecido con interpretación y diagnóstico contable/tributario
     */
    public function validarComprobante(Cliente $cliente, array $datos): array
    {
        $token = $this->getAccessToken($cliente);
        $rucConsultante = $this->sanitizarTexto($cliente->ruc);
        $payload = $this->prepararPayload($datos);
        $url = sprintf(self::VALIDA_CPE_URL_TEMPLATE, $rucConsultante);

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->post($url, $payload);

            // Si el token expiró (HTTP 401), intentar una renovación inmediata
            if ($response->status() === 401) {
                $token = $this->getAccessToken($cliente, true);
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                    ->post($url, $payload);
            }
        } catch (ConnectionException) {
            return $this->armarRespuestaError($payload, 'Tiempo de espera agotado al conectar con el servicio de SUNAT.', 'timeout');
        } catch (Throwable $e) {
            return $this->armarRespuestaError($payload, "Error de comunicación con SUNAT: {$e->getMessage()}", 'error');
        }

        if (! $response->successful()) {
            $status = $response->status();
            $body = $response->json();
            $mensaje = $body['message'] ?? $response->body() ?? "Error HTTP {$status} recibido de SUNAT.";

            return $this->armarRespuestaError($payload, $mensaje, "http_{$status}");
        }

        $json = $response->json();
        $data = $json['data'] ?? [];

        return $this->interpretarRespuestaSunat($payload, $data, $json['message'] ?? 'Consulta exitosa');
    }

    /**
     * Valida un conjunto o lote de comprobantes concurrentemente usando HTTP Pools.
     * Garantiza máxima velocidad y resiliencia ante expiración de tokens.
     *
     * @param  Cliente  $cliente  Cliente con credenciales activas
     * @param  list<array>  $comprobantes  Lista de comprobantes a validar
     * @return list<array> Resultados enriquecidos con diagnóstico y metadatos
     */
    public function validarLoteComprobantes(Cliente $cliente, array $comprobantes): array
    {
        if (empty($comprobantes)) {
            return [];
        }

        $token = $this->getAccessToken($cliente);
        $rucConsultante = $this->sanitizarTexto($cliente->ruc);
        $url = sprintf(self::VALIDA_CPE_URL_TEMPLATE, $rucConsultante);

        $itemsPreparados = [];
        foreach ($comprobantes as $idx => $cpe) {
            $itemsPreparados[$idx] = [
                'payload' => $this->prepararPayload($cpe),
                'meta' => [
                    'item' => $cpe['item'] ?? ($idx + 1),
                    'razonSocial' => $cpe['razonSocial'] ?? '',
                ],
            ];
        }

        // Peticiones paralelas concurrentes con Http::pool (cURL multi)
        $responses = Http::pool(function (Pool $pool) use ($itemsPreparados, $token, $url) {
            $requests = [];
            foreach ($itemsPreparados as $idx => $item) {
                $requests[$idx] = $pool->withToken($token)
                    ->acceptJson()
                    ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                    ->post($url, $item['payload']);
            }

            return $requests;
        });

        $resultados = [];
        $reintentar401 = [];

        foreach ($itemsPreparados as $idx => $item) {
            $resp = $responses[$idx] ?? null;
            $payload = $item['payload'];
            $meta = $item['meta'];

            if ($resp instanceof Response) {
                if ($resp->status() === 401) {
                    $reintentar401[$idx] = $item;

                    continue;
                }

                if ($resp->successful()) {
                    $json = $resp->json();
                    $eval = $this->interpretarRespuestaSunat($payload, $json['data'] ?? [], $json['message'] ?? 'Consulta exitosa');
                } else {
                    $status = $resp->status();
                    $body = $resp->json();
                    $mensaje = $body['message'] ?? $resp->body() ?? "Error HTTP {$status} recibido de SUNAT.";
                    $eval = $this->armarRespuestaError($payload, $mensaje, "http_{$status}");
                }
            } elseif ($resp instanceof Throwable) {
                $eval = $this->armarRespuestaError($payload, "Error de comunicación con SUNAT: {$resp->getMessage()}", 'error');
            } else {
                $eval = $this->armarRespuestaError($payload, 'Respuesta no recibida de SUNAT.', 'error');
            }

            $eval['item'] = $meta['item'];
            $eval['razonSocial'] = $meta['razonSocial'];
            $resultados[$idx] = $eval;
        }

        // Si expiró el token en medio de las peticiones, refrescar y reintentar sólo los 401
        if (! empty($reintentar401)) {
            $nuevoToken = $this->getAccessToken($cliente, true);
            $reintentosResp = Http::pool(function (Pool $pool) use ($reintentar401, $nuevoToken, $url) {
                $requests = [];
                foreach ($reintentar401 as $idx => $item) {
                    $requests[$idx] = $pool->withToken($nuevoToken)
                        ->acceptJson()
                        ->timeout(self::DEFAULT_TIMEOUT_SECONDS)
                        ->post($url, $item['payload']);
                }

                return $requests;
            });

            foreach ($reintentar401 as $idx => $item) {
                $resp = $reintentosResp[$idx] ?? null;
                $payload = $item['payload'];
                $meta = $item['meta'];

                if ($resp instanceof Response && $resp->successful()) {
                    $json = $resp->json();
                    $eval = $this->interpretarRespuestaSunat($payload, $json['data'] ?? [], $json['message'] ?? 'Consulta exitosa');
                } else {
                    $msg = ($resp instanceof Response) ? ($resp->json('message') ?? $resp->body()) : ($resp?->getMessage() ?? 'Error en reintento');
                    $eval = $this->armarRespuestaError($payload, (string) $msg, 'http_retry_failed');
                }

                $eval['item'] = $meta['item'];
                $eval['razonSocial'] = $meta['razonSocial'];
                $resultados[$idx] = $eval;
            }
        }

        ksort($resultados);

        return array_values($resultados);
    }

    /**
     * Prueba la conexión a la API de Validez de SUNAT con las credenciales del cliente.
     */
    public function testConnection(Cliente $cliente): array
    {
        $inicio = microtime(true);

        try {
            // Fuerza la obtención de un token nuevo para garantizar que las credenciales son 100% válidas en este momento
            $token = $this->getAccessToken($cliente, true);
            $tiempoMs = round((microtime(true) - $inicio) * 1000);

            return [
                'success' => true,
                'message' => '¡Conexión exitosa con la API de Validez de Comprobantes de SUNAT!',
                'latencia_ms' => $tiempoMs,
                'token_preview' => substr($token, 0, 16).'...',
            ];
        } catch (Throwable $e) {
            $tiempoMs = round((microtime(true) - $inicio) * 1000);

            return [
                'success' => false,
                'message' => $e->getMessage(),
                'latencia_ms' => $tiempoMs,
            ];
        }
    }

    /**
     * Interpreta la respuesta técnica de SUNAT y genera el diagnóstico contable-tributario.
     */
    public function interpretarRespuestaSunat(array $payload, array $data, string $mensajeSunat = ''): array
    {
        $estadoCpCodigo = (string) ($data['estadoCp'] ?? '0');
        $estadoRucCodigo = (string) ($data['estadoRuc'] ?? '');
        $condDomiCodigo = (string) ($data['condDomiRuc'] ?? '');
        $observaciones = (array) ($data['observaciones'] ?? []);

        $infoCp = self::ESTADOS_CPE[$estadoCpCodigo] ?? [
            'codigo' => $estadoCpCodigo,
            'label' => 'DESCONOCIDO ('.$estadoCpCodigo.')',
            'descripcion' => 'Código no catalogado.',
            'color' => 'neutral',
            'es_valido' => false,
        ];

        $infoRuc = filled($estadoRucCodigo)
            ? (self::ESTADOS_RUC[$estadoRucCodigo] ?? ['codigo' => $estadoRucCodigo, 'label' => 'CÓD: '.$estadoRucCodigo, 'es_activo' => false, 'descripcion' => ''])
            : null;

        $infoDomi = filled($condDomiCodigo)
            ? (self::CONDICIONES_DOMICILIO[$condDomiCodigo] ?? ['codigo' => $condDomiCodigo, 'label' => 'CÓD: '.$condDomiCodigo, 'es_habido' => false, 'descripcion' => ''])
            : null;

        // Diagnóstico Contable / Tributario de Alto Nivel
        $esValido = $infoCp['es_valido'] === true;
        $esActivo = $infoRuc ? ($infoRuc['es_activo'] ?? false) : false;
        $esHabido = $infoDomi ? ($infoDomi['es_habido'] ?? false) : false;

        $severidad = 'success';
        $estadoTributario = 'APTO';
        $diagnostico = '';

        if ($estadoCpCodigo === '1' || $estadoCpCodigo === '3') {
            if ($infoDomi && ! $esHabido) {
                $severidad = 'warning';
                $estadoTributario = 'OBSERVADO_NO_HABIDO';
                $diagnostico = "OBSERVACIÓN TRIBUTARIA: El emisor tiene condición de {$infoDomi['label']}. Conforme al Art. 18° y 19° de la Ley del IGV y Art. 37° de la Ley del Impuesto a la Renta, no otorga derecho a crédito fiscal ni deducción de costo/gasto a menos que regularice su domicilio antes del cierre del ejercicio.";
            } elseif ($infoRuc && ! $esActivo) {
                $severidad = 'warning';
                $estadoTributario = 'OBSERVADO_NO_ACTIVO';
                $diagnostico = "OBSERVACIÓN TRIBUTARIA: El emisor se encuentra en estado {$infoRuc['label']}. Verificar la fecha de emisión contra la fecha de baja/suspensión del proveedor.";
            } else {
                $severidad = 'success';
                $estadoTributario = 'APTO';
                $diagnostico = 'APTO: Comprobante válido y registrado en SUNAT con emisor ACTIVO y HABIDO. Cumple requisitos para Crédito Fiscal del IGV y deducción de Impuesto a la Renta.';
            }
        } elseif ($estadoCpCodigo === '2') {
            $severidad = 'danger';
            $estadoTributario = 'ANULADO';
            $diagnostico = 'RECHAZADO: Comprobante ANULADO o DADO DE BAJA ante SUNAT. Carece de valor contable y tributario.';
        } elseif ($estadoCpCodigo === '0') {
            $severidad = 'danger';
            $estadoTributario = 'NO_EXISTE';
            $diagnostico = 'RECHAZADO: El comprobante NO EXISTE en las bases de datos de SUNAT. Posible comprobante apócrifo o aún no transmitido por el emisor.';
        } else {
            $severidad = 'danger';
            $estadoTributario = 'NO_AUTORIZADO';
            $diagnostico = 'RECHAZADO: Comprobante físico no autorizado por imprenta autorizada.';
        }

        return [
            'success' => true,
            'comprobante' => $payload,
            'estado_cp' => $infoCp,
            'estado_ruc' => $infoRuc,
            'condicion_domicilio' => $infoDomi,
            'observaciones' => $observaciones,
            'estado_tributario' => $estadoTributario,
            'severidad' => $severidad,
            'diagnostico' => $diagnostico,
            'es_valido_credito_fiscal' => ($estadoTributario === 'APTO'),
            'mensaje_sunat' => $mensajeSunat,
            'consultado_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Construye un resultado de error controlado cuando la petición a SUNAT falla.
     */
    protected function armarRespuestaError(array $payload, string $mensajeError, string $codigoError): array
    {
        return [
            'success' => false,
            'comprobante' => $payload,
            'estado_cp' => [
                'codigo' => 'ERR',
                'label' => 'ERROR CONSULTA',
                'descripcion' => $mensajeError,
                'color' => 'danger',
                'es_valido' => false,
            ],
            'estado_ruc' => null,
            'condicion_domicilio' => null,
            'observaciones' => [],
            'estado_tributario' => 'ERROR_CONEXION',
            'severidad' => 'danger',
            'diagnostico' => "No se pudo verificar con SUNAT: {$mensajeError}",
            'es_valido_credito_fiscal' => false,
            'mensaje_sunat' => $mensajeError,
            'codigo_error' => $codigoError,
            'consultado_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Normaliza cualquier formato de fecha a DD/MM/YYYY requerido por la API de SUNAT.
     */
    public function normalizarFechaSunat(mixed $valor): string
    {
        if (blank($valor)) {
            return now()->format('d/m/Y');
        }

        $str = trim((string) $valor);

        // Si viene D/M/AAAA o DD/MM/AAAA
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $str, $m)) {
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);

            return "{$dia}/{$mes}/{$m[3]}";
        }

        // Si viene YYYY-MM-DD o YYYY/MM/DD
        if (preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})/', $str, $m)) {
            $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $dia = str_pad($m[3], 2, '0', STR_PAD_LEFT);

            return "{$dia}/{$mes}/{$m[1]}";
        }

        // Si viene DD-MM-YYYY o D-M-YYYY
        if (preg_match('/^(\d{1,2})-(\d{1,2})-(\d{4})/', $str, $m)) {
            $dia = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mes = str_pad($m[2], 2, '0', STR_PAD_LEFT);

            return "{$dia}/{$mes}/{$m[3]}";
        }

        // Si es número de serie de fecha de Excel (e.g. 45548 o 46266)
        // Usar gmdate() estrictamente para evitar que la zona horaria UTC-5 (America/Lima) reste 5 horas
        // a las 00:00:00 UTC y traslade la fecha al día anterior.
        if (is_numeric($str) && (float) $str > 20000 && (float) $str < 60000) {
            $timestamp = ((int) $str - 25569) * 86400;

            return gmdate('d/m/Y', $timestamp);
        }

        try {
            return Carbon::parse($str)->format('d/m/Y');
        } catch (Throwable) {
            return now()->format('d/m/Y');
        }
    }

    /**
     * Extrae de forma segura el Client Secret desencriptado del cliente.
     */
    protected function obtenerSecretDesencriptado(Cliente $cliente): string
    {
        try {
            $rawSecret = (string) $cliente->sunat_cpe_client_secret;
        } catch (DecryptException) {
            $rawSecret = (string) $cliente->getRawOriginal('sunat_cpe_client_secret');
        }

        // Si por persistencias previas quedó un segundo payload cifrado
        if (is_string($rawSecret) && str_starts_with($rawSecret, 'eyJ') && strlen($rawSecret) > 80) {
            try {
                $rawSecret = Crypt::decryptString($rawSecret);
            } catch (Throwable) {
                // Mantener
            }
        }

        return $this->sanitizarTexto($rawSecret);
    }

    /**
     * Limpia caracteres de control, saltos de línea y comillas.
     */
    protected function sanitizarTexto(?string $valor): string
    {
        if ($valor === null) {
            return '';
        }

        $limpio = preg_replace('/^[\s\x00-\x1F\x7F\xC2\xA0]+|[\s\x00-\x1F\x7F\xC2\xA0]+$/u', '', (string) $valor) ?? trim((string) $valor);

        return trim($limpio, "\"'");
    }
}
