<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credencial extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'credenciales';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'usuario_sol',
        'clave_sol',
        'usuario_afp',
        'clave_afp',
        'dni_titular',
        'cuenta_detracciones',
        'clave_detracciones',
        'banco_1_nombre',
        'banco_1_usuario',
        'banco_1_clave',
        'banco_1_notas',
        'banco_2_nombre',
        'banco_2_usuario',
        'banco_2_clave',
        'banco_2_notas',
    ];

    /**
     * Atributos ocultos para la serialización en arreglos o JSON (Seguridad Crítica).
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'clave_sol',
        'clave_afp',
        'clave_detracciones',
        'banco_1_clave',
        'banco_2_clave',
    ];

    /**
     * Casteos de atributos del modelo.
     *
     * Utiliza el cast nativo 'encrypted' de Laravel (AES-256-CBC con HMAC).
     * Encripta automáticamente al persistir en base de datos y desencripta
     * al consultar el atributo en Eloquent de manera transparente.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clave_sol' => 'encrypted',
            'clave_afp' => 'encrypted',
            'clave_detracciones' => 'encrypted',
            'banco_1_clave' => 'encrypted',
            'banco_2_clave' => 'encrypted',
        ];
    }

    /**
     * Relación inversa (1 a 1): La credencial pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
