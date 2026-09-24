<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonoDeuda extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * @var string
     */
    protected $table = 'abonos_deudas';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'operacion_id',
        'monto',
        'fecha_pago',
        'observacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    /**
     * Resuelve automáticamente el estudio_id a partir de la operación padre.
     */
    protected static function booted(): void
    {
        static::creating(function (AbonoDeuda $abono) {
            if (empty($abono->estudio_id) && ! empty($abono->operacion_id)) {
                $abono->estudio_id = Operacion::withoutGlobalScopes()
                    ->whereKey($abono->operacion_id)
                    ->value('estudio_id') ?? auth()->user()?->estudio_id;
            }
        });
    }

    /**
     * Relación inversa: el abono pertenece a una operación mensual.
     */
    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class, 'operacion_id');
    }

    /**
     * Acceso al cliente a través de la operación.
     */
    public function cliente(): BelongsTo
    {
        return $this->operacion->cliente();
    }
}
