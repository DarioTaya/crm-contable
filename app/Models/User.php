<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'name',
        'email',
        'password',
        'rol',
        'activo',
        'telefono',
    ];

    /**
     * Atributos que deben ocultarse para la serialización.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Estudio al que pertenece el usuario.
     */
    public function estudio(): BelongsTo
    {
        return $this->belongsTo(Estudio::class, 'estudio_id');
    }

    /**
     * Clientes asignados a este usuario (para asistentes).
     */
    public function clientes(): BelongsToMany
    {
        return $this->belongsToMany(Cliente::class, 'cliente_user')->withTimestamps();
    }

    /**
     * Determina si el usuario es administrador del estudio.
     */
    public function isAdmin(): bool
    {
        return $this->rol === 'admin';
    }

    /**
     * Determina si el usuario tiene rol de asistente.
     */
    public function isAsistente(): bool
    {
        return $this->rol === 'asistente';
    }
}
