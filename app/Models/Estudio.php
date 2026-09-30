<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

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
        'nombre_comercial',
        'ruc',
        'representante',
        'colegiatura',
        'slogan',
        'correo',
        'telefono',
        'sitio_web',
        'direccion',
        'ciudad',
        'logo_path',
        'sidebar_display',
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
     * URL pública del logotipo o null si no se ha subido.
     */
    public function logoUrl(): ?string
    {
        if (! empty($this->logo_path) && Storage::disk('public')->exists($this->logo_path)) {
            return asset('storage/'.$this->logo_path);
        }

        return null;
    }

    /**
     * Iniciales estilizadas para monograma (fallback cuando no hay imagen).
     */
    public function iniciales(): string
    {
        $nombre = trim($this->nombre_comercial ?: $this->nombre ?: 'Estudio Contable');
        $palabras = explode(' ', $nombre);
        if (count($palabras) >= 2) {
            return strtoupper(substr($palabras[0], 0, 1).substr($palabras[1], 0, 1));
        }

        return strtoupper(substr($nombre, 0, 2));
    }

    /**
     * Nombre comercial preferente o razón social.
     */
    public function nombreParaMostrar(): string
    {
        return $this->nombre_comercial ?: $this->nombre ?: 'Estudio Contable';
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
