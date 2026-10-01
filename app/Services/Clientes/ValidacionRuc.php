<?php

namespace App\Services\Clientes;

use App\Models\Cliente;

/**
 * Misma respuesta que el alta manual: formato, módulo 11, padrón y duplicado
 * dentro del estudio.
 */
class ValidacionRuc
{
    public function __construct(private ConsultaPadronRuc $padron) {}

    /**
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    public function evaluar(string $ruc, bool $recordar = false): array
    {
        $mensaje = RucSunat::mensajeSiInvalido($ruc);
        if ($mensaje !== null) {
            return [
                'estado' => 422,
                'cuerpo' => [
                    'valid' => false,
                    'message' => $mensaje,
                ],
            ];
        }

        $padron = $this->padron->consultar($ruc, $recordar);

        $clienteExistente = Cliente::where('ruc', $ruc)->first();
        if ($clienteExistente) {
            if ($padron['razon_social'] === null || $padron['razon_social'] === '') {
                $padron['razon_social'] = $clienteExistente->razon_social;
            }

            return [
                'estado' => 200,
                'cuerpo' => [
                    'valid' => true,
                    'duplicate' => true,
                    'message' => "Este RUC ya se encuentra registrado en el CRM: {$clienteExistente->razon_social}",
                    'cliente' => [
                        'id' => $clienteExistente->id,
                        'razon_social' => $clienteExistente->razon_social,
                    ],
                    ...$padron,
                ],
            ];
        }

        $mensajePadron = $padron['razon_social']
            ? "RUC verificado ante SUNAT: {$padron['razon_social']} (".trim("{$padron['estado_sunat']} / {$padron['condicion_sunat']}", ' /').')'
            : "RUC válido ante SUNAT ({$padron['tipo_persona']}).";

        return [
            'estado' => 200,
            'cuerpo' => [
                'valid' => true,
                'duplicate' => false,
                ...$padron,
                'message' => $mensajePadron,
            ],
        ];
    }
}
