<?php

namespace App\Models;

use App\Traits\BelongsToEstudio;
use Illuminate\Database\Eloquent\Model;

class CatalogoMedioPago extends Model
{
    use BelongsToEstudio;

    protected $table = 'catalogo_medios_pago';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'codigo',
        'denominacion',
        'sistema',
        'activo',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sistema' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }
}
