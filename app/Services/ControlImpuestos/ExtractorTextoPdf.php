<?php

namespace App\Services\ControlImpuestos;

/**
 * Saca el texto de un PDF de constancia SUNAT sin depender de un paquete externo.
 * Los PDF de SOL traen el texto en streams FlateDecode, no en una imagen.
 */
final class ExtractorTextoPdf
{
    public function extraer(string $binario): string
    {
        if ($binario === '') {
            return '';
        }

        if (! str_starts_with($binario, '%PDF')) {
            return $this->tieneLetras($this->limpiar($binario)) ? $this->limpiar($binario) : '';
        }

        $texto = $this->limpiar($this->enOrdenDeLectura($binario));

        return $this->tieneLetras($texto) ? $texto : '';
    }

    /**
     * Reconstruye las líneas como se ven en el papel. SUNAT imprime la etiqueta
     * y el valor en columnas; el orden del archivo no es el orden de lectura.
     */
    private function enOrdenDeLectura(string $pdf): string
    {
        $paginas = [];
        foreach ($this->contenidos($pdf) as $contenido) {
            if (preg_match('/Tj|TJ/', $contenido) !== 1) {
                continue;
            }
            $texto = $this->textoDeFragmentos($this->fragmentosDeContenido($contenido));
            if ($texto !== '') {
                $paginas[] = $texto;
            }
        }

        return $paginas === [] ? $this->deContenido($pdf) : implode("\n", $paginas);
    }

    /**
     * Cada página tiene su propio origen. Mezclarlas por la coordenada Y junta
     * el encabezado de la página 2 con las casillas de la página 1.
     *
     * @param  list<array{x: float, y: float, texto: string}>  $fragmentos
     */
    private function textoDeFragmentos(array $fragmentos): string
    {
        if ($fragmentos === []) {
            return '';
        }

        usort($fragmentos, function (array $a, array $b): int {
            $fila = (int) round($b['y']) <=> (int) round($a['y']);

            return $fila !== 0 ? $fila : $a['x'] <=> $b['x'];
        });

        $lineas = [];
        $yActual = null;
        $piezas = [];
        foreach ($fragmentos as $fragmento) {
            if ($yActual !== null && abs($fragmento['y'] - $yActual) > 2.2) {
                $lineas[] = trim(implode(' ', $piezas));
                $piezas = [];
            }
            $yActual = $fragmento['y'];
            if ($fragmento['texto'] !== '') {
                $piezas[] = $fragmento['texto'];
            }
        }
        if ($piezas !== []) {
            $lineas[] = trim(implode(' ', $piezas));
        }

        return implode("\n", array_filter($lineas, fn (string $linea): bool => $linea !== ''));
    }

    /**
     * @return list<string>
     */
    private function contenidos(string $pdf): array
    {
        $contenidos = [];
        $offset = 0;
        $largo = strlen($pdf);

        while ($offset < $largo && ($inicio = strpos($pdf, 'stream', $offset)) !== false) {
            $inicioDatos = $inicio + 6;
            if (($pdf[$inicioDatos] ?? '') === "\r") {
                $inicioDatos++;
            }
            if (($pdf[$inicioDatos] ?? '') === "\n") {
                $inicioDatos++;
            }

            $fin = strpos($pdf, 'endstream', $inicioDatos);
            if ($fin === false) {
                break;
            }

            $datos = substr($pdf, $inicioDatos, $fin - $inicioDatos);
            $dict = substr($pdf, max(0, $inicio - 600), min(600, $inicio));
            $decodificado = $this->decodificarStream(rtrim($datos, "\r\n"), $dict);
            if (is_string($decodificado) && strlen($decodificado) <= 2_000_000) {
                $contenidos[] = $decodificado;
            }

            $offset = $fin + 9;
        }

        return $contenidos;
    }

    /**
     * @return list<array{x: float, y: float, texto: string}>
     */
    private function fragmentosDeContenido(string $contenido): array
    {
        $fragmentos = [];
        $x = 0.0;
        $y = 0.0;
        preg_match_all(
            '/([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+Tm|([+-]?\d+(?:\.\d+)?)\s+([+-]?\d+(?:\.\d+)?)\s+Td|\[(.*?)\]\s*TJ|\((?:\\\\.|[^\\\\)])*\)\s*Tj/s',
            $contenido,
            $coincidencias,
            PREG_SET_ORDER,
        );

        foreach ($coincidencias as $coincidencia) {
            $token = $coincidencia[0];
            if (str_ends_with($token, 'Tm')) {
                $x = (float) $coincidencia[5];
                $y = (float) $coincidencia[6];

                continue;
            }
            if (str_ends_with($token, 'Td')) {
                $x += (float) $coincidencia[7];
                $y += (float) $coincidencia[8];

                continue;
            }

            $texto = str_contains($token, 'TJ')
                ? $this->juntarFragmentos($coincidencia[9])
                : $this->decodificarLiteral($token);
            $texto = trim($texto);
            if ($texto === '') {
                continue;
            }

            $fragmentos[] = ['x' => $x, 'y' => $y, 'texto' => $texto];
        }

        return $fragmentos;
    }

