<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Credencial extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    public const ENLACE_AFP = 'https://www.afpnet.com.pe/';

    public const ENLACE_BN = 'https://bancaporinternet.bn.com.pe/BNWeb/Inicio';

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
        'url_afp',
        'url_bn',
        'banco_1_nombre',
        'banco_1_usuario',
        'banco_1_clave',
        'banco_1_notas',
        'banco_1_url',
        'banco_2_nombre',
        'banco_2_usuario',
        'banco_2_clave',
        'banco_2_notas',
        'banco_2_url',
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

    public function enlaceAfp(): string
    {
        return $this->enlaceHttp($this->url_afp) ?? self::ENLACE_AFP;
    }

    public function enlaceBn(): string
    {
        return $this->enlaceHttp($this->url_bn) ?? self::ENLACE_BN;
    }

    public function enlaceBanco1(): ?string
    {
        return $this->enlaceHttp($this->banco_1_url);
    }

    public function enlaceBanco2(): ?string
    {
        return $this->enlaceHttp($this->banco_2_url);
    }

    private function enlaceHttp(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || preg_match('#\Ahttps?://#i', $url) !== 1) {
            return null;
        }

        return $url;
    }
}
