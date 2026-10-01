<?php

namespace App\Services\Clientes;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Consulta el padrón público del RUC. La carga masiva puede recordar unos
 * minutos la respuesta ya obtenida para no pedir dos veces el mismo RUC
 * entre la revisión y el registro. El alta manual no usa esa memoria
 * y tampoco insiste: un corte se muestra al momento, como hasta ahora.
 */
class ConsultaPadronRuc
{
    private const ESPERA_REINTENTO_MICROSEGUNDOS = 800_000;

    /**
     * @return array{
     *     tipo_persona: string,
     *     razon_social: string|null,
     *     estado_sunat: string|null,
     *     condicion_sunat: string|null,
     *     direccion_sunat: string|null,
     *     ubigeo: string|null,
     *     distrito: string|null,
     *     provincia: string|null,
     *     departamento: string|null,
     *     disponibilidad: string
     * }
     */
    public function consultar(string $ruc, bool $recordar = false): array
    {
        if ($recordar) {
            $guardado = Cache::get($this->clave($ruc));
            if (is_array($guardado) && ($guardado['disponibilidad'] ?? '') === 'completo' && ($guardado['razon_social'] ?? '') !== '') {
                return $guardado;
            }
        }

        $padron = $this->descargar($ruc, $recordar);

        if ($recordar && ($padron['disponibilidad'] ?? '') === 'completo' && ($padron['razon_social'] ?? '') !== '') {
            Cache::put($this->clave($ruc), $padron, now()->addMinutes(10));
        }

        return $padron;
    }

    /**
     * @return array{
     *     tipo_persona: string,
     *     razon_social: string|null,
     *     estado_sunat: string|null,
     *     condicion_sunat: string|null,
     *     direccion_sunat: string|null,
     *     ubigeo: string|null,
     *     distrito: string|null,
     *     provincia: string|null,
     *     departamento: string|null,
     *     disponibilidad: string
     * }
     */
    private function descargar(string $ruc, bool $insistir): array
    {
        $timeout = $insistir ? 8 : 3;
        $resultado = $this->intento($ruc, $timeout);

        if ($insistir && ($resultado['disponibilidad'] ?? '') !== 'completo') {
            usleep($this->esperaAntesDeReintentar($resultado['codigo'] ?? 0));
            $resultado = $this->intento($ruc, $timeout);
        }

        unset($resultado['codigo']);

        return $resultado;
    }

    /**
     * @return array{
     *     tipo_persona: string,
     *     razon_social: string|null,
     *     estado_sunat: string|null,
     *     condicion_sunat: string|null,
     *     direccion_sunat: string|null,
     *     ubigeo: string|null,
     *     distrito: string|null,
     *     provincia: string|null,
     *     departamento: string|null,
     *     disponibilidad: string
     * }
     */
    private function intento(string $ruc, int $timeout): array
    {
        $padron = [
            'tipo_persona' => RucSunat::tipoPersona($ruc),
            'razon_social' => null,
            'estado_sunat' => null,
            'condicion_sunat' => null,
            'direccion_sunat' => null,
            'ubigeo' => null,
            'distrito' => null,
            'provincia' => null,
            'departamento' => null,
            'disponibilidad' => 'no_disponible',
            'codigo' => 0,
        ];

        try {
            $apiRes = Http::connectTimeout(3)
                ->timeout($timeout)
                ->get("https://api.apis.net.pe/v1/ruc?numero={$ruc}");
        } catch (Throwable) {
            return $padron;
        }

        if (! $apiRes->successful()) {
            $padron['codigo'] = $apiRes->status();
            if ($apiRes->status() === 404) {
                $padron['disponibilidad'] = 'no_figura';
            }

            return $padron;
        }

        $datosApi = $apiRes->json();
        if (! is_array($datosApi)) {
            return $padron;
        }

        $nombre = trim((string) ($datosApi['nombre'] ?? ''));
        if ($nombre === '') {
            $padron['disponibilidad'] = 'sin_nombre';

            return $padron;
        }

        $padron['disponibilidad'] = 'completo';
        $padron['razon_social'] = $nombre;
        $padron['estado_sunat'] = trim((string) ($datosApi['estado'] ?? ''));
        $padron['condicion_sunat'] = trim((string) ($datosApi['condicion'] ?? ''));
        $padron['direccion_sunat'] = trim((string) ($datosApi['direccion'] ?? ''));
        $padron['ubigeo'] = trim((string) ($datosApi['ubigeo'] ?? ''));
        $padron['distrito'] = trim((string) ($datosApi['distrito'] ?? ''));
        $padron['provincia'] = trim((string) ($datosApi['provincia'] ?? ''));
        $padron['departamento'] = trim((string) ($datosApi['departamento'] ?? ''));

        return $padron;
    }

    private function esperaAntesDeReintentar(int $codigo): int
    {
        if ($codigo === 429) {
            return 4_000_000;
        }

        return self::ESPERA_REINTENTO_MICROSEGUNDOS;
    }

    private function clave(string $ruc): string
    {
        return 'crm.padron-ruc.v1.'.$ruc;
    }
}
