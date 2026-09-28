<?php

namespace App\Models;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Traits\BelongsToEstudio;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

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
            'estado' => EstadoCliente::class,
            'regimen_tributario' => RegimenTributario::class,
            'fecha_baja' => 'date',
            'es_buen_contribuyente' => 'boolean',
            'sunat_client_secret' => 'encrypted',
            'fecha_sincronizacion_sunat' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Mutador de compatibilidad para el atributo estado.
     * Normaliza los booleanos heredados (true/false) y las cadenas con distinta
     * capitalizacion antes de que el cast del enum los reciba.
     */
    public function setEstadoAttribute(mixed $value): void
    {
        $this->attributes['estado'] = EstadoCliente::desde($value)->value;
    }

    /**
     * Scope para filtrar clientes activos en el estudio contable.
     */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('estado', EstadoCliente::Activo);
    }

    /**
     * Scope para filtrar clientes suspendidos en el estudio contable.
     */
    public function scopeSuspendidos(Builder $query): Builder
    {
        return $query->where('estado', EstadoCliente::Suspendido);
    }

    /**
     * Scope para filtrar clientes retirados o dados de baja en el estudio contable.
     */
    public function scopeRetirados(Builder $query): Builder
    {
        return $query->where('estado', EstadoCliente::Retirado);
    }

    /**
     * Clientes que el estudio todavía atiende durante el ejercicio.
     * Incluye activos, suspendidos y retirados cuyo cese cae dentro del año.
     */
    public function scopeVigentesEnEjercicio(Builder $query, int $anio): Builder
    {
        $inicio = sprintf('%04d-01', $anio);

        return $query->where(function (Builder $query) use ($inicio): void {
            $query->where('estado', EstadoCliente::Activo)
                ->orWhere('estado', EstadoCliente::Suspendido)
                ->orWhere(function (Builder $retirados) use ($inicio): void {
                    $retirados->where('estado', EstadoCliente::Retirado)
                        ->whereNotNull('mes_baja')
                        ->where('mes_baja', '>=', $inicio);
                });
        });
    }

    /**
     * Determina si el cliente está activo en el estudio contable.
     */
    public function isActivo(): bool
    {
        return $this->estado === EstadoCliente::Activo;
    }

    /**
     * Determina si el cliente se encuentra suspendido en el estudio contable.
     */
    public function isSuspendido(): bool
    {
        return $this->estado === EstadoCliente::Suspendido;
    }

    /**
     * Determina si el cliente ya no pertenece al estudio (baja o cambio de contador).
     */
    public function isRetirado(): bool
    {
        return $this->estado === EstadoCliente::Retirado;
    }

    /**
     * Periodo YYYY-MM desde el cual el cliente ya no se declara en el estudio.
     */
    public function mesDeCese(): ?string
    {
        if (! $this->isRetirado()) {
            return null;
        }

        if ($this->mes_baja) {
            return $this->mes_baja;
        }

        return $this->fecha_baja?->format('Y-m');
    }

    /**
     * Indica si el periodo MM-AAAA sigue siendo exigible para el estudio.
     */
    public function rindeEnPeriodo(string $periodo): bool
    {
        $cese = $this->mesDeCese();

        if ($cese === null) {
            return true;
        }

        if (preg_match('/^(\d{2})-(\d{4})$/', $periodo, $coincidencia) !== 1) {
            return false;
        }

        return ($coincidencia[2].'-'.$coincidencia[1]) < $cese;
    }

    /**
     * Determina si el cliente debe rendir balances mensuales, lo que depende
     * exclusivamente de su régimen tributario.
     */
    public function llevaBalances(): bool
    {
        return $this->regimen_tributario?->exigeLibrosElectronicos() ?? false;
    }

    /**
     * Accesor para etiqueta amigable del estado en el estudio.
     */
    public function getEstadoEstudioLabelAttribute(): string
    {
        return $this->estado->etiqueta();
    }

    /**
     * El método booted registra hooks del ciclo de vida del modelo.
     */
    protected static function booted(): void
    {
        static::creating(function (Cliente $cliente) {
            if (empty($cliente->numero)) {
                $cliente->numero = self::proximoNumeroDisponible($cliente->estudio_id);
            }
        });
    }

    /**
     * Regla de validación para los identificadores de cliente que llegan en el
     * cuerpo de una petición, donde no hay enlace de modelo que aplique los
     * scopes automáticamente.
     *
     * Exige que el cliente pertenezca al estudio en curso y, cuando quien opera
     * es un asistente, que además figure en su cartera asignada. Sin esta
     * restricción un `exists:clientes,id` corriente acepta el identificador de
     * cualquier cliente de la instalación.
     */
    public static function reglaPerteneceACartera(): Exists
    {
        $user = Auth::user();

        return Rule::exists('clientes', 'id')->where(function (QueryBuilder $query) use ($user) {
            $query->where('estudio_id', self::estudioActualId())->whereNull('deleted_at');

            if ($user?->isAsistente()) {
                $query->whereIn('id', function (QueryBuilder $asignados) use ($user) {
                    $asignados->select('cliente_id')
                        ->from('cliente_user')
                        ->where('user_id', $user->getKey());
                });
            }
        });
    }

    /**
     * Calcula el menor número correlativo positivo disponible dentro de un
     * estudio, reutilizando los huecos que dejaron los clientes eliminados.
     *
     * La consulta prescinde de los scopes globales y filtra el estudio de forma
     * explícita: cuando no hay sesión (comandos programados, colas, importación
     * masiva) el scope de inquilino no se aplica y la numeración se contaminaría
     * con los clientes de los demás estudios.
     */
    public static function proximoNumeroDisponible(?int $estudioId = null): int
    {
        $ocupados = self::withoutGlobalScopes()
            ->where('estudio_id', $estudioId ?? self::estudioActualId())
            ->pluck('numero')
            ->filter()
            ->flip();

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
