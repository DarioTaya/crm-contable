<?php

namespace App\Models;

use App\Traits\BelongsToEstudio;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AjusteSistema extends Model
{
    use BelongsToEstudio, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'ajustes_sistema';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'estudio_id',
        'razon_social',
        'ruc',
        'representante',
        'correo_corporativo',
        'telefono_whatsapp',
        'frecuencia_cron_horas',
        'notificar_whatsapp',
        'nivel_alerta_whatsapp',
        'ultima_sincronizacion_at',
        'ultima_sincronizacion_sunafil_at',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'estudio_id' => 'integer',
            'frecuencia_cron_horas' => 'integer',
            'notificar_whatsapp' => 'boolean',
            'ultima_sincronizacion_at' => 'datetime',
            'ultima_sincronizacion_sunafil_at' => 'datetime',
        ];
    }

    /**
     * Obtiene el registro de configuración del sistema correspondiente al estudio actual o lo inicializa por defecto.
     */
    public static function getSettings(): self
    {
        $estudioId = auth()->user()?->estudio_id ?? 1;

        return static::firstOrCreate(
            ['estudio_id' => $estudioId],
            [
                'razon_social' => 'Grupo DC Soluciones Contables SAC',
                'ruc' => '20601234567',
                'representante' => 'Sebastián Taya Yucra',
                'correo_corporativo' => 'contacto@grupodc.pe',
                'telefono_whatsapp' => '987654321',
                'frecuencia_cron_horas' => 8,
                'notificar_whatsapp' => false,
                'nivel_alerta_whatsapp' => 'Solo Altas',
            ]
        );
    }
}
