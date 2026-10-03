<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /**
     * Roles que puede tener un miembro del estudio.
     *
     * @var list<string>
     */
    public const ROLES = ['admin', 'contador', 'asistente'];

    /**
     * Variantes de la marca que se muestra sobre la foto.
     *
     * @var list<string>
     */
    public const INSIGNIAS = ['hombre', 'mujer', 'mixto'];

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
        'dni',
        'cargo',
        'password',
        'password_plain',
        'rol',
        'insignia',
        'activo',
        'telefono',
        'fecha_nacimiento',
        'fecha_ingreso',
        'fecha_cese',
        'direccion',
        'foto_perfil',
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
            'password_plain' => 'encrypted',
            'activo' => 'boolean',
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'fecha_cese' => 'date',
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
     * Empresas fijas del acceso rápido, de la más reciente a la más antigua.
     */
    public function empresasFijas(): HasMany
    {
        return $this->hasMany(EmpresaFija::class)->orderByDesc('orden');
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

    /**
     * Determina si el usuario tiene rol de contador.
     */
    public function isContador(): bool
    {
        return $this->rol === 'contador';
    }

    /**
     * El administrador ve toda la cartera del estudio. Contador y asistente
     * operan únicamente los clientes que se les asignan.
     */
    public function operaCarteraAsignada(): bool
    {
        return ! $this->isAdmin();
    }

    /**
     * Nombre corto del rol, tal como se muestra en la cuenta y en el equipo.
     */
    public function etiquetaRol(): string
    {
        return match ($this->rol) {
            'admin' => 'Administrador',
            'contador' => 'Contador',
            default => 'Asistente contable',
        };
    }

    /**
     * Tres marcas por rol: hombre, mujer y una opción para cualquiera.
     * El contador usa personas de traje; la de hombre lleva corbata.
     *
     * @return array<string, array<string, array{emoji: string, etiqueta: string, detalle: string}>>
     */
    public static function catalogoInsignias(): array
    {
        return [
            'admin' => [
                'hombre' => ['emoji' => '🤴', 'etiqueta' => 'Hombre', 'detalle' => 'Para él'],
                'mujer' => ['emoji' => '👸', 'etiqueta' => 'Mujer', 'detalle' => 'Para ella'],
                'mixto' => ['emoji' => '👑', 'etiqueta' => 'Mixto', 'detalle' => 'Para cualquiera'],
            ],
            'contador' => [
                'hombre' => ['emoji' => '👨‍💼', 'etiqueta' => 'Hombre', 'detalle' => 'Persona con corbata'],
                'mujer' => ['emoji' => '👩‍💼', 'etiqueta' => 'Mujer', 'detalle' => 'Persona de traje'],
                'mixto' => ['emoji' => '🤵', 'etiqueta' => 'Mixto', 'detalle' => 'Para cualquiera'],
            ],
            'asistente' => [
                'hombre' => ['emoji' => '👷‍♂️', 'etiqueta' => 'Hombre', 'detalle' => 'Para él'],
                'mujer' => ['emoji' => '👷‍♀️', 'etiqueta' => 'Mujer', 'detalle' => 'Para ella'],
                'mixto' => ['emoji' => '👷', 'etiqueta' => 'Mixto', 'detalle' => 'Para cualquiera'],
            ],
        ];
    }

    /**
     * Mapa rol → variante → emoji, para la vista previa en el formulario.
     *
     * @return array<string, array<string, string>>
     */
    public static function emojisPorRol(): array
    {
        $mapa = [];

        foreach (self::catalogoInsignias() as $rol => $opciones) {
            foreach ($opciones as $clave => $opcion) {
                $mapa[$rol][$clave] = $opcion['emoji'];
            }
        }

        return $mapa;
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function detallesPorRol(): array
    {
        $mapa = [];

        foreach (self::catalogoInsignias() as $rol => $opciones) {
            foreach ($opciones as $clave => $opcion) {
                $mapa[$rol][$clave] = $opcion['detalle'];
            }
        }

        return $mapa;
    }

    public static function emojiDe(string $rol, string $variante): string
    {
        $catalogo = self::catalogoInsignias();
        $rol = array_key_exists($rol, $catalogo) ? $rol : 'asistente';
        $variante = in_array($variante, self::INSIGNIAS, true) ? $variante : 'mixto';

        return $catalogo[$rol][$variante]['emoji'];
    }

    /**
     * Variante guardada, o la mixta cuando el valor no es una de las tres.
     */
    public function insigniaClave(): string
    {
        return in_array($this->insignia, self::INSIGNIAS, true) ? $this->insignia : 'mixto';
    }

    public function emojiInsignia(): string
    {
        return self::emojiDe((string) $this->rol, $this->insigniaClave());
    }

    /**
     * Determina si hoy es el cumpleaños del usuario.
     */
    public function esCumpleanosHoy(): bool
    {
        if (! $this->fecha_nacimiento) {
            return false;
        }

        return $this->fecha_nacimiento->format('m-d') === now()->format('m-d');
    }

    /**
     * Devuelve el cargo registrado o una denominación por defecto según su rol.
     */
    public function cargoParaMostrar(): string
    {
        if (! empty($this->cargo)) {
            return $this->cargo;
        }

        return match ($this->rol) {
            'admin' => 'Administrador General',
            'contador' => 'Contador',
            default => 'Asistente Contable',
        };
    }

    /**
     * Obtiene las iniciales del usuario (ej. 'ST' para Sebastián Taya, 'MS' para Maria Soledad).
     */
    public function iniciales(): string
    {
        $nombre = trim($this->name ?? '');
        if ($nombre === '') {
            return 'US';
        }

        $palabras = array_values(array_filter(preg_split('/\s+/', $nombre)));

        if (count($palabras) >= 2) {
            return mb_strtoupper(mb_substr($palabras[0], 0, 1).mb_substr($palabras[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($nombre, 0, 2));
    }

    /**
     * URL pública de la foto de perfil o null si no se ha subido o no existe en storage.
     */
    public function fotoPerfilUrl(): ?string
    {
        if (! empty($this->foto_perfil) && Storage::disk('public')->exists($this->foto_perfil)) {
            return asset('storage/'.$this->foto_perfil);
        }

        return null;
    }

    /**
     * Alias de conveniencia para la foto de perfil del usuario.
     */
    public function avatarUrl(): ?string
    {
        return $this->fotoPerfilUrl();
    }

    /**
     * Envía la notificación para el restablecimiento de contraseña.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
