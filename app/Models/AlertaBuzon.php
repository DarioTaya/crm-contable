<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AlertaBuzon extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'alertas_buzon';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'plataforma',
        'asunto',
        'contenido',
        'fecha_recepcion',
        'estado',
        'prioridad',
        'notificado_whatsapp_at',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'fecha_recepcion' => 'datetime',
            'notificado_whatsapp_at' => 'datetime',
        ];
    }

    /**
     * Relación inversa: Cada alerta pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Scope para filtrar alertas con estado Pendiente.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'Pendiente');
    }

    /**
     * Scope para filtrar alertas con estado Leído.
     */
    public function scopeLeidas(Builder $query): Builder
    {
        return $query->where('estado', 'Leído');
    }

    /**
     * Scope para filtrar alertas de Alta Prioridad.
     */
    public function scopeAltaPrioridad(Builder $query): Builder
    {
        return $query->where('prioridad', 'Alta');
    }

    /**
     * Scope para filtrar por plataforma emisora (SUNAT o SUNAFIL).
     */
    public function scopePorPlataforma(Builder $query, string $plataforma): Builder
    {
        return $query->where('plataforma', $plataforma);
    }

    /**
     * Scope para alertas aún no notificadas por WhatsApp.
     */
    public function scopeNoNotificadasWhatsApp(Builder $query): Builder
    {
        return $query->whereNull('notificado_whatsapp_at');
    }

    /**
     * Scope para alertas ya notificadas por WhatsApp.
     */
    public function scopeNotificadasWhatsApp(Builder $query): Builder
    {
        return $query->whereNotNull('notificado_whatsapp_at');
    }

    /**
     * Verifica si la alerta se encuentra en estado Pendiente.
     */
    public function esPendiente(): bool
    {
        return $this->estado === 'Pendiente';
    }

    /**
     * Verifica si la alerta es de alta prioridad.
     */
    public function esAltaPrioridad(): bool
    {
        return $this->prioridad === 'Alta';
    }

    /**
     * Verifica si la alerta ya fue despachada por WhatsApp.
     */
    public function yaFueNotificadaWhatsApp(): bool
    {
        return $this->notificado_whatsapp_at !== null;
    }
}
