<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Estudio extends Model
{
    use HasFactory;

    /**
     * Tabla asociada.
     *
     * @var string
     */
    protected $table = 'estudios';

    /**
     * Atributos asignables masivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nombre',
        'ruc',
        'correo',
        'telefono',
        'direccion',
        'logo_path',
        'activo',
        'configuracion',
    ];

    /**
     * Atributos casteados.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'configuracion' => 'array',
        ];
    }

    /**
     * Usuarios pertenecientes al estudio.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Clientes registrados en el estudio.
     */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /**
     * Configuración del sistema para este estudio.
     */
    public function ajusteSistema(): HasOne
    {
        return $this->hasOne(AjusteSistema::class);
    }
}
