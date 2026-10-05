<?php

namespace App\Models;

use App\Traits\BelongsToEstudio;
use Illuminate\Database\Eloquent\Model;

class CatalogoTributo extends Model
{
    use BelongsToEstudio;

    protected $table = 'catalogo_tributos';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'codigo',
        'denominacion',
        'nombre_corto',
        'formulario',
        'rol',
        'regimenes',
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
            'regimenes' => 'array',
            'sistema' => 'boolean',
            'activo' => 'boolean',
            'orden' => 'integer',
        ];
    }

    public function etiquetaSelector(): string
    {
        return $this->codigo.' '.$this->denominacion;
    }
}
