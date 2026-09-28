<?php

namespace App\Services\ControlImpuestos;

use App\Enums\TipoTributoSunat;

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
            return ['filas' => [], 'errores' => $mapa['errores']];
        }

        $filas = [];
        $errores = [];
        foreach (array_slice($tabla, 1) as $registro) {
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

        if ($filas === [] && $errores === []) {
            return $this->vacio('La plantilla no tiene filas para cargar.');
        }

        return ['filas' => $filas, 'errores' => $errores];
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
            if (in_array($campo, ['sire', 'pdt', 'ple', 'pagado'], true) || str_starts_with($campo, 'tributo:')) {
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
        $texto = trim(preg_replace('/\s+/', ' ', $texto) ?? $texto);

        $alias = [
            'ruc' => 'ruc',
            'razon social' => 'razon_social',
            'cliente' => 'razon_social',
            'empresa' => 'razon_social',
            'periodo' => 'periodo',
            'mes' => 'periodo',
            'sire' => 'sire',
            'compras y ventas' => 'sire',
            'pdt' => 'pdt',
            'pdt 621' => 'pdt',
            'pdt621' => 'pdt',
            'ple' => 'ple',
            'plame' => 'ple',
            'pdt 601' => 'ple',
            'pdt601' => 'ple',
            'pagado' => 'pagado',
            'pago' => 'pagado',
            'igv justo' => 'igv_justo',
            'ley igv justo' => 'igv_justo',
        ];

        if (isset($alias[$texto])) {
            return $alias[$texto];
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

        $igv = $this->afirmacion($this->valor($celdas, $columnas, 'igv_justo'));
        $pagado = $this->afirmacion($this->valor($celdas, $columnas, 'pagado'));
        if ($pagado['error'] !== null) {
            $errores[] = ['fila' => $numero, 'mensaje' => "Fila {$numero}: pagado debe ser SI o NO."];
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
            || $estados['sire'] !== null
            || $estados['pdt'] !== null
            || $estados['ple'] !== null
            || $pagado['valor'] === true
            || $pagado['error'] !== null;

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
                'igv_justo' => $igv['valor'],
                'pagado' => $pagado['valor'],
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

    /**
     * @return array{valor: ?bool, error: ?string}
     */
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
            'errores' => [['fila' => null, 'mensaje' => $mensaje]],
        ];
    }
}
