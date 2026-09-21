<?php

namespace App\Traits;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\Scopes\EstudioScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aisla por inquilino los registros que cuelgan de un cliente.
 *
 * A diferencia de BelongsToEstudio, el estudio no se toma de la sesion sino
 * del cliente propietario. La distincion es necesaria porque la sincronizacion
 * de buzones SUNAT/SUNAFIL y las descargas del SIRE corren desde comandos
 * programados y colas, sin usuario autenticado, y recorren clientes de varios
 * estudios: derivarlo de la sesion los asignaria todos al mismo inquilino.
 */
trait BelongsToEstudioViaCliente
{
    public static function bootBelongsToEstudioViaCliente(): void
    {
        static::addGlobalScope(new EstudioScope);

        static::creating(function (Model $model) {
            if (empty($model->estudio_id) && ! empty($model->cliente_id)) {
                $model->estudio_id = Cliente::withoutGlobalScopes()
                    ->whereKey($model->cliente_id)
                    ->value('estudio_id');
            }
        });
    }

    /**
     * Relación con el Estudio Contable (Tenant).
     */
    public function estudio(): BelongsTo
    {
        return $this->belongsTo(Estudio::class, 'estudio_id');
    }
}
