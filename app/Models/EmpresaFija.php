<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Empresa que un usuario dejó fija en las cinco del acceso rápido.
 */
class EmpresaFija extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'cliente_id',
        'orden',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'orden' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }
}
