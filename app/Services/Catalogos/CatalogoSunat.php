<?php

namespace App\Services\Catalogos;

use App\Enums\MedioPagoTributo;
use App\Enums\RegimenTributario;
use App\Enums\TipoTributoSunat;
use App\Models\AbonoDeuda;
use App\Models\CatalogoMedioPago;
use App\Models\CatalogoTributo;
use App\Models\DeudaTributaria;
use App\Models\LiquidacionPeriodo;
use App\Models\Operacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo del estudio: códigos SUNAT y medios de pago que el administrador mantiene.
 * Los códigos de sistema siguen alimentando la hoja; aquí se cambia el nombre,
 * el régimen de la renta mensual y se agregan códigos nuevos.
 */
class CatalogoSunat
{
    /**
     * @var array<string, string>
     */
    public const ROLES = [
        'igv' => 'IGV 18%',
        'igv_especial' => 'IGV tasa especial',
        'renta_mensual' => 'Renta mensual',
        'renta_regularizacion' => 'Regularización anual',
        'essalud' => 'EsSalud',
        'onp' => 'ONP',
        'afp' => 'AFP',
        'renta_4ta' => 'Retención de 4ta',
        'renta_5ta' => 'Retención de 5ta',
        'otro' => 'Otro tributo',
    ];

    /**
     * @var array<string, array{titulo: string, detalle: string}>
     */
    public const FORMULARIOS = [
        'pdt_621' => ['titulo' => 'PDT 621', 'detalle' => 'IGV y Renta'],
        'pdt_601' => ['titulo' => 'PDT 601', 'detalle' => 'PLAME'],
        'otro' => ['titulo' => 'Otros', 'detalle' => 'Tributos adicionales'],
    ];

    /**
     * @var array<string, string>
     */
    public const REGIMENES_RENTA = [
        'RER' => 'Régimen Especial (RER)',
        'RMT' => 'MYPE Tributario (RMT)',
        'RG' => 'Régimen General',
    ];

    /**
     * @var array<int, array{tributos: Collection<int, CatalogoTributo>, medios: Collection<int, CatalogoMedioPago>}>
     */
    private static array $memorias = [];

    private static ?bool $tablasListas = null;

    public static function olvidar(): void
    {
        self::$memorias = [];
        self::$tablasListas = null;
    }

    public function asegurar(?int $estudioId = null): void
    {
        if (! $this->tablasListas()) {
            return;
        }

        $this->sembrar($estudioId ?? $this->estudioId());
    }

    public function sembrar(int $estudioId): void
    {
        if (! $this->tablasListas()) {
            return;
        }

        $oficiales = $this->tributosOficiales();
        $existentes = CatalogoTributo::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('sistema', true)
            ->count();

        if ($existentes < count($oficiales)) {
            foreach ($oficiales as $fila) {
                CatalogoTributo::withoutGlobalScopes()->firstOrCreate(
                    ['estudio_id' => $estudioId, 'codigo' => $fila['codigo']],
                    [...$fila, 'estudio_id' => $estudioId],
                );
            }
        }

        $medios = $this->mediosOficiales();
        $mediosExistentes = CatalogoMedioPago::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('sistema', true)
            ->count();

        if ($mediosExistentes < count($medios)) {
            foreach ($medios as $fila) {
                CatalogoMedioPago::withoutGlobalScopes()->firstOrCreate(
                    ['estudio_id' => $estudioId, 'codigo' => $fila['codigo']],
                    [...$fila, 'estudio_id' => $estudioId],
                );
            }
        }

        unset(self::$memorias[$estudioId]);
    }

    /**
     * @return Collection<int, CatalogoTributo>
     */
    public function tributos(?int $estudioId = null): Collection
    {
        $estudioId = $estudioId ?? $this->estudioId();
        if (! $this->tablasListas()) {
            return collect();
        }

        $this->asegurar($estudioId);

        return $this->memoria($estudioId)['tributos'];
    }

