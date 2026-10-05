<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConstanciaDeclaracion extends Model
{
    use BelongsToEstudioViaCliente;

    protected $table = 'constancias_declaracion';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'operacion_id',
        'user_id',
        'formulario',
        'periodo',
        'ruc',
        'razon_social',
        'numero_orden',
        'fecha_presentacion',
        'tipo_declaracion',
        'archivo_path',
        'archivo_nombre',
        'archivo_hash',
        'casillas',
        'tributos',
        'alertas',
        'tiene_detalle',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_presentacion' => 'datetime',
            'casillas' => 'array',
            'tributos' => 'array',
            'alertas' => 'array',
            'tiene_detalle' => 'boolean',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
