<?php

namespace App\Models;

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
        'ventas_no_gravadas',
        'compras_base',
        'igv_compras',
        'igv_saldo_favor_anterior',
        'igv_retenciones',
        'igv_percepciones',
        'igv_saldo_favor_siguiente',
        'igv_resultante_pagar',
        'renta_base_ingresos',
        'renta_tasa',
        'renta_calculada',
        'renta_saldo_favor_anterior',
        'renta_compensacion_itan',
        'renta_retenciones',
        'renta_resultante_pagar',
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
            'ventas_no_gravadas' => 'decimal:2',
            'compras_base' => 'decimal:2',
            'igv_compras' => 'decimal:2',
            'igv_saldo_favor_anterior' => 'decimal:2',
            'igv_retenciones' => 'decimal:2',
            'igv_percepciones' => 'decimal:2',
            'igv_saldo_favor_siguiente' => 'decimal:2',
            'igv_resultante_pagar' => 'decimal:2',
            'renta_base_ingresos' => 'decimal:2',
            'renta_tasa' => 'decimal:4',
            'renta_calculada' => 'decimal:2',
            'renta_saldo_favor_anterior' => 'decimal:2',
            'renta_compensacion_itan' => 'decimal:2',
            'renta_retenciones' => 'decimal:2',
            'renta_resultante_pagar' => 'decimal:2',
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
     * Recalcula la determinación de IGV y Renta conforme a la técnica del PDT 621.
     */
    public function recalcularDeterminacion(): void
    {
        // 1. IGV: Si es zona de Selva (Ley de Amazonía 27037), no genera débito ni pago de IGV
        if ($this->es_zona_selva) {
            $this->igv_ventas = 0.00;
            $this->igv_resultante_pagar = 0.00;
            $this->igv_saldo_favor_siguiente = 0.00;
        } else {
            // Débito y Crédito estándar
            if ((float) $this->ventas_base > 0 && (float) $this->igv_ventas <= 0) {
                $this->igv_ventas = round((float) $this->ventas_base * 0.18, 2);
            }
            if ((float) $this->compras_base > 0 && (float) $this->igv_compras <= 0) {
                $this->igv_compras = round((float) $this->compras_base * 0.18, 2);
            }

            $debito = (float) ($this->igv_ventas ?? 0);
            $credito = (float) ($this->igv_compras ?? 0);
            $saldoAnterior = (float) ($this->igv_saldo_favor_anterior ?? 0);

            $diferencia = $debito - $credito - $saldoAnterior;

            if ($diferencia > 0) {
                $retenciones = (float) ($this->igv_retenciones ?? 0);
                $percepciones = (float) ($this->igv_percepciones ?? 0);
                $pagar = max(0.00, round($diferencia - $retenciones - $percepciones, 2));

                $this->igv_resultante_pagar = $pagar;
                $this->igv_saldo_favor_siguiente = 0.00;
            } else {
                $this->igv_resultante_pagar = 0.00;
                $this->igv_saldo_favor_siguiente = round(abs($diferencia), 2);
            }
        }

        // 2. Renta: Ingresos netos del mes (Ventas gravadas + Ventas no gravadas)
        $totalVentas = (float) $this->ventas_base + (float) $this->ventas_no_gravadas;
        if ((float) $this->renta_base_ingresos <= 0 && $totalVentas > 0) {
            $this->renta_base_ingresos = round($totalVentas, 2);
        }

        if ((float) $this->renta_base_ingresos > 0 && (float) $this->renta_calculada <= 0 && (float) $this->renta_tasa > 0) {
            $tasa = (float) $this->renta_tasa;
            // Si viene en porcentaje ej 1.0 ó 1.5, convertir
            $factor = $tasa > 0.1 ? ($tasa / 100) : $tasa;
            $this->renta_calculada = round((float) $this->renta_base_ingresos * $factor, 2);
        }

        $rentaBruta = (float) ($this->renta_calculada ?? 0);
        $compensacionSaldo = (float) ($this->renta_saldo_favor_anterior ?? 0);
        $compensacionItan = (float) ($this->renta_compensacion_itan ?? 0);
        $retenciones3ra = (float) ($this->renta_retenciones ?? 0);

        $rentaNeto = max(0.00, round($rentaBruta - $compensacionSaldo - $compensacionItan - $retenciones3ra, 2));
        $this->renta_resultante_pagar = $rentaNeto;
    }
}
