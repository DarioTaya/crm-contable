<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class SunatApiException extends Exception
{
    /**
     * Código de estado HTTP devuelto por SUNAT si la petición falló a nivel de transporte.
     */
    protected ?int $httpStatus = null;

    /**
     * Datos o estructura de error devuelta por la API de SUNAT en formato JSON/Array.
     *
     * @var array<string, mixed>|null
     */
    protected ?array $responsePayload = null;

    /**
     * Constructor de la excepción.
     *
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?int $httpStatus = null,
        ?array $responsePayload = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->httpStatus = $httpStatus;
        $this->responsePayload = $responsePayload;
    }

    /**
     * Retorna el código de estado HTTP de la respuesta de SUNAT.
     */
    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /**
     * Retorna el payload de error recibido desde la API de SUNAT.
     *
     * @return array<string, mixed>|null
     */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }
}
