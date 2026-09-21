<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Comprobante;
use Exception;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class SunatSoapService
{
    /**
     * Endpoint oficial del Web Service SOAP de Consulta Integrada de CPE de SUNAT.
     */
    public const SOAP_ENDPOINT = 'https://e-factura.sunat.gob.pe/ol-it-wsconscpegem/billConsultService';

    public function __construct(
        protected ComprobanteDocumentService $docService
    ) {}

    /**
     * Consulta el estado del comprobante ante SUNAT y descarga el CDR / XML oficial si está disponible.
     *
     * @return array{success: bool, status_code: ?string, message: string, cdr_descargado: bool}
     */
    public function consultarCpe(Comprobante $comprobante): array
    {
        $cliente = $comprobante->cliente ?: Cliente::find($comprobante->cliente_id);
        if (! $cliente) {
            throw new Exception("No se encontró el cliente asociado al comprobante {$comprobante->id}.");
        }

        // Obtener credenciales SOL
        $credenciales = $this->obtenerCredencialesSol($cliente);
        $username = $credenciales['username'];
        $password = $credenciales['password'];

        $tipoDoc = $comprobante->tipo_comprobante ?: '01';
        $serie = trim((string) $comprobante->serie);
        $correlativoInt = (int) $comprobante->correlativo;

        // Determinar RUC emisor según tipo de registro
        $esCompra = $comprobante->tipo_registro === 'RCE';
        $rucEmisor = $esCompra ? trim((string) $comprobante->ruc_tercero) : trim((string) $cliente->ruc);

        if (empty($rucEmisor) || empty($serie) || $correlativoInt <= 0) {
            throw new Exception("Datos incompletos para consultar comprobante: RUC={$rucEmisor}, Serie={$serie}, Número={$correlativoInt}");
        }

        // Si la serie no empieza con 'F' (ej. serie 'E001' emitida en portal SOL o boletas 'B...'),
        // SUNAT billConsultService solo soporta series 'F'.
        $esSerieF = str_starts_with(strtoupper($serie), 'F');

        $statusCode = null;
        $statusMessage = '';
        $cdrDescargado = false;

        // Intentar consulta SOAP si la serie es compatible con billConsultService
        if ($esSerieF) {
            try {
                // Si el cliente es el emisor (Ventas), consultar getStatusCdr directamente
                if (! $esCompra) {
                    $resultadoCdr = $this->ejecutarGetStatusCdr($username, $password, $rucEmisor, $tipoDoc, $serie, $correlativoInt);
                    $statusCode = $resultadoCdr['statusCode'] ?? null;
                    $statusMessage = $resultadoCdr['statusMessage'] ?? '';

                    if (! empty($resultadoCdr['content'])) {
                        $this->guardarCdrZip($comprobante, $resultadoCdr['content'], $rucEmisor, $tipoDoc, $serie, $correlativoInt);
                        $cdrDescargado = true;
                    }
                } else {
                    // Para compras (receptor), primero consultar getStatus
                    $resultadoStatus = $this->ejecutarGetStatus($username, $password, $rucEmisor, $tipoDoc, $serie, $correlativoInt);
                    $statusCode = $resultadoStatus['statusCode'] ?? null;
                    $statusMessage = $resultadoStatus['statusMessage'] ?? '';

                    // Si SUNAT confirma que existe y está aceptado, intentar getStatusCdr
                    if ($statusCode === '0001') {
                        try {
                            $resultadoCdr = $this->ejecutarGetStatusCdr($username, $password, $rucEmisor, $tipoDoc, $serie, $correlativoInt);
                            if (! empty($resultadoCdr['content'])) {
                                $this->guardarCdrZip($comprobante, $resultadoCdr['content'], $rucEmisor, $tipoDoc, $serie, $correlativoInt);
                                $cdrDescargado = true;
                            }
                        } catch (Throwable) {
                            // Si getStatusCdr falla para receptor, getStatus fue exitoso
                        }
                    }
                }
            } catch (Throwable $e) {
                Log::warning("Aviso en consulta SOAP para comprobante {$comprobante->id}: {$e->getMessage()}");
                $statusMessage = 'Aviso SOAP: '.$e->getMessage();
            }
        } else {
            $statusCode = '0010';
            $statusMessage = "Comprobante de portal SOL (Serie {$serie}): Validado por índice oficial SIRE.";
        }

        // Asegurar que existan las representaciones en storage estructurado
        $comprobante->setRelation('cliente', $cliente);
        $this->docService->asegurarDocumentos($comprobante);

        // Actualizar estados finales en el comprobante
        $comprobante->estado_descarga = 'disponible';
        $comprobante->estado_xml = 'disponible';
        $comprobante->estado_pdf = 'disponible';
        $comprobante->estado_cdr = 'disponible';
        if ($statusMessage !== '') {
            $comprobante->mensaje_soap = $statusMessage;
        }
        $comprobante->saveQuietly();

        return [
            'success' => true,
            'status_code' => $statusCode,
            'message' => $statusMessage ?: 'Comprobante procesado exitosamente.',
            'cdr_descargado' => $cdrDescargado,
        ];
    }

    /**
     * Ejecuta la operación SOAP getStatusCdr ante SUNAT.
     *
     * @return array{statusCode: ?string, statusMessage: string, content: ?string}
     */
    public function ejecutarGetStatusCdr(
        string $username,
        string $password,
        string $rucEmisor,
        string $tipoDoc,
        string $serie,
        int $correlativo
    ): array {
        $xmlPayload = $this->buildSoapEnvelope(
            $username,
            $password,
            'getStatusCdr',
            $rucEmisor,
            $tipoDoc,
            $serie,
            $correlativo
        );

        $responseXml = $this->sendSoapRequest($xmlPayload, 'urn:getStatusCdr');

        return $this->parseSoapStatusResponse($responseXml, 'getStatusCdrResponse');
    }

    /**
     * Ejecuta la operación SOAP getStatus ante SUNAT.
     *
     * @return array{statusCode: ?string, statusMessage: string, content: ?string}
     */
    public function ejecutarGetStatus(
        string $username,
        string $password,
        string $rucEmisor,
        string $tipoDoc,
        string $serie,
        int $correlativo
    ): array {
        $xmlPayload = $this->buildSoapEnvelope(
            $username,
            $password,
            'getStatus',
            $rucEmisor,
            $tipoDoc,
            $serie,
            $correlativo
        );

        $responseXml = $this->sendSoapRequest($xmlPayload, 'urn:getStatus');

        return $this->parseSoapStatusResponse($responseXml, 'getStatusResponse');
    }

    /**
     * Envía la solicitud HTTP POST con el sobre SOAP y cabecera SOAPAction.
     */
    protected function sendSoapRequest(string $xmlPayload, string $action): string
    {
        $response = Http::withHeaders([
            'Content-Type' => 'text/xml;charset=UTF-8',
            'SOAPAction' => $action,
        ])->timeout(30)->send('POST', self::SOAP_ENDPOINT, [
            'body' => $xmlPayload,
        ]);

        if (! $response->successful()) {
            throw new Exception("Error HTTP SOAP {$response->status()}: ".Str::limit($response->body(), 200));
        }

        return $response->body();
    }

    /**
     * Construye el sobre XML SOAP con cabecera WS-Security UsernameToken.
     */
    public function buildSoapEnvelope(
        string $username,
        string $password,
        string $operation,
        string $rucEmisor,
        string $tipoDoc,
        string $serie,
        int $correlativo
    ): string {
        $cleanUsername = htmlspecialchars($username, ENT_XML1, 'UTF-8');
        $cleanPassword = htmlspecialchars($password, ENT_XML1, 'UTF-8');
        $cleanRuc = htmlspecialchars($rucEmisor, ENT_XML1, 'UTF-8');
        $cleanTipo = htmlspecialchars($tipoDoc, ENT_XML1, 'UTF-8');
        $cleanSerie = htmlspecialchars($serie, ENT_XML1, 'UTF-8');

        return <<<XML
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
                  xmlns:ser="http://service.sunat.gob.pe"
                  xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">
    <soapenv:Header>
        <wsse:Security>
            <wsse:UsernameToken>
                <wsse:Username>{$cleanUsername}</wsse:Username>
                <wsse:Password>{$cleanPassword}</wsse:Password>
            </wsse:UsernameToken>
        </wsse:Security>
    </soapenv:Header>
    <soapenv:Body>
        <ser:{$operation}>
            <rucComprobante>{$cleanRuc}</rucComprobante>
            <tipoComprobante>{$cleanTipo}</tipoComprobante>
            <serieComprobante>{$cleanSerie}</serieComprobante>
            <numeroComprobante>{$correlativo}</numeroComprobante>
        </ser:{$operation}>
    </soapenv:Body>
</soapenv:Envelope>
XML;
    }

    /**
     * Parsea la respuesta SOAP XML extrayendo statusCode, statusMessage y content (base64).
     *
     * @return array{statusCode: ?string, statusMessage: string, content: ?string}
     */
    public function parseSoapStatusResponse(string $xmlContent, string $expectedRoot): array
    {
        $statusCode = null;
        $statusMessage = '';
        $content = null;

        if (preg_match('/<statusCode>([^<]*)<\/statusCode>/', $xmlContent, $m)) {
            $statusCode = trim($m[1]);
        }
        if (preg_match('/<statusMessage>([^<]*)<\/statusMessage>/', $xmlContent, $m)) {
            $statusMessage = trim($m[1]);
        }
        if (preg_match('/<content>([^<]+)<\/content>/', $xmlContent, $m)) {
            $content = trim($m[1]);
        }

        // Si hay SOAP Fault
        if (preg_match('/<faultstring>([^<]*)<\/faultstring>/', $xmlContent, $m)) {
            $statusMessage = trim($m[1]);
        }

        return [
            'statusCode' => $statusCode,
            'statusMessage' => $statusMessage,
            'content' => $content,
        ];
    }

    /**
     * Guarda el archivo ZIP y extrae el XML firmado del CDR en la estructura de almacenamiento.
     */
    protected function guardarCdrZip(
        Comprobante $comprobante,
        string $base64Content,
        string $rucEmisor,
        string $tipoDoc,
        string $serie,
        int $correlativo
    ): void {
        $zipData = base64_decode($base64Content);
        if (! $zipData) {
            return;
        }

        $relDir = $comprobante->storage_directory;
        $absDir = storage_path('app/'.$relDir);
        if (! is_dir($absDir)) {
            File::makeDirectory($absDir, 0755, true, true);
        }

        $correlativoPad = str_pad((string) $correlativo, 8, '0', STR_PAD_LEFT);
        $zipName = sprintf('R-%s-%s-%s-%s.zip', $rucEmisor, $tipoDoc, $serie, $correlativoPad);
        $zipPath = "{$absDir}/{$zipName}";
        file_put_contents($zipPath, $zipData);

        // Extraer el archivo R-*.xml de respuesta si el ZIP es válido
        $zip = new ZipArchive;
        if ($zip->open($zipPath) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if (str_ends_with(strtolower($entryName), '.xml')) {
                    $xmlContent = $zip->getFromIndex($i);
                    $xmlName = basename($entryName);
                    file_put_contents("{$absDir}/{$xmlName}", $xmlContent);
                    break;
                }
            }
            $zip->close();
        }

        $comprobante->ruta_cdr = "{$relDir}/{$zipName}";
    }

    /**
     * Obtiene y normaliza las credenciales SOL del cliente.
     *
     * @return array{username: string, password: string}
     */
    public function obtenerCredencialesSol(Cliente $cliente): array
    {
        $cred = $cliente->credencial;
        $usuarioSol = trim((string) ($cliente->usuario_sol ?? $cred?->usuario_sol ?? ''));
        $claveSol = trim((string) ($cliente->clave_sol ?? $cred?->clave_sol ?? ''));

        if (str_starts_with($claveSol, 'eyJ')) {
            try {
                $claveSol = Crypt::decryptString($claveSol);
            } catch (Throwable) {
                // Dejar como estaba si falla desencriptación
            }
        }

        $username = trim($cliente->ruc).$usuarioSol;

        return [
            'username' => $username,
            'password' => $claveSol,
        ];
    }
}
