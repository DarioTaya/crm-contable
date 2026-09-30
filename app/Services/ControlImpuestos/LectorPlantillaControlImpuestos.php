<?php

namespace App\Services\ControlImpuestos;

use App\Enums\MedioPagoTributo;
use App\Enums\TipoTributoSunat;
use Carbon\Carbon;

/**
 * Lee la plantilla CSV de Control de impuestos.
 *
 * Acepta el archivo descargado desde la matriz, un CSV guardado por Excel
 * (coma o punto y coma) y el texto pegado desde una hoja de cálculo.
 */
class LectorPlantillaControlImpuestos
{
    private const MAXIMO_FILAS = 4000;

    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     pagos: list<array<string, mixed>>,
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

        $delimitador = $this->delimitador($lineas);
        $tabla = [];
        foreach ($lineas as $indice => $linea) {
            if (trim($linea) === '') {
                continue;
            }
            $tabla[] = [
                'numero' => $indice + 1,
                'celdas' => str_getcsv($linea, $delimitador, '"', '\\'),
            ];
        }

        if ($tabla === []) {
            return $this->vacio('La plantilla está vacía.');
        }

        if (count($tabla) - 1 > self::MAXIMO_FILAS) {
            return $this->vacio('La plantilla supera las 4000 filas. Divídala por grupos de clientes.');
        }

        $mapa = $this->mapa($tabla[0]['celdas']);
        if ($mapa['errores'] !== []) {
            return ['filas' => [], 'pagos' => [], 'errores' => $mapa['errores']];
        }

        $filas = [];
        $pagos = [];
        $errores = [];
        $seccion = 'meses';
        $mapaPagos = null;
        $pagosInvalidos = false;
        foreach (array_slice($tabla, 1) as $registro) {
            if ($this->esMarcaPagos($registro['celdas'])) {
                $seccion = 'pagos';
                $mapaPagos = null;
                $pagosInvalidos = false;

                continue;
            }

            if ($seccion === 'pagos') {
                if ($mapaPagos === null) {
                    $mapaPagos = $this->mapaPagos($registro['celdas'], $registro['numero']);
                    if ($mapaPagos['errores'] !== []) {
                        array_push($errores, ...$mapaPagos['errores']);
                        $pagosInvalidos = true;
                    }

                    continue;
                }
                if ($pagosInvalidos) {
                    continue;
                }
                $leida = $this->filaPago($registro['numero'], $registro['celdas'], $mapaPagos['columnas']);
                if ($leida === null) {
                    continue;
                }
                if ($leida['errores'] !== []) {
                    array_push($errores, ...$leida['errores']);

                    continue;
                }
                $pagos[] = $leida['pago'];

                continue;
            }

            $leida = $this->fila($registro['numero'], $registro['celdas'], $mapa['columnas']);
            if ($leida === null) {
                continue;
            }
            if ($leida['errores'] !== []) {
                array_push($errores, ...$leida['errores']);

                continue;
            }
            $filas[] = $leida['fila'];
        }

        if ($filas === [] && $pagos === [] && $errores === []) {
            return $this->vacio('La plantilla no tiene filas para cargar.');
        }

