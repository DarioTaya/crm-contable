<?php

namespace App\Models;

use App\Enums\TipoTributoSunat;
use App\Models\Scopes\EstudioScope;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeudaTributaria extends Model
{
    protected $table = 'deuda_tributarias';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'operacion_id',
        'tributo_codigo',
        'es_igv_justo',
        'monto_deuda_inicial',
        'vencimiento_deuda',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'saldo_pendiente',
        'total_abonado',
        'tributo_nombre',
        'tributo_nombre_corto',
        'tributo_etiqueta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_igv_justo' => 'boolean',
            'monto_deuda_inicial' => 'decimal:2',
            'vencimiento_deuda' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new EstudioScope);

        static::creating(function (DeudaTributaria $deuda): void {
            if (empty($deuda->estudio_id) && ! empty($deuda->operacion_id)) {
                $deuda->estudio_id = Operacion::withoutGlobalScopes()
                    ->whereKey($deuda->operacion_id)
                    ->value('estudio_id');
            }
        });

        static::deleting(function (DeudaTributaria $deuda): void {
            $deuda->abonos()->get()->each(function (AbonoDeuda $abono): void {
                $abono->delete();
            });
        });
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class, 'operacion_id');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(AbonoDeuda::class, 'deuda_tributaria_id');
    }

    /**
     * Mismo nombre que en Operacion para la tabla de Control de Deudas.
     */
    public function abonosDeuda(): HasMany
    {
        return $this->abonos();
    }

    protected function periodo(): Attribute
    {
        return Attribute::make(
            get: fn (): string => (string) ($this->operacion?->periodo ?? ''),
        );
    }

    protected function cliente(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->operacion?->cliente,
        );
    }

    public function tributoEnum(): ?TipoTributoSunat
    {
        return $this->tributo_codigo ? TipoTributoSunat::tryFrom($this->tributo_codigo) : null;
    }

    protected function tributoNombre(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->tributo_codigo
                ? app(CatalogoSunat::class)->nombre((string) $this->tributo_codigo, $this->estudio_id, 'Deuda Tributaria SUNAT')
                : 'Deuda Tributaria SUNAT',
        );
    }

    protected function tributoNombreCorto(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->tributo_codigo
                ? app(CatalogoSunat::class)->nombreCorto((string) $this->tributo_codigo, $this->estudio_id)
                : 'Tributo',
        );
    }

    protected function tributoEtiqueta(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                if (empty($this->tributo_codigo)) {
                    return 'Deuda Tributaria SUNAT';
                }

                return app(CatalogoSunat::class)->etiqueta((string) $this->tributo_codigo, $this->estudio_id);
            },
        );
    }

    protected function totalAbonado(): Attribute
    {
        return Attribute::make(
            get: fn (): float => round((float) $this->abonos->sum('monto'), 2),
        );
    }

    protected function saldoPendiente(): Attribute
    {
        return Attribute::make(
            get: fn (): float => max(0.00, round((float) $this->monto_deuda_inicial - $this->total_abonado, 2)),
        );
    }

    public function getMontoDeudaInicialFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) ($this->monto_deuda_inicial ?? 0), 2);
    }

    public function getTotalAbonadoFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) $this->total_abonado, 2);
    }

    public function getSaldoPendienteFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) $this->saldo_pendiente, 2);
    }
}
