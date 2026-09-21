<?php

namespace App\Models;

use App\Traits\BelongsToEstudio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use BelongsToEstudio, HasFactory, SoftDeletes;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'clientes';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'numero',
        'razon_social',
        'ruc',
        'regimen_tributario',
        'estado_contribuyente',
        'condicion_domicilio',
        'tipo_contribuyente',
        'direccion_fiscal',
        'ubigeo',
        'distrito',
        'provincia',
        'departamento',
        'fecha_sincronizacion_sunat',
        'correo',
        'telefono',
        'estado',
        'fecha_baja',
        'mes_baja',
        'motivo_baja',
        'notas_estado',
        'es_buen_contribuyente',
        'sunat_client_id',
        'sunat_client_secret',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'numero' => 'integer',
            'fecha_baja' => 'date',
            'es_buen_contribuyente' => 'boolean',
            'sunat_client_secret' => 'encrypted',
            'fecha_sincronizacion_sunat' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Mutador de compatibilidad para el atributo estado.
     * Soporta valores booleanos legacy (true/false) convirtiéndolos a ('activo'/'retirado').
     */
    public function setEstadoAttribute(mixed $value): void
    {
        if ($value === true || $value === 1 || $value === '1') {
            $this->attributes['estado'] = 'activo';
        } elseif ($value === false || $value === 0 || $value === '0') {
            $this->attributes['estado'] = 'retirado';
        } else {
            $this->attributes['estado'] = strtolower((string) $value);
        }
    }

    /**
     * Scope para filtrar clientes activos en el estudio contable.
     */
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    /**
     * Scope para filtrar clientes suspendidos en el estudio contable.
     */
    public function scopeSuspendidos($query)
    {
        return $query->where('estado', 'suspendido');
    }

    /**
     * Scope para filtrar clientes retirados o dados de baja en el estudio contable.
     */
    public function scopeRetirados($query)
    {
        return $query->where('estado', 'retirado');
    }

    /**
     * Determina si el cliente está activo en el estudio contable.
     */
    public function isActivo(): bool
    {
        return $this->estado === 'activo';
    }

    /**
     * Determina si el cliente se encuentra suspendido en el estudio contable.
     */
    public function isSuspendido(): bool
    {
        return $this->estado === 'suspendido';
    }

    /**
     * Determina si el cliente ya no pertenece al estudio (baja o cambio de contador).
     */
    public function isRetirado(): bool
    {
        return $this->estado === 'retirado';
    }

    /**
     * Accesor para etiqueta amigable del estado en el estudio.
     */
    public function getEstadoEstudioLabelAttribute(): string
    {
        return match ($this->estado) {
            'activo' => 'Activo',
            'suspendido' => 'Suspendido',
            'retirado' => 'Retirado / Baja',
            default => ucfirst($this->estado),
        };
    }

    /**
     * El método booted registra hooks del ciclo de vida del modelo.
     */
    protected static function booted(): void
    {
        static::creating(function (Cliente $cliente) {
            if (empty($cliente->numero)) {
                $cliente->numero = self::proximoNumeroDisponible();
            }
        });
    }

    /**
     * Calcula el menor número correlativo positivo disponible (reutiliza huecos de eliminados o asigna el siguiente).
     */
    public static function proximoNumeroDisponible(): int
    {
        $ocupados = self::withTrashed()->pluck('numero')->filter()->flip();
        $candidato = 1;
        while ($ocupados->has($candidato)) {
            $candidato++;
        }

        return $candidato;
    }

    /**
     * Relación 1 a 1: Un cliente tiene una única credencial de accesos.
     */
    public function credencial(): HasOne
    {
        return $this->hasOne(Credencial::class, 'cliente_id');
    }

    /**
     * Accesor para usuario_sol derivado de la credencial asociada.
     */
    public function getUsuarioSolAttribute(): ?string
    {
        return $this->credencial?->usuario_sol;
    }

    /**
     * Accesor para clave_sol desencriptada derivada de la credencial asociada.
     */
    public function getClaveSolAttribute(): ?string
    {
        return $this->credencial?->clave_sol;
    }

    /**
     * Relación 1 a N: Un cliente recibe múltiples notificaciones (SUNAT / SUNAFIL).
     */
    public function notificaciones(): HasMany
    {
        return $this->hasMany(Notificacion::class, 'cliente_id');
    }

    /**
     * Relación 1 a N: Un cliente tiene múltiples declaraciones y operaciones mensuales.
     */
    public function operaciones(): HasMany
    {
        return $this->hasMany(Operacion::class, 'cliente_id');
    }

    /**
     * Relación 1 a N: Un cliente tiene múltiples registros anuales de control de balances.
     */
    public function controlBalances(): HasMany
    {
        return $this->hasMany(ControlBalance::class, 'cliente_id');
    }

    /**
     * Relación 1 a N: Un cliente recibe múltiples alertas en su buzón electrónico (SUNAT / SUNAFIL).
     */
    public function alertas(): HasMany
    {
        return $this->hasMany(AlertaBuzon::class, 'cliente_id');
    }

    /**
     * Relación 1 a N: Un cliente posee registros de compras y ventas en el SIRE (RCE y RVIE).
     */
    public function comprobantes(): HasMany
    {
        return $this->hasMany(Comprobante::class, 'cliente_id');
    }

    /**
     * Relación N a N: Asistentes que tienen asignada la gestión de este cliente.
     */
    public function asistentes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cliente_user')->withTimestamps();
    }

    /**
     * Scope para filtrar clientes según el rol del usuario autenticado:
     * Si es Administrador -> ve todos los clientes del estudio.
     * Si es Asistente -> ve únicamente los clientes que tiene explícitamente asignados.
     */
    public function scopeParaUsuario(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query;
        }

        return $query->whereHas('asistentes', function (Builder $q) use ($user) {
            $q->where('users.id', $user->id);
        });
    }
}