    /**
     * @return Collection<int, CatalogoMedioPago>
     */
    public function medios(?int $estudioId = null): Collection
    {
        $estudioId = $estudioId ?? $this->estudioId();
        if (! $this->tablasListas()) {
            return collect();
        }

        $this->asegurar($estudioId);

        return $this->memoria($estudioId)['medios'];
    }

    /**
     * @return list<string>
     */
    public function codigosActivos(?int $estudioId = null): array
    {
        if (! $this->tablasListas()) {
            return array_map(fn (TipoTributoSunat $caso): string => $caso->value, TipoTributoSunat::cases());
        }

        return $this->tributos($estudioId)
            ->filter(fn (CatalogoTributo $tributo): bool => $tributo->activo)
            ->map(fn (CatalogoTributo $tributo): string => $tributo->codigo)
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, titulo: string, detalle: string, tributos: list<array{codigo: string, etiqueta: string, nombre: string, nombre_corto: string}>}>
     */
    public function catalogoFormulario(?int $estudioId = null): array
    {
        if (! $this->tablasListas()) {
            return TipoTributoSunat::catalogoFormulario();
        }

        $grupos = [];
        foreach ($this->tributos($estudioId) as $tributo) {
            if (! $tributo->activo) {
                continue;
            }
            $clave = array_key_exists($tributo->formulario, self::FORMULARIOS) ? $tributo->formulario : 'otro';
            $grupos[$clave][] = [
                'codigo' => $tributo->codigo,
                'etiqueta' => $tributo->etiquetaSelector(),
                'nombre' => $tributo->denominacion,
                'nombre_corto' => $tributo->nombre_corto,
            ];
        }

        $bloques = [];
        foreach (self::FORMULARIOS as $clave => $formulario) {
            if (($grupos[$clave] ?? []) === []) {
                continue;
            }
            $bloques[] = [
                'id' => $clave,
                'titulo' => $formulario['titulo'],
                'detalle' => $formulario['detalle'],
                'tributos' => $grupos[$clave],
            ];
        }

        return $bloques;
    }

    /**
     * @return array<string, string>
     */
    public function opcionesTributo(?int $estudioId = null): array
    {
        if (! $this->tablasListas()) {
            return TipoTributoSunat::opciones();
        }

        $opciones = [];
        foreach ($this->tributos($estudioId) as $tributo) {
            if (! $tributo->activo) {
                continue;
            }
            $opciones[$tributo->codigo] = $tributo->codigo.' - '.$tributo->denominacion;
        }

        return $opciones;
    }

    /**
     * @return list<array{codigo: string, denominacion: string}>
     */
    public function mediosParaSelector(?int $estudioId = null): array
    {
        if (! $this->tablasListas()) {
            return array_map(fn (MedioPagoTributo $medio): array => [
                'codigo' => $medio->value,
                'denominacion' => $medio->etiqueta(),
            ], MedioPagoTributo::cases());
        }

        return $this->medios($estudioId)
            ->filter(fn (CatalogoMedioPago $medio): bool => $medio->activo)
            ->map(fn (CatalogoMedioPago $medio): array => [
                'codigo' => $medio->codigo,
                'denominacion' => $medio->denominacion,
            ])
            ->values()
            ->all();
    }

    public function aceptaTributo(string $codigo, ?int $estudioId = null): bool
    {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return false;
        }

        if (! $this->tablasListas()) {
            return TipoTributoSunat::resolver($codigo) !== null;
        }