    private function deContenido(string $pdf): string
    {
        $piezas = [];
        foreach ($this->contenidos($pdf) as $contenido) {
            $piezas[] = $this->textoDeContenido($contenido);
        }

        return implode("\n", $piezas);
    }

    private function decodificarStream(string $datos, string $dict): ?string
    {
        if (! str_contains($dict, 'FlateDecode')) {
            return $datos;
        }

        foreach ([
            @gzuncompress($datos),
            @gzinflate($datos),
            strlen($datos) > 2 ? @gzinflate(substr($datos, 2)) : false,
        ] as $intento) {
            if (is_string($intento) && $intento !== '') {
                return $intento;
            }
        }

        return null;
    }

    private function textoDeContenido(string $contenido): string
    {
        $lineas = [];

        if (preg_match_all('/\[(.*?)\]\s*TJ/s', $contenido, $bloques)) {
            foreach ($bloques[1] as $bloque) {
                $lineas[] = $this->juntarFragmentos($bloque);
            }
        }

        if (preg_match_all('/\((?:\\\\.|[^\\\\)])*\)\s*Tj/s', $contenido, $tjs)) {
            foreach ($tjs[0] as $tj) {
                $lineas[] = $this->decodificarLiteral($tj);
            }
        }

        if (preg_match_all('/<([0-9A-Fa-f\s]+)>\s*Tj/', $contenido, $hexes)) {
            foreach ($hexes[1] as $hex) {
                $lineas[] = $this->decodificarHex($hex);
            }
        }

        return implode("\n", array_filter($lineas, fn (string $linea): bool => $linea !== ''));
    }

    private function juntarFragmentos(string $bloque): string
    {
        preg_match_all('/\((?:\\\\.|[^\\\\)])*\)|<([0-9A-Fa-f\s]+)>|(-?\d+(?:\.\d+)?)/', $bloque, $partes, PREG_SET_ORDER);
        $texto = '';

        foreach ($partes as $parte) {
            $token = $parte[0];
            if (str_starts_with($token, '(')) {
                $texto .= $this->decodificarLiteral($token);
            } elseif (str_starts_with($token, '<')) {
                $texto .= $this->decodificarHex(trim($token, "<> \t"));
            } elseif (is_numeric($token) && (float) $token <= -150 && $texto !== '' && ! str_ends_with($texto, ' ')) {
                $texto .= ' ';
            }
        }

        return trim($texto);
    }

    private function decodificarLiteral(string $token): string
    {
        $token = preg_replace('/\s*Tj\s*$/', '', $token) ?? $token;
        $token = trim($token);
        if (str_starts_with($token, '(') && str_ends_with($token, ')')) {
            $token = substr($token, 1, -1);
        }

        $token = preg_replace_callback('/\\\\([nrtbf\\\\()]|[0-7]{1,3})/', function (array $coincidencia): string {
            return match ($coincidencia[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\f",
                '\\' => '\\',
                '(' => '(',
                ')' => ')',
                default => chr(octdec($coincidencia[1])),
            };
        }, $token) ?? $token;

        return $this->aUtf8($token);
    }

    private function decodificarHex(string $hex): string
    {
        $hex = preg_replace('/\s+/', '', $hex) ?? '';
        if ($hex === '' || strlen($hex) % 2 !== 0) {
            return '';
        }

        $bytes = hex2bin($hex);
        if ($bytes === false) {
            return '';
        }

        if (str_starts_with($bytes, "\xFE\xFF")) {
            $utf = mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE');

            return is_string($utf) ? $utf : '';
        }

        return $this->aUtf8($bytes);
    }

    private function aUtf8(string $texto): string
    {
        if ($texto === '' || mb_check_encoding($texto, 'UTF-8')) {
            return $texto;
        }

        $convertido = mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');

        return is_string($convertido) ? $convertido : $texto;
    }

    private function limpiar(string $texto): string
    {
        $texto = str_replace(["\x00", "\u{00A0}"], ['', ' '], $texto);
        $texto = preg_replace("/\r\n|\r/", "\n", $texto) ?? $texto;

        return trim($texto);
    }

    private function tieneLetras(string $texto): bool
    {
        return preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]{4,}/u', $texto) === 1;
    }
}
