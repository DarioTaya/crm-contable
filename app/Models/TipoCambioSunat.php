<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoCambioSunat extends Model
{
    /**
     * El tipo de cambio es una publicación nacional, no de un estudio.
     *
     * @var string
     */
    protected $table = 'tipos_cambio_sunat';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'fecha',
        'compra',
        'venta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'compra' => 'decimal:4',
            'venta' => 'decimal:4',
        ];
    }
}
