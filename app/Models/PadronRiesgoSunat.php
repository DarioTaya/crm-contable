<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PadronRiesgoSunat extends Model
{
    public const TIPO_SSCO_FIRME = 'ssco_firme';

    public const TIPO_SSCO_VINCULADO = 'ssco_vinculado';

    public const TIPO_DEUDA_COACTIVA = 'deuda_coactiva';

    protected $table = 'padrones_riesgo_sunat';

    protected $fillable = [
        'tipo_padron',
        'ruc',
        'razon_social',
        'nombre_comercial',
        'domicilio_fiscal',
        'resolucion_atribucion',
        'fecha_emision_resolucion',
        'fecha_firme_resolucion',
        'fecha_publicacion',
        'fecha_limite_restriccion',
        'doc_representante',
        'nombre_representante',
        'ruc_ssco_origen',
        'razon_social_ssco_origen',
        'supuesto_inclusion',
        'dependencia_sunat',
        'monto_deuda',
        'cantidad_representantes',
        'corte_sunat',
    ];

    protected function casts(): array
    {
        return [
            'monto_deuda' => 'decimal:2',
            'cantidad_representantes' => 'integer',
        ];
    }
}
