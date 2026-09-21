<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlBalance extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'control_balances';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'anio',
        'meses',
        'dj_anual',
        'notas',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'meses' => 'array',
            'notas' => 'array',
        ];
    }

    /**
     * Relación inversa: El balance pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Obtiene el estado del balance para un mes específico (1 al 12).
     */
    public function getEstadoMes(int $mes): string
    {
        $meses = $this->meses ?? [];

        return $meses[$mes] ?? $meses[(string) $mes] ?? 'Pendiente';
    }

    /**
     * Asigna el estado del balance para un mes específico (1 al 12).
     */
    public function setEstadoMes(int $mes, string $estado): void
    {
        $meses = $this->meses ?? [];
        $meses[$mes] = $estado;
        $this->meses = $meses;
    }

    /**
     * Obtiene la nota de balance para un mes específico (1 al 12).
     */
    public function getNotaMes(int $mes): string
    {
        $notas = $this->notas ?? [];

        return $notas[$mes] ?? $notas[(string) $mes] ?? '';
    }

    /**
     * Asigna la nota de balance para un mes específico (1 al 12).
     */
    public function setNotaMes(int $mes, string $nota): void
    {
        $notas = $this->notas ?? [];
        $notas[$mes] = $nota;
        $this->notas = $notas;
    }
}
