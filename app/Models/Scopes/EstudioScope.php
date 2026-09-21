<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class EstudioScope implements Scope
{
    /**
     * Aplica el ámbito global para aislar por Estudio Contable (Tenant).
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (Auth::hasUser()) {
            $user = Auth::user();
            if ($user && $user->estudio_id) {
                $builder->where($model->qualifyColumn('estudio_id'), $user->estudio_id);
            }
        }
    }
}
