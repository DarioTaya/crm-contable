<?php

namespace App\Traits;

use App\Models\Estudio;
use App\Models\Scopes\EstudioScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToEstudio
{
    /**
     * Boot the BelongsToEstudio trait for a model.
     */
    public static function bootBelongsToEstudio(): void
    {
        static::addGlobalScope(new EstudioScope);

        static::creating(function (Model $model) {
            if (empty($model->estudio_id)) {
                $model->estudio_id = static::estudioActualId();
            }
        });
    }

    /**
     * Identificador del estudio bajo el que opera la peticion actual.
     *
     * Ante la ausencia de sesion (comandos de consola, colas, seeders) recae en
     * el estudio principal de la instalacion, que en la distribucion de
     * escritorio es siempre el unico existente.
     */
    public static function estudioActualId(): int
    {
        return Auth::user()?->estudio_id
            ?? Estudio::query()->oldest('id')->value('id')
            ?? 1;
    }

    /**
     * Relación con el Estudio Contable (Tenant).
     */
    public function estudio(): BelongsTo
    {
        return $this->belongsTo(Estudio::class, 'estudio_id');
    }
}
