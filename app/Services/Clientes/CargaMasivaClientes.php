<?php

namespace App\Services\Clientes;

use App\Enums\RegimenTributario;
use App\Models\Cliente;
use App\Models\Credencial;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Alta masiva de clientes. Cada fila se valida como el alta manual:
 * módulo 11, régimen, padrón SUNAT y el primer número correlativo libre.
 * Bancos, AFP y detracciones (salvo el enlace) se completan después en la ficha.
 */
class CargaMasivaClientes
{
    private const MAXIMO_FILAS = 500;

    public function __construct(private ValidacionRuc $validacion) {}

    public function plantilla(): string
    {
        return (new LibroCargaMasivaClientes)->binario();
    }

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     errores: list<array{fila: int|null, mensaje: string}>
     * }
     */
    public function leer(string $contenido): array
    {
        $contenido = $this->utf8($contenido);
        $lineas = preg_split("/\r\n|\n|\r/", $contenido);
        if ($lineas === false) {
            return $this->vacio('La plantilla está vacía.');
        }

        $utiles = [];
        foreach ($lineas as $indice => $linea) {
            $limpia = trim($linea);
            if ($limpia === '' || str_starts_with($limpia, '#')) {
                continue;
            }
            $utiles[] = [
                'numero' => $indice + 1,
                'texto' => $linea,
            ];
        }

        if ($utiles === []) {
            return $this->vacio('La plantilla está vacía.');
        }

        $delimitador = $this->delimitador($utiles[0]['texto']);
        $tabla = [];
        foreach ($utiles as $registro) {
            $tabla[] = [
                'numero' => $registro['numero'],
                'celdas' => str_getcsv($registro['texto'], $delimitador, '"', '\\'),
            ];
        }

        if (count($tabla) - 1 > self::MAXIMO_FILAS) {
            return $this->vacio('La plantilla supera los 500 clientes. Divídala en grupos.');
        }

        $mapa = $this->mapa($tabla[0]['celdas']);
        if ($mapa['errores'] !== []) {
            return ['filas' => [], 'errores' => $mapa['errores']];
        }

        $filas = [];
        foreach (array_slice($tabla, 1) as $registro) {
            $leida = $this->fila($registro['numero'], $registro['celdas'], $mapa['columnas']);
            if ($leida !== null) {
                $filas[] = $leida;
            }
        }

        if ($filas === []) {
            return $this->vacio('La plantilla no tiene clientes para cargar.');
        }

        $this->marcarRepetidos($filas);
        $this->marcarYaRegistrados($filas);

        return ['filas' => $filas, 'errores' => []];
    }

    /**
     * @return array<string, mixed>
     */
    public function consultar(string $rucCrudo): array
    {
        $normalizado = $this->normalizarRuc($rucCrudo);
        if ($normalizado['error'] !== null || $normalizado['ruc'] === null) {
            return $this->observado($normalizado['error'] ?? 'Indique el RUC.', false);
        }

        $ruc = $normalizado['ruc'];
        $formato = RucSunat::mensajeSiInvalido($ruc);
        if ($formato !== null) {
            return $this->observado($formato, false, $ruc);
        }

        $enPapelera = Cliente::onlyTrashed()->where('ruc', $ruc)->first();
        if ($enPapelera) {
            return $this->observado(
                "Este RUC está en la papelera ({$enPapelera->razon_social}). Restaure ese cliente o elimínelo de forma definitiva antes de volver a cargarlo.",
                false,
                $ruc,
            );
        }

        $evaluacion = $this->validacion->evaluar($ruc, true);
        $cuerpo = $evaluacion['cuerpo'];
        if (($cuerpo['valid'] ?? false) !== true) {
            return $this->observado((string) ($cuerpo['message'] ?? 'El RUC no es válido.'), false, $ruc);
        }

        if (($cuerpo['duplicate'] ?? false) === true) {
            return $this->observado((string) $cuerpo['message'], false, $ruc, $cuerpo);
        }

        $razon = trim((string) ($cuerpo['razon_social'] ?? ''));
        if ($razon === '') {
            $disponibilidad = (string) ($cuerpo['disponibilidad'] ?? '');
            $mensaje = $disponibilidad === 'sin_nombre' || $disponibilidad === 'no_figura'
                ? 'El padrón no encontró la razón social de este RUC. Revise el número o pulse Reintentar consultas. No se registra sin ese dato oficial.'
                : 'El padrón de SUNAT no respondió para este RUC. Suele ser un corte de un momento: pulse Reintentar consultas. El número sigue pendiente y no se registró.';

            return $this->observado($mensaje, true, $ruc);
        }

        return [
            'lista' => true,
            'reintentable' => false,
            'mensaje' => (string) ($cuerpo['message'] ?? 'RUC verificado ante SUNAT.'),
            'ruc' => $ruc,
            'razon_social' => $razon,
            'estado_sunat' => $cuerpo['estado_sunat'] ?? null,
            'condicion_sunat' => $cuerpo['condicion_sunat'] ?? null,
            'tipo_persona' => $cuerpo['tipo_persona'] ?? RucSunat::tipoPersona($ruc),
            'direccion_sunat' => $cuerpo['direccion_sunat'] ?? null,
            'ubigeo' => $cuerpo['ubigeo'] ?? null,
            'distrito' => $cuerpo['distrito'] ?? null,
            'provincia' => $cuerpo['provincia'] ?? null,
            'departamento' => $cuerpo['departamento'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    public function registrar(array $datos): array
    {
        $regimen = $this->canonRegimen((string) ($datos['regimen'] ?? ''));
        if ($regimen === null) {
            return $this->rechazo('El régimen no es válido. Use NRUS, RER, RMT o General.', false);
        }

        $enlace = $this->rechazoDeEnlace($datos);
        if ($enlace !== null) {
            return $enlace;
        }

        $correo = $this->limpiar($datos['correo'] ?? null);
        if ($correo !== null && Validator::make(['correo' => $correo], ['correo' => ['email', 'max:255']])->fails()) {
            return $this->rechazo('El correo no es válido.', false);
        }

        $telefono = $this->limpiar($datos['telefono'] ?? null);
        if ($telefono !== null && mb_strlen($telefono) > 20) {
            return $this->rechazo('El teléfono no puede pasar de 20 caracteres.', false);
        }

        $consulta = $this->consultar((string) ($datos['ruc'] ?? ''));
        if (($consulta['lista'] ?? false) !== true) {
            return [
                'registrado' => false,
                'reintentable' => (bool) ($consulta['reintentable'] ?? false),
                'mensaje' => (string) ($consulta['mensaje'] ?? 'No se pudo validar el RUC.'),
            ];
        }

        $usuarioSol = $this->limpiar($datos['usuario_sol'] ?? null);
        if ($usuarioSol !== null) {
            $usuarioSol = mb_strtoupper($usuarioSol);
        }

        $estado = $this->limpiar($consulta['estado_sunat'] ?? null);
        $atributos = [
            'razon_social' => (string) $consulta['razon_social'],
            'ruc' => (string) $consulta['ruc'],
            'regimen_tributario' => $regimen,
            'estado_contribuyente' => $estado,
            'condicion_domicilio' => $this->limpiar($consulta['condicion_sunat'] ?? null),
            'tipo_contribuyente' => $this->limpiar($consulta['tipo_persona'] ?? null),
            'direccion_fiscal' => $this->limpiar($consulta['direccion_sunat'] ?? null),
            'ubigeo' => $this->limpiar($consulta['ubigeo'] ?? null),
            'distrito' => $this->limpiar($consulta['distrito'] ?? null),
            'provincia' => $this->limpiar($consulta['provincia'] ?? null),
            'departamento' => $this->limpiar($consulta['departamento'] ?? null),
            'fecha_sincronizacion_sunat' => $estado !== null ? now() : null,
            'correo' => $correo,
            'telefono' => $telefono,
            'es_buen_contribuyente' => false,
            'estado' => 'activo',
            'sunat_client_id' => $this->limpiar($datos['sunat_client_id'] ?? null),
            'sunat_client_secret' => $this->limpiar($datos['sunat_client_secret'] ?? null),
        ];

        $credencial = [
            'usuario_sol' => $usuarioSol,
            'clave_sol' => $this->limpiar($datos['clave_sol'] ?? null),
            'url_afp' => Credencial::enlaceSiDifiereDelOficial($this->limpiar($datos['url_afp'] ?? null), Credencial::ENLACE_AFP),
            'url_bn' => Credencial::enlaceSiDifiereDelOficial($this->limpiar($datos['url_bn'] ?? null), Credencial::ENLACE_BN),
        ];

        try {
            $cliente = $this->crear($atributos, $credencial);
        } catch (QueryException $excepcion) {
            if ($this->esRucDuplicado($excepcion)) {
                return $this->rechazo('Este RUC ya se encuentra registrado en el CRM.', false);
            }
            if ($this->esNumeroDuplicado($excepcion)) {
                return $this->rechazo('No se pudo asignar el número de cliente. Vuelva a intentar esta fila.', true);
            }
            report($excepcion);

            return $this->rechazo('No se pudo registrar este cliente.', true);
        } catch (Throwable $excepcion) {
            report($excepcion);

            return $this->rechazo('No se pudo registrar este cliente.', true);
        }

        return [
            'registrado' => true,
            'reintentable' => false,
            'mensaje' => "Cliente N° {$cliente->numero} registrado: {$cliente->razon_social}.",
            'cliente' => [
                'id' => $cliente->id,
                'numero' => $cliente->numero,
                'ruc' => $cliente->ruc,
                'razon_social' => $cliente->razon_social,
            ],
        ];
    }

    public function canonRegimen(string $texto): ?string
    {
        $normalizado = $this->fold($texto);

        return match ($normalizado) {
            'rus', 'nrus', 'nuevo rus', 'nuevo regimen unico simplificado', 'regimen unico simplificado' => RegimenTributario::Rus->value,
            'rer', 'regimen especial', 'regimen especial de renta' => RegimenTributario::Rer->value,
            'mype', 'rmt', 'regimen mype', 'regimen mype tributario' => RegimenTributario::Rmt->value,
            'general', 'rg', 'regimen general' => RegimenTributario::Rg->value,
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $atributos
     * @param  array<string, mixed>  $credencial
     */
    private function crear(array $atributos, array $credencial): Cliente
    {
        $ultimo = null;
        for ($intento = 0; $intento < 2; $intento++) {
            try {
                return DB::transaction(function () use ($atributos, $credencial): Cliente {
                    $cliente = Cliente::create($atributos);
                    $cliente->credencial()->create($credencial);
                    $usuario = Auth::user();
                    if ($usuario?->operaCarteraAsignada()) {
                        $cliente->asistentes()->syncWithoutDetaching([$usuario->id]);
                    }

                    return $cliente;
                });
            } catch (QueryException $excepcion) {
                $ultimo = $excepcion;
                if (! $this->esNumeroDuplicado($excepcion)) {
                    throw $excepcion;
                }
            }
        }

        if ($ultimo instanceof QueryException) {
            throw $ultimo;
        }

        throw new \RuntimeException('No se pudo asignar el número de cliente.');
    }

    /**
     * @param  list<string|null>  $celdas
     * @return array{columnas: array<string, int>, errores: list<array{fila: int|null, mensaje: string}>}
     */
    private function mapa(array $celdas): array
    {
        $columnas = [];
        $errores = [];
        foreach ($celdas as $indice => $celda) {
            $clave = $this->fold((string) $celda);
            if ($clave === '') {
                continue;
            }
            $campo = $this->campoDeEncabezado($clave);
            if ($campo === null) {
                continue;
            }
            if (isset($columnas[$campo])) {
                $errores[] = ['fila' => null, 'mensaje' => 'La columna "'.trim((string) $celda).'" está repetida.'];

                continue;
            }
            $columnas[$campo] = $indice;
        }

        foreach (['ruc' => 'RUC', 'regimen' => 'Régimen'] as $campo => $nombre) {
            if (! isset($columnas[$campo])) {
                $errores[] = [
                    'fila' => null,
                    'mensaje' => "Falta la columna {$nombre}. Descargue la plantilla y no borre la fila de títulos.",
                ];
            }
        }

        return ['columnas' => $columnas, 'errores' => $errores];
    }

    private function campoDeEncabezado(string $clave): ?string
    {
        $alias = [
            'ruc' => ['ruc'],
            'regimen' => ['regimen', 'regimen tributario'],
            'correo' => ['correo', 'correo electronico', 'email', 'e mail'],
            'telefono' => ['telefono', 'telefono whatsapp', 'celular', 'whatsapp'],
            'usuario_sol' => ['usuario sol', 'usuario clave sol'],
            'clave_sol' => ['clave sol', 'clave clave sol'],
            'sunat_client_id' => ['client id api sunat', 'client id', 'sunat client id'],
            'sunat_client_secret' => ['client secret api sunat', 'client secret', 'sunat client secret'],
        ];

        foreach ($alias as $campo => $nombres) {
            if (in_array($clave, $nombres, true)) {
                return $campo;
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $celdas
     * @param  array<string, int>  $columnas
     * @return array<string, mixed>|null
     */
    private function fila(int $numero, array $celdas, array $columnas): ?array
    {
        $valor = function (string $campo) use ($celdas, $columnas): string {
            if (! isset($columnas[$campo])) {
                return '';
            }

            return trim((string) ($celdas[$columnas[$campo]] ?? ''));
        };

        $rucCrudo = $valor('ruc');
        $regimenCrudo = $valor('regimen');
        $correo = $valor('correo');
        $telefono = $valor('telefono');
        $usuarioSol = $valor('usuario_sol');
        $claveSol = $valor('clave_sol');
        $clientId = $valor('sunat_client_id');
        $clientSecret = $valor('sunat_client_secret');

        if ($rucCrudo === '' && $regimenCrudo === '' && $correo === '' && $telefono === '' && $usuarioSol === '' && $claveSol === '' && $clientId === '' && $clientSecret === '') {
            return null;
        }

        $errores = [];
        $ruc = '';
        if ($rucCrudo === '') {
            $errores[] = 'Indique el RUC.';
        } else {
            $normalizado = $this->normalizarRuc($rucCrudo);
            if ($normalizado['error'] !== null || $normalizado['ruc'] === null) {
                $errores[] = $normalizado['error'] ?? 'Indique el RUC.';
            } else {
                $ruc = $normalizado['ruc'];
                $formato = RucSunat::mensajeSiInvalido($ruc);
                if ($formato !== null) {
                    $errores[] = $formato;
                }
            }
        }

        $regimen = $this->canonRegimen($regimenCrudo);
        if ($regimen === null) {
            $errores[] = $regimenCrudo === ''
                ? 'Indique el régimen: RUS, RER, RMT o RG. SUNAT no lo informa en el padrón del RUC.'
                : 'El régimen "'.$regimenCrudo.'" no es válido. Use RUS, RER, RMT o RG.';
        }

        if ($correo !== '' && Validator::make(['correo' => $correo], ['correo' => ['email', 'max:255']])->fails()) {
            $errores[] = 'El correo "'.$correo.'" no es válido.';
        }
        if (mb_strlen($telefono) > 20) {
            $errores[] = 'El teléfono no puede pasar de 20 caracteres.';
        }
        if (mb_strlen($usuarioSol) > 255) {
            $errores[] = 'El usuario SOL es demasiado largo.';
        }
        if (mb_strlen($claveSol) > 255) {
            $errores[] = 'La clave SOL es demasiado larga.';
        }
        if (mb_strlen($clientId) > 255) {
            $errores[] = 'El Client ID de la API SUNAT es demasiado largo.';
        }
        if (mb_strlen($clientSecret) > 500) {
            $errores[] = 'El Client Secret de la API SUNAT es demasiado largo.';
        }

        return [
            'fila' => $numero,
            'ruc' => $ruc,
            'regimen' => $regimen ?? '',
            'correo' => $correo,
            'telefono' => $telefono,
            'usuario_sol' => $usuarioSol,
            'clave_sol' => $claveSol,
            'sunat_client_id' => $clientId,
            'sunat_client_secret' => $clientSecret,
            'errores' => $errores,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function marcarRepetidos(array &$filas): void
    {
        $vistos = [];
        foreach ($filas as $indice => $fila) {
            $ruc = (string) ($fila['ruc'] ?? '');
            if ($ruc === '' || RucSunat::mensajeSiInvalido($ruc) !== null) {
                continue;
            }
            if (isset($vistos[$ruc])) {
                $filas[$indice]['errores'][] = "El RUC {$ruc} ya está en la fila {$vistos[$ruc]} de esta plantilla.";

                continue;
            }
            $vistos[$ruc] = $fila['fila'];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function marcarYaRegistrados(array &$filas): void
    {
        $rucs = [];
        foreach ($filas as $fila) {
            $ruc = (string) ($fila['ruc'] ?? '');
            if ($ruc !== '' && RucSunat::mensajeSiInvalido($ruc) === null) {
                $rucs[] = $ruc;
            }
        }
        if ($rucs === []) {
            return;
        }

        $existentes = Cliente::withTrashed()->whereIn('ruc', array_values(array_unique($rucs)))->get()->keyBy('ruc');
        foreach ($filas as $indice => $fila) {
            $cliente = $existentes->get((string) ($fila['ruc'] ?? ''));
            if ($cliente === null) {
                continue;
            }
            $filas[$indice]['errores'][] = $cliente->trashed()
                ? "Este RUC está en la papelera ({$cliente->razon_social}). Restaure ese cliente o elimínelo de forma definitiva antes de volver a cargarlo."
                : "Este RUC ya se encuentra registrado en el CRM: {$cliente->razon_social}";
        }
    }

    /**
     * @return array{ruc: string|null, error: string|null}
     */
    private function normalizarRuc(string $crudo): array
    {
        $crudo = trim($crudo);
        if (str_starts_with($crudo, '=')) {
            $crudo = trim($crudo, "=\"' ");
        }
        $crudo = trim($crudo, " \t'\"");

        if ($crudo === '') {
            return ['ruc' => null, 'error' => 'Indique el RUC.'];
        }

        if (preg_match('/e\s*[+\-]/i', $crudo) === 1) {
            return [
                'ruc' => null,
                'error' => "El RUC llegó como número de Excel ({$crudo}). Marque la columna RUC como Texto y vuelva a guardar el CSV.",
            ];
        }

        if (preg_match('/^\d+\.0+$/', $crudo) === 1) {
            $crudo = strstr($crudo, '.', true) ?: $crudo;
        }

        if (preg_match('/[a-z]/i', $crudo) === 1) {
            return ['ruc' => null, 'error' => 'El RUC solo puede contener números.'];
        }

        $digitos = preg_replace('/\D/', '', $crudo) ?? '';

        return ['ruc' => $digitos, 'error' => null];
    }

    /**
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>|null
     */
    private function rechazoDeEnlace(array $datos): ?array
    {
        foreach (['url_afp' => 'AFPnet', 'url_bn' => 'detracciones'] as $campo => $nombre) {
            $url = $this->limpiar($datos[$campo] ?? null);
            if ($url === null) {
                continue;
            }
            if (preg_match('#\Ahttps?://#i', $url) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
                return $this->rechazo("El enlace de {$nombre} debe comenzar con http:// o https://.", false, 'enlace');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $padron
     * @return array<string, mixed>
     */
    private function observado(string $mensaje, bool $reintentable, ?string $ruc = null, array $padron = []): array
    {
        return [
            'lista' => false,
            'reintentable' => $reintentable,
            'mensaje' => $mensaje,
            'ruc' => $ruc,
            'razon_social' => $padron['razon_social'] ?? null,
            'estado_sunat' => $padron['estado_sunat'] ?? null,
            'condicion_sunat' => $padron['condicion_sunat'] ?? null,
            'tipo_persona' => $padron['tipo_persona'] ?? null,
            'direccion_sunat' => $padron['direccion_sunat'] ?? null,
            'ubigeo' => $padron['ubigeo'] ?? null,
            'distrito' => $padron['distrito'] ?? null,
            'provincia' => $padron['provincia'] ?? null,
            'departamento' => $padron['departamento'] ?? null,
        ];
    }

    /**
     * @return array{registrado: bool, reintentable: bool, mensaje: string, motivo?: string}
     */
    private function rechazo(string $mensaje, bool $reintentable, ?string $motivo = null): array
    {
        $respuesta = [
            'registrado' => false,
            'reintentable' => $reintentable,
            'mensaje' => $mensaje,
        ];
        if ($motivo !== null) {
            $respuesta['motivo'] = $motivo;
        }

        return $respuesta;
    }

    private function limpiar(mixed $valor): ?string
    {
        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        return $valor === '' ? null : $valor;
    }

    private function fold(string $valor): string
    {
        $valor = mb_strtolower(trim($valor));
        $valor = strtr($valor, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $valor = preg_replace('/[^a-z0-9]+/', ' ', $valor) ?? $valor;

        return trim($valor);
    }

    private function esRucDuplicado(QueryException $excepcion): bool
    {
        $mensaje = $excepcion->getMessage();

        return str_contains($mensaje, 'clientes_estudio_ruc_unique')
            || str_contains($mensaje, 'clientes.ruc');
    }

    private function esNumeroDuplicado(QueryException $excepcion): bool
    {
        $mensaje = $excepcion->getMessage();

        return str_contains($mensaje, 'clientes_estudio_numero_unique')
            || str_contains($mensaje, 'clientes.numero');
    }

    private function delimitador(string $encabezado): string
    {
        $conteos = [
            ';' => substr_count($encabezado, ';'),
            "\t" => substr_count($encabezado, "\t"),
            ',' => substr_count($encabezado, ','),
        ];
        arsort($conteos);
        $ganador = (string) array_key_first($conteos);

        return ($conteos[$ganador] ?? 0) > 0 ? $ganador : ';';
    }

    private function utf8(string $contenido): string
    {
        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido) ?? $contenido;
        if (mb_check_encoding($contenido, 'UTF-8')) {
            return $contenido;
        }

        $convertido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');

        return is_string($convertido) ? $convertido : $contenido;
    }

    /**
     * @return array{filas: list<array<string, mixed>>, errores: list<array{fila: int|null, mensaje: string}>}
     */
    private function vacio(string $mensaje): array
    {
        return [
            'filas' => [],
            'errores' => [['fila' => null, 'mensaje' => $mensaje]],
        ];
    }
}
