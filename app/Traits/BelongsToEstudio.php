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
                $model->estudio_id = Auth::user()?->estudio_id ?? 1;
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