        return $this->tributos($estudioId)->contains(
            fn (CatalogoTributo $tributo): bool => $tributo->codigo === $codigo && $tributo->activo,
        );
    }

    public function aceptaMedio(?string $codigo, ?int $estudioId = null): bool
    {
        $codigo = strtolower(trim((string) $codigo));
        if ($codigo === '') {
            return false;
        }

        if (! $this->tablasListas()) {
            return MedioPagoTributo::tryFrom($codigo) !== null;
        }

        return $this->medios($estudioId)->contains(
            fn (CatalogoMedioPago $medio): bool => $medio->codigo === $codigo && $medio->activo,
        );
    }

    public function nombre(string $codigo, ?int $estudioId = null, string $default = 'Tributo SUNAT'): string
    {
        $fila = $this->tributo($codigo, $estudioId);

        return $fila?->denominacion ?? TipoTributoSunat::obtenerNombre($codigo, $default);
    }

    public function nombreCorto(string $codigo, ?int $estudioId = null): string
    {
        $fila = $this->tributo($codigo, $estudioId);
        if ($fila instanceof CatalogoTributo) {
            return $fila->nombre_corto;
        }

        return TipoTributoSunat::obtenerNombreCorto($codigo, $codigo);
    }

    public function etiqueta(string $codigo, ?int $estudioId = null): string
    {
        $fila = $this->tributo($codigo, $estudioId);
        if ($fila instanceof CatalogoTributo) {
            return $fila->codigo.' - '.$fila->denominacion;
        }

        return TipoTributoSunat::resolver($codigo)?->etiquetaCompleta() ?? $codigo;
    }

    public function declaracionDe(?string $codigo, ?int $estudioId = null): ?string
    {
        if ($codigo === null || $codigo === '') {
            return null;
        }

        $codigo = match ($codigo) {
            '3041' => '3042',
            '3051' => '3052',
            default => strtoupper($codigo),
        };
        $fila = $this->tributo($codigo, $estudioId);

        return $fila?->formulario ?? TipoTributoSunat::declaracionDe($codigo);
    }

    public function rolDe(string $codigo, ?int $estudioId = null): ?string
    {
        return $this->tributo($codigo, $estudioId)?->rol;
    }

    public function codigoRentaDelRegimen(RegimenTributario|string|null $regimen, ?int $estudioId = null): ?string
    {
        $clave = $this->claveRegimen($regimen);
        if ($clave === 'RUS') {
            return null;
        }

        if ($clave !== null && $this->tablasListas()) {
            $fila = $this->tributos($estudioId)->first(
                fn (CatalogoTributo $tributo): bool => $tributo->activo
                    && $tributo->rol === 'renta_mensual'
                    && in_array($clave, $tributo->regimenes ?? [], true),
            );
            if ($fila instanceof CatalogoTributo) {
                return $fila->codigo;
            }
        }

        return TipoTributoSunat::rentaDelRegimen($regimen)?->value;
    }

    public function codigoDelRol(string $rol, ?int $estudioId = null): ?string
    {
        if ($this->tablasListas()) {
            $fila = $this->tributos($estudioId)->first(
                fn (CatalogoTributo $tributo): bool => $tributo->rol === $rol && $tributo->activo,
            );
            if ($fila instanceof CatalogoTributo) {
                return $fila->codigo;
            }
        }

        return match ($rol) {
            'igv' => TipoTributoSunat::Igv18->value,
            'igv_especial' => TipoTributoSunat::Igv105->value,
            'essalud' => TipoTributoSunat::EsSalud->value,
            'onp' => TipoTributoSunat::Onp->value,
            'afp' => TipoTributoSunat::Afp->value,
            'renta_4ta' => TipoTributoSunat::RentaCuarta->value,
            'renta_5ta' => TipoTributoSunat::RentaQuinta->value,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    public function codigosDeLiquidacion(?int $estudioId = null): array
    {
        $roles = ['igv', 'igv_especial', 'renta_mensual', 'renta_regularizacion', 'essalud', 'onp', 'afp', 'renta_4ta', 'renta_5ta'];
        $codigos = ['3011', 'AFP', TipoTributoSunat::Afp->value];

        if ($this->tablasListas()) {
            foreach ($this->tributos($estudioId) as $tributo) {
                if (in_array($tributo->rol, $roles, true)) {
                    $codigos[] = $tributo->codigo;
                }
            }
        }

        return array_values(array_unique($codigos));
    }

    public function etiquetaMedio(?string $codigo, ?int $estudioId = null): string
    {
        $codigo = strtolower(trim((string) $codigo));
        if ($codigo === '') {
            return '';
        }

        if ($this->tablasListas()) {
            $medio = $this->medios($estudioId)->first(
                fn (CatalogoMedioPago $fila): bool => $fila->codigo === $codigo,
            );
            if ($medio instanceof CatalogoMedioPago) {
                return $medio->denominacion;
            }
        }

        return MedioPagoTributo::tryFrom($codigo)?->etiqueta() ?? $codigo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearTributo(array $datos, ?int $estudioId = null): CatalogoTributo
    {
        $estudioId = $estudioId ?? $this->estudioId();
        $this->asegurar($estudioId);
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $this->validarCodigoTributo($codigo);

        if ($this->tributos($estudioId)->contains(fn (CatalogoTributo $fila): bool => $fila->codigo === $codigo)) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código ya está en el catálogo.',
            ]);
        }

        $rol = (string) ($datos['rol'] ?? 'otro');
        $formulario = (string) ($datos['formulario'] ?? 'otro');
        $this->validarRolYFormulario($rol, $formulario);
        $denominacion = $this->denominacion((string) ($datos['denominacion'] ?? ''));
        $regimenes = $rol === 'renta_mensual' ? $this->regimenes($datos['regimenes'] ?? []) : [];

        $tributo = CatalogoTributo::withoutGlobalScopes()->create([
            'estudio_id' => $estudioId,
            'codigo' => $codigo,
            'denominacion' => $denominacion,
            'nombre_corto' => $this->nombreCortoIngresado($datos['nombre_corto'] ?? null, $denominacion),
            'formulario' => $formulario,
            'rol' => $rol,
            'regimenes' => $regimenes,
            'sistema' => false,
            'activo' => true,
            'orden' => ((int) $this->tributos($estudioId)->max('orden')) + 1,
        ]);
        $this->reservarRegimenes($tributo, $regimenes);
        unset(self::$memorias[$estudioId]);

        return $tributo;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarTributo(CatalogoTributo $tributo, array $datos): void
    {
        $denominacion = $this->denominacion((string) ($datos['denominacion'] ?? ''));
        $rol = $tributo->sistema ? $tributo->rol : (string) ($datos['rol'] ?? $tributo->rol);
        $formulario = $tributo->sistema ? $tributo->formulario : (string) ($datos['formulario'] ?? $tributo->formulario);
        if (! $tributo->sistema) {
            $this->validarRolYFormulario($rol, $formulario);
        }

        $codigo = $this->codigoTributo((string) ($datos['codigo'] ?? $tributo->codigo));
        $regimenes = $rol === 'renta_mensual' ? $this->regimenes($datos['regimenes'] ?? []) : [];

        DB::transaction(function () use ($tributo, $datos, $denominacion, $rol, $formulario, $codigo, $regimenes): void {
            if ($codigo !== $tributo->codigo) {
                $this->asegurarCodigoLibre($codigo, (int) $tributo->estudio_id, $tributo->id);
                $this->propagarCodigo($tributo->codigo, $codigo, (int) $tributo->estudio_id);
            }

            $tributo->fill([
                'codigo' => $codigo,
                'denominacion' => $denominacion,
                'nombre_corto' => $this->nombreCortoIngresado($datos['nombre_corto'] ?? null, $denominacion),
                'formulario' => $formulario,
                'rol' => $rol,
                'regimenes' => $regimenes,
                'activo' => $tributo->sistema ? true : (bool) ($datos['activo'] ?? false),
            ]);
            $tributo->save();
            $this->reservarRegimenes($tributo, $regimenes);
        });

        unset(self::$memorias[(int) $tributo->estudio_id]);
    }

    public function eliminarTributo(CatalogoTributo $tributo): void
    {
        $estudioId = (int) $tributo->estudio_id;
        if ($this->tributoEnUso($tributo)) {
            throw ValidationException::withMessages([
                'codigo' => "Hay deudas o liquidaciones con el código {$tributo->codigo}. No se eliminó para no perder ese control.",
            ]);
        }

        $tributo->delete();
        unset(self::$memorias[$estudioId]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearMedio(array $datos, ?int $estudioId = null): CatalogoMedioPago
    {
        $estudioId = $estudioId ?? $this->estudioId();
        $this->asegurar($estudioId);
        $codigo = $this->codigoMedio((string) ($datos['codigo'] ?? ''));

        if ($this->medios($estudioId)->contains(fn (CatalogoMedioPago $medio): bool => $medio->codigo === $codigo)) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código de medio de pago ya existe.',
            ]);
        }

        $medio = CatalogoMedioPago::withoutGlobalScopes()->create([
            'estudio_id' => $estudioId,
            'codigo' => $codigo,
            'denominacion' => $this->denominacion((string) ($datos['denominacion'] ?? ''), 120),
            'sistema' => false,
            'activo' => true,
            'orden' => ((int) $this->medios($estudioId)->max('orden')) + 1,
        ]);
        unset(self::$memorias[$estudioId]);

        return $medio;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarMedio(CatalogoMedioPago $medio, array $datos): void
    {
        $codigo = $this->codigoMedio((string) ($datos['codigo'] ?? $medio->codigo));

        DB::transaction(function () use ($medio, $datos, $codigo): void {
            if ($codigo !== $medio->codigo) {
                $ocupado = CatalogoMedioPago::withoutGlobalScopes()
                    ->where('estudio_id', $medio->estudio_id)
                    ->where('codigo', $codigo)
                    ->whereKeyNot($medio->id)
                    ->exists();
                if ($ocupado) {
                    throw ValidationException::withMessages([
                        'codigo' => 'Ese código de medio de pago ya existe.',
                    ]);
                }

                AbonoDeuda::withoutGlobalScopes()
                    ->where('estudio_id', $medio->estudio_id)
                    ->where('medio_pago', $medio->codigo)
                    ->update(['medio_pago' => $codigo]);
            }

            $medio->fill([
                'codigo' => $codigo,
                'denominacion' => $this->denominacion((string) ($datos['denominacion'] ?? ''), 120),
                'activo' => (bool) ($datos['activo'] ?? false),
            ]);
            $medio->save();
        });

        unset(self::$memorias[(int) $medio->estudio_id]);
    }

    public function eliminarMedio(CatalogoMedioPago $medio): void
    {
        $estudioId = (int) $medio->estudio_id;
        $usado = AbonoDeuda::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('medio_pago', $medio->codigo)
            ->exists();

        if ($usado) {
            throw ValidationException::withMessages([
                'codigo' => 'Hay pagos registrados con este medio. No se eliminó para no perder ese historial.',
            ]);
        }

        $medio->delete();
        unset(self::$memorias[$estudioId]);
    }

    /**
     * @return array{tributos: Collection<int, CatalogoTributo>, medios: Collection<int, CatalogoMedioPago>}
     */
    private function memoria(int $estudioId): array
    {
        if (! isset(self::$memorias[$estudioId])) {
            self::$memorias[$estudioId] = [
                'tributos' => CatalogoTributo::withoutGlobalScopes()
                    ->where('estudio_id', $estudioId)
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->get(),
                'medios' => CatalogoMedioPago::withoutGlobalScopes()
                    ->where('estudio_id', $estudioId)
                    ->orderBy('orden')
                    ->orderBy('id')
                    ->get(),
            ];
        }

        return self::$memorias[$estudioId];
    }

    private function tributo(string $codigo, ?int $estudioId): ?CatalogoTributo
    {
        $codigo = match ($codigo) {
            '3041' => '3042',
            '3051' => '3052',
            default => strtoupper(trim($codigo)),
        };
        if ($codigo === '' || ! $this->tablasListas()) {
            return null;
        }

        $fila = $this->tributos($estudioId)->first(
            fn (CatalogoTributo $tributo): bool => $tributo->codigo === $codigo,
        );

        return $fila instanceof CatalogoTributo ? $fila : null;
    }

    private function tablasListas(): bool
    {
        if (self::$tablasListas === null) {
            self::$tablasListas = Schema::hasTable('catalogo_tributos') && Schema::hasTable('catalogo_medios_pago');
        }

        return self::$tablasListas;
    }

    private function estudioId(): int
    {
        return CatalogoTributo::estudioActualId();
    }

    /**
     * @return list<array{codigo: string, denominacion: string, nombre_corto: string, formulario: string, rol: string, regimenes: list<string>, sistema: bool, activo: bool, orden: int}>
     */
    private function tributosOficiales(): array
    {
        $regimenes = [
            '3111' => ['RER'],
            '3121' => ['RMT'],
            '3031' => ['RG'],
        ];
        $roles = [
            TipoTributoSunat::Igv18->value => 'igv',
            TipoTributoSunat::Igv105->value => 'igv_especial',
            TipoTributoSunat::RentaEspecial->value => 'renta_mensual',
            TipoTributoSunat::RentaMype->value => 'renta_mensual',
            TipoTributoSunat::RentaGeneral->value => 'renta_mensual',
            TipoTributoSunat::RentaRegularizacion->value => 'renta_regularizacion',
            TipoTributoSunat::RentaCuarta->value => 'renta_4ta',
            TipoTributoSunat::RentaQuinta->value => 'renta_5ta',
            TipoTributoSunat::EsSalud->value => 'essalud',
            TipoTributoSunat::Onp->value => 'onp',
            TipoTributoSunat::Afp->value => 'afp',
        ];
        $ordenTrabajo = [
            '1011' => 0,
            '1016' => 1,
            '3111' => 2,
            '3121' => 3,
            '3031' => 4,
            '3081' => 5,
            '5210' => 6,
            '5310' => 7,
            '6098' => 8,
            '3052' => 9,
            '3042' => 10,
        ];
        $filas = [];
        foreach (TipoTributoSunat::cases() as $caso) {
            $filas[] = [
                'codigo' => $caso->value,
                'denominacion' => $caso->descripcion(),
                'nombre_corto' => $caso->nombreCorto(),
                'formulario' => $caso->declaracion(),
                'rol' => $roles[$caso->value],
                'regimenes' => $regimenes[$caso->value] ?? [],
                'sistema' => true,
                'activo' => true,
                'orden' => $ordenTrabajo[$caso->value],
            ];
        }

        return $filas;
    }

    /**
     * @return list<array{codigo: string, denominacion: string, sistema: bool, activo: bool, orden: int}>
     */
    private function mediosOficiales(): array
    {
        $filas = [];
        foreach (MedioPagoTributo::cases() as $orden => $medio) {
            $filas[] = [
                'codigo' => $medio->value,
                'denominacion' => $medio->etiqueta(),
                'sistema' => true,
                'activo' => true,
                'orden' => $orden,
            ];
        }

        return $filas;
    }

    private function claveRegimen(RegimenTributario|string|null $regimen): ?string
    {
        if ($regimen === null) {
            return null;
        }

        $valor = strtoupper(trim($regimen instanceof RegimenTributario ? $regimen->value : (string) $regimen));

        return match ($valor) {
            'RER' => 'RER',
            'RG', 'GENERAL' => 'RG',
            'RUS', 'NRUS' => 'RUS',
            'RMT', 'MYPE' => 'RMT',
            default => null,
        };
    }

    private function codigoTributo(string $codigo): string
    {
        $codigo = trim($codigo);
        $this->validarCodigoTributo($codigo);

        return $codigo;
    }

    private function asegurarCodigoLibre(string $codigo, int $estudioId, int $exceptoId): void
    {
        $ocupado = CatalogoTributo::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('codigo', $codigo)
            ->whereKeyNot($exceptoId)
            ->exists();

        if ($ocupado) {
            throw ValidationException::withMessages([
                'codigo' => 'Ese código ya está en el catálogo.',
            ]);
        }
    }

    private function propagarCodigo(string $anterior, string $nuevo, int $estudioId): void
    {
        if ($anterior === $nuevo) {
            return;
        }

        DeudaTributaria::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('tributo_codigo', $anterior)
            ->update(['tributo_codigo' => $nuevo]);

        Operacion::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('tributo_codigo', $anterior)
            ->update(['tributo_codigo' => $nuevo]);

        LiquidacionPeriodo::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('renta_tributo_codigo', $anterior)
            ->update(['renta_tributo_codigo' => $nuevo]);
    }

    private function tributoEnUso(CatalogoTributo $tributo): bool
    {
        $estudioId = (int) $tributo->estudio_id;

        return DeudaTributaria::withoutGlobalScopes()
            ->where('estudio_id', $estudioId)
            ->where('tributo_codigo', $tributo->codigo)
            ->exists()
            || Operacion::withoutGlobalScopes()
                ->where('estudio_id', $estudioId)
                ->where('tributo_codigo', $tributo->codigo)
                ->exists()
            || LiquidacionPeriodo::withoutGlobalScopes()
                ->where('estudio_id', $estudioId)
                ->where('renta_tributo_codigo', $tributo->codigo)
                ->exists();
    }

    private function validarCodigoTributo(string $codigo): void
    {
        if (preg_match('/^\d{4}$/', $codigo) !== 1) {
            throw ValidationException::withMessages([
                'codigo' => 'El código del tributo tiene que ser de 4 números.',
            ]);
        }
    }

    private function validarRolYFormulario(string $rol, string $formulario): void
    {
        if (! array_key_exists($rol, self::ROLES) || ! array_key_exists($formulario, self::FORMULARIOS)) {
            throw ValidationException::withMessages([
                'rol' => 'Seleccione el formulario y el tipo de tributo.',
            ]);
        }
    }

    private function denominacion(string $texto, int $maximo = 180): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? '');
        if ($texto === '') {
            throw ValidationException::withMessages([
                'denominacion' => 'Escriba la denominación.',
            ]);
        }

        return mb_substr($texto, 0, $maximo);
    }

    private function nombreCortoIngresado(mixed $texto, string $denominacion): string
    {
        $corto = trim((string) $texto);

        return mb_substr($corto !== '' ? $corto : $denominacion, 0, 80);
    }

    /**
     * @return list<string>
     */
    private function regimenes(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $regimenes = [];
        foreach ($valor as $regimen) {
            $regimen = strtoupper(trim((string) $regimen));
            if (array_key_exists($regimen, self::REGIMENES_RENTA)) {
                $regimenes[] = $regimen;
            }
        }

        return array_values(array_unique($regimenes));
    }

    /**
     * @param  list<string>  $regimenes
     */
    private function reservarRegimenes(CatalogoTributo $tributo, array $regimenes): void
    {
        if ($regimenes === []) {
            return;
        }

        $hermanos = CatalogoTributo::withoutGlobalScopes()
            ->where('estudio_id', $tributo->estudio_id)
            ->where('rol', 'renta_mensual')
            ->whereKeyNot($tributo->id)
            ->get();

        foreach ($hermanos as $hermano) {
            $quedan = array_values(array_diff($hermano->regimenes ?? [], $regimenes));
            if ($quedan !== array_values($hermano->regimenes ?? [])) {
                $hermano->regimenes = $quedan;
                $hermano->save();
            }
        }
    }

    private function codigoMedio(string $codigo): string
    {
        $codigo = strtolower(trim($codigo));
        if (preg_match('/^[a-z0-9][a-z0-9_\-]{0,39}$/', $codigo) !== 1) {
            throw ValidationException::withMessages([
                'codigo' => 'El código del medio de pago usa letras, números o guion, sin espacios.',
            ]);
        }

        return $codigo;
    }
}
