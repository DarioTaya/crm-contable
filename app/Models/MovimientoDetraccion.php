<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoDetraccion extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * @var string
     */
    protected $table = 'movimientos_detracciones';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'tipo',
        'fecha',
        'periodo',
        'monto',
        'descripcion',
        'numero_operacion',
        'numero_constancia',
        'comprobante_referencia',
        'adquiriente_ruc',
        'adquiriente_razon_social',
        'tipo_bien',
        'origen',
        'abono_deuda_id',
        'metadatos',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'monto_formateado',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'monto' => 'decimal:2',
            'metadatos' => 'array',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function abonoDeuda(): BelongsTo
    {
        return $this->belongsTo(AbonoDeuda::class, 'abono_deuda_id');
    }

    public function isDeposito(): bool
    {
        return $this->tipo === 'deposito';
    }

    public function isRetiro(): bool
    {
        return $this->tipo === 'retiro';
    }

    public function getMontoFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) $this->monto, 2);
    }
}
