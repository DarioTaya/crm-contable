<?php

namespace App\Models;

use App\Services\ControlImpuestos\CalculadorLiquidacionService;
use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiquidacionPeriodo extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    protected $table = 'liquidaciones_periodo';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'operacion_id',
        'periodo',
        'es_zona_selva',
        'ventas_base',
        'igv_ventas',
        'igv_descuentos_base',
        'igv_descuentos',
        'ventas_no_gravadas',
        'igv_ventas_ley_base',
        'igv_ventas_ley',
        'compras_base',
        'igv_compras',
        'igv_compras_ley_base',
        'igv_compras_ley',
        'igv_compras_mixtas_base',
        'igv_compras_mixtas',
        'igv_importaciones_base',
        'igv_importaciones',
        'igv_saldo_favor_anterior',
        'igv_retenciones',
        'igv_retenciones_saldo_anterior',
        'igv_retenciones_saldo_siguiente',
        'igv_percepciones',
        'igv_percepciones_saldo_anterior',
        'igv_percepciones_saldo_siguiente',
        'igv_saldo_favor_siguiente',
        'igv_resultante_pagar',
        'igv_determinado_por_sunat',
        'renta_base_ingresos',
        'renta_tasa',
        'renta_calculada',
        'renta_saldo_favor_anterior',
        'renta_compensacion_itan',
        'renta_itan_saldo_anterior',
        'renta_itan_saldo_siguiente',
        'renta_retenciones',
        'renta_resultante_pagar',
        'renta_determinado_por_sunat',
        'renta_tributo_codigo',
        'renta_saldo_favor_siguiente',
        'plame_essalud_base',
        'plame_essalud_a_pagar',
        'plame_onp_a_pagar',
        'plame_renta_4ta_base',
        'plame_renta_4ta_a_pagar',
        'plame_renta_5ta_a_pagar',
        'plame_afp_a_pagar',
        'notas_liquidacion',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_zona_selva' => 'boolean',
            'ventas_base' => 'decimal:2',
            'igv_ventas' => 'decimal:2',
            'igv_descuentos_base' => 'decimal:2',
            'igv_descuentos' => 'decimal:2',
            'ventas_no_gravadas' => 'decimal:2',
            'igv_ventas_ley_base' => 'decimal:2',
            'igv_ventas_ley' => 'decimal:2',
            'compras_base' => 'decimal:2',
            'igv_compras' => 'decimal:2',
            'igv_compras_ley_base' => 'decimal:2',
            'igv_compras_ley' => 'decimal:2',
            'igv_compras_mixtas_base' => 'decimal:2',
            'igv_compras_mixtas' => 'decimal:2',
            'igv_importaciones_base' => 'decimal:2',
            'igv_importaciones' => 'decimal:2',
            'igv_saldo_favor_anterior' => 'decimal:2',
            'igv_retenciones' => 'decimal:2',
            'igv_retenciones_saldo_anterior' => 'decimal:2',
            'igv_retenciones_saldo_siguiente' => 'decimal:2',
            'igv_percepciones' => 'decimal:2',
            'igv_percepciones_saldo_anterior' => 'decimal:2',
            'igv_percepciones_saldo_siguiente' => 'decimal:2',
            'igv_saldo_favor_siguiente' => 'decimal:2',
            'igv_resultante_pagar' => 'decimal:2',
            'igv_determinado_por_sunat' => 'boolean',
            'renta_base_ingresos' => 'decimal:2',
            'renta_tasa' => 'decimal:4',
            'renta_calculada' => 'decimal:2',
            'renta_saldo_favor_anterior' => 'decimal:2',
            'renta_compensacion_itan' => 'decimal:2',
            'renta_itan_saldo_anterior' => 'decimal:2',
            'renta_itan_saldo_siguiente' => 'decimal:2',
            'renta_retenciones' => 'decimal:2',
            'renta_resultante_pagar' => 'decimal:2',
            'renta_determinado_por_sunat' => 'boolean',
            'renta_saldo_favor_siguiente' => 'decimal:2',
            'plame_essalud_base' => 'decimal:2',
            'plame_essalud_a_pagar' => 'decimal:2',
            'plame_onp_a_pagar' => 'decimal:2',
            'plame_renta_4ta_base' => 'decimal:2',
            'plame_renta_4ta_a_pagar' => 'decimal:2',
            'plame_renta_5ta_a_pagar' => 'decimal:2',
            'plame_afp_a_pagar' => 'decimal:2',
        ];
    }

    public function operacion(): BelongsTo
    {
        return $this->belongsTo(Operacion::class, 'operacion_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Recalcula la determinación de IGV, Renta y PLAME conforme a la técnica oficial SUNAT.
     */
    public function recalcularDeterminacion(?Cliente $cliente = null): void
    {
        app(CalculadorLiquidacionService::class)->determinar($this, $cliente);
    }
}