        return ['filas' => $filas, 'pagos' => $pagos, 'errores' => $errores];
    }

    /**
     * El delimitador se decide con el encabezado, que no trae importes.
     */
    private function delimitador(array $lineas): string
    {
        $encabezado = '';
        foreach ($lineas as $linea) {
            if (trim($linea) !== '') {
                $encabezado = $linea;
                break;
            }
        }

        $conteos = [
            ';' => substr_count($encabezado, ';'),
            "\t" => substr_count($encabezado, "\t"),
            ',' => substr_count($encabezado, ','),
        ];
        arsort($conteos);
        $ganador = (string) array_key_first($conteos);

        return ($conteos[$ganador] ?? 0) > 0 ? $ganador : ';';
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
            $texto = trim((string) $celda);
            if ($texto === '') {
                continue;
            }
            $campo = $this->campo($texto);
            if ($campo === null) {
                $errores[] = [
                    'fila' => 1,
                    'mensaje' => "La columna «{$texto}» no forma parte de la plantilla.",
                ];

                continue;
            }
            if (array_key_exists($campo, $columnas)) {
                $errores[] = [
                    'fila' => 1,
                    'mensaje' => "La columna «{$texto}» está repetida.",
                ];

                continue;
            }
            $columnas[$campo] = $indice;
        }

        if (! isset($columnas['ruc']) || ! isset($columnas['periodo'])) {
            $errores[] = [
                'fila' => 1,
                'mensaje' => 'La plantilla debe incluir las columnas ruc y periodo.',
            ];
        }

        $tieneDatos = false;
        foreach (array_keys($columnas) as $campo) {
            if (in_array($campo, ['sire', 'pdt', 'ple', 'dj', 'pagado', 'honorarios'], true) || str_starts_with($campo, 'tributo:')) {
                $tieneDatos = true;
                break;
            }
        }
        if (! $tieneDatos && $errores === []) {
            $errores[] = [
                'fila' => 1,
                'mensaje' => 'La plantilla no tiene columnas de declaraciones ni de tributos.',
            ];
        }

        return ['columnas' => $columnas, 'errores' => $errores];
    }

    private function campo(string $encabezado): ?string
    {
        $texto = str_replace(['_', '-'], ' ', $this->sinAcentos(mb_strtolower(trim($encabezado))));
        $texto = trim(preg_replace('/\s*\([^)]*\)\s*/', ' ', $texto) ?? $texto);
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);

        $alias = [
            'ruc' => 'ruc',
            'razon social' => 'razon_social',
            'cliente' => 'razon_social',
            'empresa' => 'razon_social',
            'periodo' => 'periodo',
            'mes' => 'periodo',
            'declaracion dj' => 'dj',
            'estado declaracion' => 'dj',
            'declaracion' => 'dj',
            'dj' => 'dj',
            'sire' => 'sire',
            'compras y ventas' => 'sire',
            'pdt 621' => 'pdt',
            'pdt621' => 'pdt',
            'pdt 601 plame' => 'ple',
            'pdt 601' => 'ple',
            'pdt601' => 'ple',
            'ple' => 'ple',
            'plame' => 'ple',
            'pdt' => 'pdt',
            'honorarios rh' => 'honorarios',
            'honorarios' => 'honorarios',
            'recibos por honorarios' => 'honorarios',
            'recibo por honorarios' => 'honorarios',
            'rh' => 'honorarios',
            'pago honorarios' => 'honorarios',
            'codigo del tributo' => 'codigo_tributo',
            'codigo tributo' => 'codigo_tributo',
            'codigo' => 'codigo_tributo',
            'monto del pago' => 'monto_pago',
            'monto pago' => 'monto_pago',
            'monto' => 'monto_pago',
            'importe' => 'monto_pago',
            'pagado' => 'pagado',
            'pago' => 'pagado',
            'fecha de pago' => 'fecha_pago',
            'fecha pago' => 'fecha_pago',
            'fecha' => 'fecha_pago',
            'medio de pago' => 'medio_pago',
            'medio pago' => 'medio_pago',
            'medio' => 'medio_pago',
            'numero de operacion' => 'numero_operacion',
            'numero operacion' => 'numero_operacion',
            'numero' => 'numero_operacion',
            'operacion' => 'numero_operacion',
            'n de operacion' => 'numero_operacion',
            'igv justo' => 'igv_justo',
            'ley igv justo' => 'igv_justo',
        ];

        $nombres = array_keys($alias);
        usort($nombres, fn (string $izquierda, string $derecha): int => strlen($derecha) <=> strlen($izquierda));
        foreach ($nombres as $nombre) {
            if ($texto === $nombre || str_starts_with($texto, $nombre.' ')) {
                return $alias[$nombre];
            }
        }

        $token = preg_split('/\s+/', $texto)[0] ?? '';
        if (strcasecmp($token, 'afp') === 0) {
            $token = 'AFP';
        }

        foreach (TipoTributoSunat::cases() as $caso) {
            if (strcasecmp($token, $caso->value) === 0) {
                return 'tributo:'.$caso->value;
            }
        }

        return null;
    }

    /**
     * @param  list<string|null>  $celdas
     * @param  array<string, int>  $columnas
     * @return array{fila: array<string, mixed>, errores: list<array{fila: int, mensaje: string}>}|null
     */
    private function fila(int $numero, array $celdas, array $columnas): ?array
    {
        if ($this->filaVacia($celdas)) {
            return null;
        }

        $errores = [];
        $ruc = preg_replace('/\D/', '', $this->valor($celdas, $columnas, 'ruc')) ?? '';
        $periodoTexto = $this->valor($celdas, $columnas, 'periodo');
        $periodo = $this->periodo($periodoTexto);
        $estados = [];
        foreach (['sire' => 'SIRE', 'pdt' => 'PDT 621', 'ple' => 'PDT 601'] as $campo => $etiqueta) {
            $texto = $this->valor($celdas, $columnas, $campo);
            if ($texto === '') {
                $estados[$campo] = null;

                continue;
            }
            $estado = $this->estado($texto);
            if ($estado === null) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el estado «{$texto}» de {$etiqueta} no es válido. Use Pendiente, En proceso o Declarado.",
                ];
                $estados[$campo] = null;

                continue;
            }
            $estados[$campo] = $estado;
        }

        $djTexto = $this->valor($celdas, $columnas, 'dj');
        if ($djTexto !== '') {
            $dj = $this->estado($djTexto);
            if ($dj === null) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el estado «{$djTexto}» de la declaración no es válido. Use Pendiente, En proceso o Declarado.",
                ];
            } else {
                foreach (['sire' => 'SIRE', 'pdt' => 'PDT 621', 'ple' => 'PDT 601'] as $campo => $etiqueta) {
                    if (is_string($estados[$campo]) && $estados[$campo] !== $dj) {
                        $errores[] = [
                            'fila' => $numero,
                            'mensaje' => "Fila {$numero}: Declaracion DJ dice {$dj} y {$etiqueta} dice {$estados[$campo]}. Deje una sola columna de declaración.",
                        ];
                    }
                    $estados[$campo] = $dj;
                }
            }
        }

        $honorariosTexto = $this->valor($celdas, $columnas, 'honorarios');
        $honorarios = null;
        if ($honorariosTexto !== '') {
            $honorarios = $this->honorarios($honorariosTexto);
            if ($honorarios === null) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el estado «{$honorariosTexto}» de Honorarios RH no es válido. Use Pagó o Debe (o Pagado/Pendiente).",
                ];
            }
        }

        $igv = $this->afirmacion($this->valor($celdas, $columnas, 'igv_justo'));
        $pagado = $this->afirmacion($this->valor($celdas, $columnas, 'pagado'));
        if ($pagado['error'] !== null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: pagado debe ser SI o NO."];
        }
        $fechaPago = $this->fecha($this->valor($celdas, $columnas, 'fecha_pago'));
        if ($fechaPago['error'] !== null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: la fecha de pago no es válida. Use DD/MM/AAAA."];
        }
        $medioPago = $this->medio($this->valor($celdas, $columnas, 'medio_pago'));
        if ($medioPago['error'] !== null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el medio de pago no es válido. Use Detracciones, Caja, Bancos, Compensacion renta anual o Compensacion ITAN."];
        }
        $numeroOperacion = $this->valor($celdas, $columnas, 'numero_operacion');
        if (mb_strlen($numeroOperacion) > 500) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el número de operación admite hasta 500 caracteres."];
        }
        $traeDetalleDePago = $fechaPago['valor'] !== null || $medioPago['valor'] !== null || $numeroOperacion !== '';
        if ($traeDetalleDePago && $pagado['valor'] !== true && $pagado['error'] === null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: la fecha, el medio y el número de operación solo se usan cuando Pagado es SI."];
        }
        $tributos = [];
        foreach ($columnas as $campo => $indice) {
            if (! str_starts_with($campo, 'tributo:')) {
                continue;
            }
            $codigo = substr($campo, 8);
            $texto = trim((string) ($celdas[$indice] ?? ''));
            if ($texto === '') {
                continue;
            }
            $importe = $this->importe($texto);
            if ($importe['error'] !== null) {
                $errores[] = [
                    'fila' => $numero,
                    'mensaje' => "Fila {$numero}: el importe «{$texto}» de {$codigo} no es válido. {$importe['error']}",
                ];

                continue;
            }
            if ($importe['monto'] === null) {
                continue;
            }
            $tributos[] = ['codigo' => $codigo, 'monto' => $importe['monto']];
        }

        if ($igv['error'] !== null && $this->traeIgv($tributos)) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: IGV Justo debe ser SI o NO."];
        }

        $hayInstruccion = $tributos !== []
            || $djTexto !== ''
            || $estados['sire'] !== null
            || $estados['pdt'] !== null
            || $estados['ple'] !== null
            || $honorarios !== null
            || $pagado['valor'] === true
            || $pagado['error'] !== null
            || $traeDetalleDePago
            || $fechaPago['error'] !== null
            || $medioPago['error'] !== null;

        if (! $hayInstruccion && $errores === []) {
            return null;
        }

        if ($ruc === '') {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: falta el RUC."];
        } elseif (strlen($ruc) !== 11) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el RUC debe tener 11 dígitos."];
        }

        if ($periodo === null) {
            $mostrado = $periodoTexto !== '' ? $periodoTexto : 'vacío';
            $errores[] = [
                'fila' => $numero,
                'mensaje' => "Fila {$numero}: el periodo «{$mostrado}» no se reconoce. Use MM-AAAA, por ejemplo 08-2026.",
            ];
        }

        return [
            'fila' => [
                'fila' => $numero,
                'ruc' => $ruc,
                'periodo' => $periodo,
                'sire' => $estados['sire'],
                'pdt' => $estados['pdt'],
                'ple' => $estados['ple'],
                'honorarios' => $honorarios,
                'igv_justo' => $igv['valor'],
                'pagado' => $pagado['valor'],
                'fecha_pago' => $fechaPago['valor'],
                'medio_pago' => $medioPago['valor'],
                'numero_operacion' => $numeroOperacion,
                'tributos' => $tributos,
            ],
            'errores' => $errores,
        ];
    }

    /**
     * @param  list<string|null>  $celdas
     */
    private function filaVacia(array $celdas): bool
    {
        foreach ($celdas as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $celdas
     * @param  array<string, int>  $columnas
     */
    private function valor(array $celdas, array $columnas, string $campo): string
    {
        if (! isset($columnas[$campo])) {
            return '';
        }

        return trim((string) ($celdas[$columnas[$campo]] ?? ''));
    }

    private function periodo(string $texto): ?string
    {
        $texto = mb_strtolower(trim($texto));
        if ($texto === '') {
            return null;
        }

        if (preg_match('/^(0?[1-9]|1[0-2])[\/\-](\d{4})$/', $texto, $coincidencia) === 1) {
            return sprintf('%02d-%s', (int) $coincidencia[1], $coincidencia[2]);
        }

        if (preg_match('/^(\d{4})[\/\-](0?[1-9]|1[0-2])$/', $texto, $coincidencia) === 1) {
            return sprintf('%02d-%s', (int) $coincidencia[2], $coincidencia[1]);
        }

        if (preg_match('/^([a-z]+)[\s\/\-]+(\d{4})$/', $this->sinAcentos($texto), $coincidencia) === 1) {
            $mes = $this->meses()[$coincidencia[1]] ?? null;
            if ($mes !== null) {
                return sprintf('%02d-%s', $mes, $coincidencia[2]);
            }
        }

        return null;
    }

    private function estado(string $texto): ?string
    {
        $normalizado = str_replace(['_', '-'], ' ', $this->sinAcentos(mb_strtolower(trim($texto))));
        $normalizado = trim(preg_replace('/\s+/', ' ', $normalizado) ?? $normalizado);

        return match ($normalizado) {
            'pendiente' => 'Pendiente',
            'procesando', 'en proceso', 'proceso' => 'Procesando',
            'declarado' => 'Declarado',
            default => null,
        };
    }

    private function honorarios(string $texto): ?string
    {
        $normalizado = str_replace(['_', '-'], ' ', $this->sinAcentos(mb_strtolower(trim($texto))));
        $normalizado = trim(preg_replace('/\s+/', ' ', $normalizado) ?? $normalizado);

        return match ($normalizado) {
            'pago', 'pagado', 'si', 's', '1', 'cancelado' => 'Pagado',
            'debe', 'pendiente', 'no', 'n', '0' => 'Pendiente',
            default => null,
        };
    }

    /**
     * @return array{valor: ?string, error: ?string}
     */
    private function fecha(string $texto): array
    {
        if ($texto === '') {
            return ['valor' => null, 'error' => null];
        }

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/y', 'd-m-y', 'Y/m/d'] as $formato) {
            try {
                $fecha = Carbon::createFromFormat('!'.$formato, $texto);
            } catch (\Throwable) {
                continue;
            }
            if (! $fecha instanceof Carbon) {
                continue;
            }
            $fallos = Carbon::getLastErrors();
            if (($fallos['warning_count'] ?? 0) > 0 || ($fallos['error_count'] ?? 0) > 0) {
                continue;
            }

            return ['valor' => $fecha->toDateString(), 'error' => null];
        }

        return ['valor' => null, 'error' => 'invalida'];
    }

    /**
     * @return array{valor: ?string, error: ?string}
     */
    private function medio(string $texto): array
    {
        if ($texto === '') {
            return ['valor' => null, 'error' => null];
        }

        $normalizado = str_replace(['_', '-'], ' ', $this->sinAcentos(mb_strtolower(trim($texto))));
        $normalizado = trim(preg_replace('/\s+/', ' ', $normalizado) ?? $normalizado);

        $sinonimos = [
            'efectivo' => MedioPagoTributo::Caja->value,
            'caja' => MedioPagoTributo::Caja->value,
            'banco' => MedioPagoTributo::Bancos->value,
            'bancos' => MedioPagoTributo::Bancos->value,
            'transferencia' => MedioPagoTributo::Bancos->value,
            'deposito' => MedioPagoTributo::Bancos->value,
            'detraccion' => MedioPagoTributo::Detracciones->value,
            'detracciones' => MedioPagoTributo::Detracciones->value,
            'cuenta detracciones' => MedioPagoTributo::Detracciones->value,
            'saldo a favor' => MedioPagoTributo::CompensacionRenta->value,
            'compensacion saldo' => MedioPagoTributo::CompensacionRenta->value,
            'compensacion renta' => MedioPagoTributo::CompensacionRenta->value,
            'compensacion renta anual' => MedioPagoTributo::CompensacionRenta->value,
            'itan' => MedioPagoTributo::CompensacionItan->value,
            'compensacion itan' => MedioPagoTributo::CompensacionItan->value,
        ];

        if (isset($sinonimos[$normalizado])) {
            return ['valor' => $sinonimos[$normalizado], 'error' => null];
        }

        foreach (MedioPagoTributo::cases() as $caso) {
            $etiqueta = str_replace(['_', '-'], ' ', $this->sinAcentos(mb_strtolower($caso->etiqueta())));
            $valor = str_replace('_', ' ', $caso->value);
            if (in_array($normalizado, [$etiqueta, $valor, $caso->value], true)) {
                return ['valor' => $caso->value, 'error' => null];
            }
        }

        return ['valor' => null, 'error' => 'invalido'];
    }

    /**
     * @return array{valor: ?bool, error: ?string}
     */
    private function afirmacion(string $texto): array
    {
        if ($texto === '') {
            return ['valor' => null, 'error' => null];
        }

        $normalizado = $this->sinAcentos(mb_strtolower(trim($texto)));

        if (in_array($normalizado, ['si', 's', '1', 'yes', 'verdadero', 'x'], true)) {
            return ['valor' => true, 'error' => null];
        }

        if (in_array($normalizado, ['no', 'n', '0', 'false', 'falso'], true)) {
            return ['valor' => false, 'error' => null];
        }

        return ['valor' => null, 'error' => 'invalido'];
    }

    /**
     * @return array{monto: ?float, error: ?string}
     */
    private function importe(string $texto): array
    {
        $limpio = str_ireplace(['S/', 'S/.', ' '], '', trim($texto));
        $limpio = str_replace("'", '', $limpio);

        if ($limpio === '' || $limpio === '-' || $limpio === '—') {
            return ['monto' => null, 'error' => null];
        }

        if (str_starts_with($limpio, '(') && str_ends_with($limpio, ')')) {
            return ['monto' => null, 'error' => 'No use importes negativos.'];
        }

        $tieneComa = str_contains($limpio, ',');
        $tienePunto = str_contains($limpio, '.');

        if ($tieneComa && $tienePunto) {
            if (strrpos($limpio, ',') > strrpos($limpio, '.')) {
                $limpio = str_replace('.', '', $limpio);
                $limpio = str_replace(',', '.', $limpio);
            } else {
                $limpio = str_replace(',', '', $limpio);
            }
        } elseif ($tieneComa) {
            if (substr_count($limpio, ',') > 1 || preg_match('/^\d{1,3}(,\d{3})+$/', $limpio) === 1) {
                $limpio = str_replace(',', '', $limpio);
            } else {
                $limpio = str_replace(',', '.', $limpio);
            }
        } elseif (substr_count($limpio, '.') > 1) {
            $limpio = str_replace('.', '', $limpio);
        } elseif ($tienePunto && preg_match('/^\d{1,3}\.\d{3}$/', $limpio) === 1) {
            $limpio = str_replace('.', '', $limpio);
        }

        if (! is_numeric($limpio)) {
            return ['monto' => null, 'error' => 'Use un número como 1500.50 o 1.500,50.'];
        }

        $monto = round((float) $limpio, 2);
        if ($monto < 0) {
            return ['monto' => null, 'error' => 'No use importes negativos.'];
        }
        if ($monto > 99999999.99) {
            return ['monto' => null, 'error' => 'El importe supera el máximo permitido.'];
        }
        if ($monto <= 0) {
            return ['monto' => null, 'error' => null];
        }

        return ['monto' => $monto, 'error' => null];
    }

    /**
     * @param  list<array{codigo: string, monto: float}>  $tributos
     */
    private function traeIgv(array $tributos): bool
    {
        foreach ($tributos as $tributo) {
            if ($tributo['codigo'] === TipoTributoSunat::Igv18->value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, int>
     */
    private function meses(): array
    {
        return [
            'enero' => 1, 'ene' => 1,
            'febrero' => 2, 'feb' => 2,
            'marzo' => 3, 'mar' => 3,
            'abril' => 4, 'abr' => 4,
            'mayo' => 5, 'may' => 5,
            'junio' => 6, 'jun' => 6,
            'julio' => 7, 'jul' => 7,
            'agosto' => 8, 'ago' => 8,
            'setiembre' => 9, 'septiembre' => 9, 'set' => 9, 'sep' => 9,
            'octubre' => 10, 'oct' => 10,
            'noviembre' => 11, 'nov' => 11,
            'diciembre' => 12, 'dic' => 12,
        ];
    }

    private function sinAcentos(string $texto): string
    {
        return strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n',
        ]);
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
            'pagos' => [],
            'errores' => [['fila' => null, 'mensaje' => $mensaje]],
        ];
    }

    /**
     * @param  list<string|null>  $celdas
     */
    private function esMarcaPagos(array $celdas): bool
    {
        $marca = $this->sinAcentos(mb_strtolower(trim((string) ($celdas[0] ?? ''))));
        if ($marca !== 'pagos') {
            return false;
        }

        foreach (array_slice($celdas, 1) as $celda) {
            if (trim((string) $celda) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string|null>  $celdas
     * @return array{columnas: array<string, int>, errores: list<array{fila: int|null, mensaje: string}>}
     */
    private function mapaPagos(array $celdas, int $fila): array
    {
        $columnas = [];
        $errores = [];

        foreach ($celdas as $indice => $celda) {
            $texto = trim((string) $celda);
            if ($texto === '') {
                continue;
            }
            $campo = $this->campo($texto);
            if ($campo === null) {
                $errores[] = [
                    'fila' => $fila,
                    'mensaje' => "La columna de pagos «{$texto}» no forma parte de la plantilla.",
                ];

                continue;
            }
            if (array_key_exists($campo, $columnas)) {
                $errores[] = [
                    'fila' => $fila,
                    'mensaje' => "La columna de pagos «{$texto}» está repetida.",
                ];

                continue;
            }
            $columnas[$campo] = $indice;
        }

        foreach ([
            'ruc' => 'ruc',
            'periodo' => 'periodo',
            'codigo_tributo' => 'codigo del tributo',
            'monto_pago' => 'monto del pago',
            'fecha_pago' => 'fecha de pago',
            'medio_pago' => 'medio de pago',
        ] as $campo => $etiqueta) {
            if (! isset($columnas[$campo])) {
                $errores[] = [
                    'fila' => $fila,
                    'mensaje' => "La sección PAGOS debe incluir la columna {$etiqueta}.",
                ];
            }
        }

        return ['columnas' => $columnas, 'errores' => $errores];
    }

    /**
     * @param  list<string|null>  $celdas
     * @param  array<string, int>  $columnas
     * @return array{pago: array<string, mixed>, errores: list<array{fila: int, mensaje: string}>}|null
     */
    private function filaPago(int $numero, array $celdas, array $columnas): ?array
    {
        if ($this->filaVacia($celdas)) {
            return null;
        }

        $errores = [];
        $ruc = preg_replace('/\D/', '', $this->valor($celdas, $columnas, 'ruc')) ?? '';
        $periodoTexto = $this->valor($celdas, $columnas, 'periodo');
        $periodo = $this->periodo($periodoTexto);
        $codigo = $this->codigoTributo($this->valor($celdas, $columnas, 'codigo_tributo'));
        $importe = $this->importe($this->valor($celdas, $columnas, 'monto_pago'));
        $fechaPago = $this->fecha($this->valor($celdas, $columnas, 'fecha_pago'));
        $medioPago = $this->medio($this->valor($celdas, $columnas, 'medio_pago'));
        $numeroOperacion = $this->valor($celdas, $columnas, 'numero_operacion');

        if ($codigo === null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: indique el código del tributo que se paga, por ejemplo 1011 o 5210."];
        }
        if ($importe['error'] !== null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el monto del pago no es válido. {$importe['error']}"];
        } elseif ($importe['monto'] === null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: falta el monto del pago."];
        }
        if ($fechaPago['error'] !== null || $fechaPago['valor'] === null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: la fecha de pago no es válida. Use DD/MM/AAAA."];
        }
        if ($medioPago['error'] !== null || $medioPago['valor'] === null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el medio de pago no es válido. Use Detracciones, Caja, Bancos, Compensacion renta anual o Compensacion ITAN."];
        }
        if (mb_strlen($numeroOperacion) > 500) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el número de operación admite hasta 500 caracteres."];
        }
        if ($ruc === '') {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: falta el RUC."];
        } elseif (strlen($ruc) !== 11) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: el RUC debe tener 11 dígitos."];
        }
        if ($periodo === null) {
            $mostrado = $periodoTexto !== '' ? $periodoTexto : 'vacío';
            $errores[] = [
                'fila' => $numero,
                'mensaje' => "Fila {$numero}: el periodo «{$mostrado}» no se reconoce. Use MM-AAAA, por ejemplo 08-2026.",
            ];
        }

        return [
            'pago' => [
                'fila' => $numero,
                'ruc' => $ruc,
                'periodo' => $periodo,
                'codigo' => $codigo,
                'monto' => $importe['monto'],
                'fecha' => $fechaPago['valor'],
                'medio' => $medioPago['valor'],
                'numero' => $numeroOperacion,
            ],
            'errores' => $errores,
        ];
    }

    private function codigoTributo(string $texto): ?string
    {
        $token = preg_split('/\s+/', trim($texto))[0] ?? '';
        if ($token === '') {
            return null;
        }
        if (strcasecmp($token, 'afp') === 0) {
            $token = 'AFP';
        }
        if ($token === '3011') {
            $token = '3031';
        }

        return TipoTributoSunat::resolver($token)?->value;
    }
}
