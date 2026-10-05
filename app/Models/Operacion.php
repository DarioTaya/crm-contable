<?php

namespace App\Models;

use App\Enums\TipoTributoSunat;
use App\Services\Catalogos\CatalogoSunat;
use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'tributo_codigo',
        'es_igv_justo',
        'monto_deuda_inicial',
        'vencimiento_deuda',
    ];

    /**
     * Atributos que deben anexarse en arrays o JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'saldo_pendiente',
        'total_abonado',
        'tributo_nombre',
        'tributo_etiqueta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tiene_deuda' => 'boolean',
            'tributo_codigo' => 'string',
            'es_igv_justo' => 'boolean',
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
     * Tributos adeudados en este periodo. Cada uno lleva sus propios abonos.
     */
    public function deudasTributarias(): HasMany
    {
        return $this->hasMany(DeudaTributaria::class, 'operacion_id');
    }

    /**
     * Liquidación detallada del periodo (IGV, Renta, Crédito Fiscal, ITAN).
     */
    public function liquidacionPeriodo(): HasOne
    {
        return $this->hasOne(LiquidacionPeriodo::class, 'operacion_id');
    }

    /**
     * Copia en la operación el resumen del mes para el anillo de la matriz.
     * El detalle por tributo vive en deuda_tributarias.
     */
    public function refrescarResumenDeudas(): void
    {
        $deudas = $this->deudasTributarias()->get();

        if ($deudas->isEmpty()) {
            $this->monto_deuda_inicial = 0;
            $this->tributo_codigo = null;
            $this->es_igv_justo = false;
            $this->vencimiento_deuda = null;
            $this->tiene_deuda = false;
            if ($this->exists) {
                $this->save();
            }

            return;
        }

        $this->monto_deuda_inicial = round((float) $deudas->sum(fn (DeudaTributaria $deuda) => (float) $deuda->monto_deuda_inicial), 2);
        $referencia = $deudas->sortBy(fn (DeudaTributaria $deuda) => $deuda->vencimiento_deuda?->timestamp ?? PHP_INT_MAX)->first();
        $this->tributo_codigo = $referencia->tributo_codigo;
        $this->es_igv_justo = (bool) $referencia->es_igv_justo;
        $this->vencimiento_deuda = $referencia->vencimiento_deuda;
        $this->tiene_deuda = true;
        if ($this->exists) {
            $this->save();
        }
    }

    /**
     * Instancia del enum del tributo SUNAT si fue seleccionado.
     */
    public function tributoEnum(): ?TipoTributoSunat
    {
        return $this->tributo_codigo ? TipoTributoSunat::tryFrom($this->tributo_codigo) : null;
    }

    /**
     * Nombre oficial descriptivo del tributo adeudado.
     */
    protected function tributoNombre(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->tributo_codigo
                ? app(CatalogoSunat::class)->nombre((string) $this->tributo_codigo, $this->estudio_id, 'Deuda Tributaria SUNAT')
                : 'Deuda Tributaria SUNAT',
        );
    }

    /**
     * Nombre corto del tributo adeudado.
     */
    protected function tributoNombreCorto(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->tributo_codigo
                ? app(CatalogoSunat::class)->nombreCorto((string) $this->tributo_codigo, $this->estudio_id)
                : 'Tributo',
        );
    }

    /**
     * Etiqueta completa con código oficial [ej. [1011] IGV Cuenta Propia 18%].
     */
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

                return max(0.00, round((float) $this->monto_deuda_inicial - $this->total_abonado, 2));
            },
        );
    }

    /**
     * Monto de deuda inicial formateado con estándar peruano (S/ 0,000.00).
     */
    public function getMontoDeudaInicialFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) ($this->monto_deuda_inicial ?? 0), 2);
    }

    /**
     * Total abonado formateado con estándar peruano (S/ 0,000.00).
     */
    public function getTotalAbonadoFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) $this->total_abonado, 2);
    }

    /**
     * Saldo pendiente formateado con estándar peruano (S/ 0,000.00).
     */
    public function getSaldoPendienteFormateadoAttribute(): string
    {
        return 'S/ '.number_format((float) $this->saldo_pendiente, 2);
    }
}
