<?php

namespace App\Services\ControlImpuestos;

use App\Enums\TipoTributoSunat;
use App\Models\DeudaTributaria;
use App\Models\Operacion;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Validation\ValidationException;

/**
 * Alta o actualización de un tributo en un periodo.
 *
 * Es el mismo registro que usa la caja de Tributos y pagos: Control de deudas
 * y el libro de impuestos del cliente leen estas filas.
 */
class RegistrarTributoPeriodo
{
    /**
     * @param  array{
     *     tributo_codigo?: string|null,
     *     monto_deuda_inicial?: float|int|string|null,
     *     deuda_id?: int|null,
     *     es_igv_justo?: bool|null,
     *     vencimiento_deuda?: string|null
     * }  $datos
     */
    public function registrar(Operacion $operacion, array $datos): void
    {
        $codigo = strtoupper(trim((string) ($datos['tributo_codigo'] ?? '')));
        if (! app(CatalogoSunat::class)->aceptaTributo($codigo, $operacion->estudio_id)) {
            throw ValidationException::withMessages([
                'tributo_codigo' => 'Seleccione un tributo SUNAT válido.',
            ]);
        }

        $monto = array_key_exists('monto_deuda_inicial', $datos) ? $datos['monto_deuda_inicial'] : null;
        if ($monto === null || (float) $monto <= 0) {
            throw ValidationException::withMessages([
                'monto_deuda_inicial' => 'Ingrese el monto a pagar de este tributo.',
            ]);
        }

        $deuda = null;
        if (! empty($datos['deuda_id'])) {
            $deuda = $operacion->deudasTributarias()->whereKey($datos['deuda_id'])->first();
            if (! $deuda) {
                throw ValidationException::withMessages([
                    'deuda_id' => 'El tributo indicado no pertenece a este periodo.',
                ]);
            }
        }

        $duplicado = $operacion->deudasTributarias()
            ->where('tributo_codigo', $codigo)
            ->when($deuda, fn ($consulta) => $consulta->whereKeyNot($deuda->id))
            ->exists();

        if ($duplicado) {
            throw ValidationException::withMessages([
                'tributo_codigo' => 'Ese tributo ya está registrado en este mes.',
            ]);
        }

        $abonado = $deuda ? (float) $deuda->abonos()->sum('monto') : 0;
        if ((float) $monto + 0.001 < $abonado) {
            if (! empty($datos['permitir_ajuste_abono'])) {
                $monto = $abonado;
            } else {
                throw ValidationException::withMessages([
                    'monto_deuda_inicial' => 'El monto no puede ser menor que los pagos ya registrados de este tributo.',
                ]);
            }
        }

        $esPrimera = $operacion->deudasTributarias()->doesntExist();
        if (! $deuda) {
            $deuda = new DeudaTributaria([
                'operacion_id' => $operacion->id,
                'estudio_id' => $operacion->estudio_id,
            ]);
        }

        $deuda->fill([
            'tributo_codigo' => $codigo,
            'es_igv_justo' => $codigo === TipoTributoSunat::Igv18->value && (bool) ($datos['es_igv_justo'] ?? false),
            'monto_deuda_inicial' => $monto,
            'vencimiento_deuda' => $datos['vencimiento_deuda'] ?? null,
        ]);
        $deuda->save();

        if ($esPrimera) {
            $operacion->abonosDeuda()->whereNull('deuda_tributaria_id')->update([
                'deuda_tributaria_id' => $deuda->id,
            ]);
        }

        $operacion->refrescarResumenDeudas();
    }
}
