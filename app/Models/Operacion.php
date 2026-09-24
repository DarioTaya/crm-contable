<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operacion extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'operaciones';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'periodo',
        'estado_compras_ventas',
        'estado_pdt621',
        'estado_ple',
        'notas',
        'estado_pago_honorarios',
        'tiene_deuda',
        'monto_deuda_inicial',
        'vencimiento_deuda',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiene_deuda' => 'boolean',
            'monto_deuda_inicial' => 'decimal:2',
            'vencimiento_deuda' => 'date',
        ];
    }

    /**
     * Relación inversa (1 a N): La operación mensual pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relación 1 a N: Una operación puede tener múltiples abonos de deuda.
     */
    public function abonosDeuda(): HasMany
    {
        return $this->hasMany(AbonoDeuda::class, 'operacion_id');
    }

    /**
     * Suma total de los abonos realizados a esta deuda.
     */
    protected function totalAbonado(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round((float) $this->abonosDeuda->sum('monto'), 2),
        );
    }

    /**
     * Saldo pendiente: monto de deuda inicial menos la suma de abonos.
     * Retorna 0 si no hay deuda registrada.
     */
    protected function saldoPendiente(): Attribute
    {
        return Attribute::make(
            get: function (): float {
                if (! $this->tiene_deuda || ! $this->monto_deuda_inicial) {
                    return 0;
                }

                return round((float) $this->monto_deuda_inicial - $this->total_abonado, 2);
            },
        );
    }
}
