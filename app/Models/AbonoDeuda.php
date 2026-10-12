<?php

namespace App\Models;

use App\Services\Catalogos\CatalogoSunat;
use App\Services\Detracciones\ExtractoDetraccionesService;
use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
        'deuda_tributaria_id',
        'monto',
        'fecha_pago',
        'medio_pago',
        'observacion',
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
            'monto' => 'decimal:2',
            'fecha_pago' => 'date',
        ];
    }

    public function etiquetaMedioPago(): string
    {
        $codigo = $this->medio_pago;

        return is_string($codigo) && $codigo !== ''
            ? app(CatalogoSunat::class)->etiquetaMedio($codigo, $this->estudio_id)
            : '';
    }

    /**
     * Monto del abono formateado en Soles (S/ 0,000.00).
     */
    public function getMontoFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) ($this->monto ?? 0), 2);
    }

    /**
     * Determina si el pago fue financiado con cargo a la cuenta de detracciones BN.
     */
    public function esPagoDetracciones(): bool
    {
        $medio = strtolower(trim((string) $this->medio_pago));
        $obs = strtolower(trim((string) $this->observacion));

        return str_starts_with($medio, 'detracc')
            || str_contains($medio, 'detraccion')
            || str_contains($obs, 'cuenta de detracciones')
            || str_contains($obs, 'cuenta detraccion')
            || str_contains($obs, 'cta detraccion')
            || str_contains($obs, 'cargo en cuenta de detracciones');
    }

    /**
     * Resuelve automáticamente el estudio_id a partir de la operación padre
     * y mantiene sincronizado en tiempo real el extracto de cuenta de detracciones.
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

        static::saved(function (AbonoDeuda $abono) {
            app(ExtractoDetraccionesService::class)->sincronizarAbonoDeuda($abono);
        });

        static::deleted(function (AbonoDeuda $abono) {
            app(ExtractoDetraccionesService::class)->eliminarSincronizacion($abono);
        });
    }

    /**
     * Relación inversa: el abono pertenece a una operación mensual.
     */
    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class, 'operacion_id');
    }

    public function deudaTributaria(): BelongsTo
    {
        return $this->belongsTo(DeudaTributaria::class, 'deuda_tributaria_id');
    }

    /**
     * Relación con el movimiento de retiro en el extracto de la cuenta de detracciones.
     */
    public function movimientoDetraccion(): HasOne
    {
        return $this->hasOne(MovimientoDetraccion::class, 'abono_deuda_id');
    }

    /**
     * Acceso al cliente a través de la operación.
     */
    public function cliente(): HasOneThrough
    {
        return $this->hasOneThrough(
            Cliente::class,
            Operacion::class,
            'id',
            'id',
            'operacion_id',
            'cliente_id'
        );
    }
}
