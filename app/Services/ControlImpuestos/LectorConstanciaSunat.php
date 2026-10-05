<?php

namespace App\Services\ControlImpuestos;

/**
 * Lee la constancia de presentación y el detalle de casillas del PDT 621 y del PDT 601.
 *
 * Las casillas siguen el formulario virtual vigente (ayuda SUNAT del FV 621):
 * 171 y 168 son percepciones, 179 y 176 son retenciones, 189 es el importe a pagar del IGV,
 * 303 es el saldo a favor de renta y 328 es el crédito ITAN.
 */
final class LectorConstanciaSunat
{
    /**
     * Casillas del FV 621 que esta hoja sabe interpretar.
     *
     * @var list<string>
     */
    private const CASILLAS = [
        '100', '101', '102', '103', '105', '106', '107', '108', '109', '110', '111',
        '114', '115', '140', '145', '154', '155', '156', '157', '160', '164', '165', '168', '171',
        '176', '179', '184', '185', '187', '188', '189', '301', '302', '303', '304',
        '305', '306', '307', '312', '317', '319', '324', '326', '327', '328',
    ];

    /**
     * @var list<string>
     */
    private const TRIBUTOS = [
        '1011', '1016', '1041', '3031', '3042', '3052', '3081', '3111', '3121',
        '5210', '5310', '5355', '5361', '5442', '8011', '8073',
    ];

    /**
     * @param  list<string>  $codigosAdicionales
     */
    public function leer(string $texto, array $codigosAdicionales = []): DeclaracionLeida
    {
        $declaracion = new DeclaracionLeida;
        $normal = $this->normalizar($texto);
        if (trim($normal) === '') {
            $declaracion->legible = false;

            return $declaracion;
        }

        $declaracion->esBoleta = $this->esBoleta($normal);
        $declaracion->formulario = $this->formulario($normal);
        $declaracion->ruc = $this->ruc($normal);
        $declaracion->razonSocial = $this->razonSocial($normal);
        $declaracion->periodo = $this->periodo($normal);
        $declaracion->numeroOrden = $this->numeroOrden($normal);
        $declaracion->fechaPresentacion = $this->fechaPresentacion($normal);
        $declaracion->tipo = $this->tipo($normal);
        [$declaracion->casillas, $declaracion->casillasRepetidas] = $this->casillas($normal);
        [$declaracion->tributos, $declaracion->essaludBase, $declaracion->renta4taBase, $declaracion->tributosRepetidos] = $this->tributos($normal, $codigosAdicionales);
        $declaracion->afp = $this->afp($normal);
        $declaracion->igvJusto = preg_match('/IGV\s+JUSTO.{0,80}SI\/NO\)?\??\s*SI\b/', $normal) === 1;
        $declaracion->essaludBase ??= $this->baseEtiquetada($normal, 'REMUNERACION ASEGURABLE|BASE(?:\s+IMPONIBLE)?\s+ESSALUD');
        $declaracion->renta4taBase ??= $this->baseEtiquetada($normal, 'BASE(?:\s+IMPONIBLE)?\s+(?:DE\s+)?(?:CUARTA|4TA)|HONORARIOS');
        $this->inferirFormulario($declaracion);
        $declaracion->tieneDetalle = $declaracion->casillas !== []
            || $declaracion->essaludBase !== null
            || $declaracion->renta4taBase !== null;

        if ($declaracion->formulario === null && ! $declaracion->esBoleta && $declaracion->tributos === [] && $declaracion->casillas === []) {
            $declaracion->legible = false;
        }

        return $declaracion;
    }

    /**
     * @param  list<DeclaracionLeida>  $declaraciones
     */
    public function fusionar(array $declaraciones): DeclaracionLeida
    {
        $base = new DeclaracionLeida;
        $prioridad = ['original' => 1, 'sustitutoria' => 2, 'rectificatoria' => 3];

        foreach ($declaraciones as $declaracion) {
            if ($declaracion->esBoleta && $declaracion->formulario === null) {
                $base->boletasIgnoradas++;

                continue;
            }

            if ($declaracion->formulario !== null) {
                if ($base->formulario === null) {
                    $base->formulario = $declaracion->formulario;
                } elseif ($base->formulario !== $declaracion->formulario) {
                    $base->formulariosMixtos = true;
                }
            }

            $base->ruc = $this->combinarIdentidad($base->ruc, $declaracion->ruc, $base->rucConflicto);
            $base->periodo = $this->combinarIdentidad($base->periodo, $declaracion->periodo, $base->periodoConflicto);
            $base->numeroOrden = $this->combinarIdentidad($base->numeroOrden, $declaracion->numeroOrden, $base->ordenConflicto);
            $base->razonSocial ??= $declaracion->razonSocial;
            $base->fechaPresentacion ??= $declaracion->fechaPresentacion;

            if (($prioridad[$declaracion->tipo] ?? 0) > ($prioridad[$base->tipo] ?? 0)) {
                $base->tipo = $declaracion->tipo;
            }

            foreach ($declaracion->casillas as $casilla => $monto) {
                if (isset($base->casillas[$casilla]) && ! MontoTributario::iguales($base->casillas[$casilla], $monto)) {
                    $base->casillasRepetidas[] = $casilla;
                }
                $base->casillas[$casilla] = $monto;
            }

            foreach ($declaracion->tributos as $codigo => $monto) {
                if (isset($base->tributos[$codigo]) && ! MontoTributario::iguales($base->tributos[$codigo], $monto)) {
                    $base->tributosRepetidos[] = $codigo;
                }
                $base->tributos[$codigo] = $monto;
            }

            $base->afp ??= $declaracion->afp;
            $base->igvJusto = $base->igvJusto || $declaracion->igvJusto;
            $base->essaludBase ??= $declaracion->essaludBase;
            $base->renta4taBase ??= $declaracion->renta4taBase;
            $base->tieneDetalle = $base->tieneDetalle || $declaracion->tieneDetalle;
            $base->casillasRepetidas = array_values(array_unique([...$base->casillasRepetidas, ...$declaracion->casillasRepetidas]));
            $base->tributosRepetidos = array_values(array_unique([...$base->tributosRepetidos, ...$declaracion->tributosRepetidos]));
        }

        if ($base->formulario === null && $base->boletasIgnoradas > 0 && $declaraciones !== []) {
            $base->esBoleta = true;
        }

        return $base;
    }

    private function combinarIdentidad(?string $actual, ?string $nuevo, bool &$conflicto): ?string
    {
        if ($nuevo === null || $nuevo === '') {
            return $actual;
        }

        if ($actual !== null && $actual !== $nuevo) {
            $conflicto = true;
        }

        return $actual ?? $nuevo;
    }

    private function normalizar(string $texto): string
    {
        $texto = str_replace(["\u{00A0}", "\t", "\x00"], ' ', $texto);
        $texto = mb_strtoupper($texto, 'UTF-8');
        $texto = strtr($texto, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
            'º' => ' ', '°' => ' ',
        ]);
        $texto = preg_replace('/[ ]+/', ' ', $texto) ?? $texto;

        return trim($texto);
    }

    private function esBoleta(string $texto): bool
    {
        $pareceBoleta = preg_match('/BOLETA\s+DE\s+PAGO|FORMULARIO\s+1662|FORMULARIO\s+VIRTUAL\s+N\s*1662/', $texto) === 1;
        $pareceDeclaracion = preg_match('/CONSTANCIA\s+DE\s+PRESENTACION|DETALLE\s+DE\s+(?:LA\s+)?DECLARACION|CASILLA\s+100|\bPDT\s*621\b|\bPLAME\b|\b0601\b|\b0621\b/', $texto) === 1;

        return $pareceBoleta && ! $pareceDeclaracion;
    }

    private function formulario(string $texto): ?string
    {
        $senal621 = preg_match_all('/\b0621\b|\bPDT\s*621\b|IGV\s*-?\s*RENTA|FORMULARIO(?:\s+VIRTUAL)?\s+N\s*0*621\b/', $texto);
        $senal601 = preg_match_all('/\b0601\b|\bPDT\s*601\b|\bPLAME\b|PLANILLA\s+ELECTRONICA|FORMULARIO(?:\s+VIRTUAL)?\s+N\s*0*601\b/', $texto);

        if ($senal621 === 0 && $senal601 === 0) {
            return null;
        }

        if ($senal601 > $senal621) {
            return '601';
        }

        if ($senal621 > $senal601) {
            return '621';
        }

        $cabeza = substr($texto, 0, 800);

        return preg_match('/\b0601\b|PLAME|PLANILLA/', $cabeza) === 1
            && preg_match('/\b0621\b|IGV/', $cabeza) !== 1
            ? '601'
            : '621';
    }

    private function inferirFormulario(DeclaracionLeida $declaracion): void
    {
        if ($declaracion->formulario !== null) {
            return;
        }

        $codigos = array_keys($declaracion->tributos);
        if (array_intersect($codigos, ['5210', '5310', '3042', '3052']) !== []) {
            $declaracion->formulario = '601';

            return;
        }

        if ($declaracion->casillas !== [] || array_intersect($codigos, ['1011', '1016', '3111', '3121', '3031']) !== []) {
            $declaracion->formulario = '621';
        }
    }

    private function ruc(string $texto): ?string
    {
        if (preg_match('/\bRUC\b(?:\s+RUC)?\s*[:\-]?\s*(\d{11})\b/', $texto, $coincidencia) !== 1) {
            return null;
        }

        return $coincidencia[1];
    }

    private function razonSocial(string $texto): ?string
    {
        if (preg_match('/RAZON\s+SOCIAL\s*[:\-]?\s*(.+?)(?=\s+(?:PERIODO|NUMERO|NRO|FECHA|TIPO|FORMULARIO|RUC)\b|$)/', $texto, $coincidencia) !== 1) {
            return null;
        }

        $razon = trim($coincidencia[1]);

        return $razon === '' ? null : mb_substr($razon, 0, 255);
    }

    private function periodo(string $texto): ?string
    {
        if (preg_match('/PERIODO(?:\s+TRIBUTARIO)?\s*[:\-]?\s*(\d{2})\s*[\/\-]\s*(20\d{2})/', $texto, $coincidencia) === 1) {
            return $this->periodoValido((int) $coincidencia[1], $coincidencia[2]);
        }

        if (preg_match('/PERIODO(?:\s+TRIBUTARIO)?(?:\s+[A-Z]+){0,3}\s*[:\-]?\s*(20\d{2})\s*(\d{2})\b/', $texto, $coincidencia) === 1) {
            return $this->periodoValido((int) $coincidencia[2], $coincidencia[1]);
        }

        $meses = [
            'ENERO' => 1, 'FEBRERO' => 2, 'MARZO' => 3, 'ABRIL' => 4, 'MAYO' => 5, 'JUNIO' => 6,
            'JULIO' => 7, 'AGOSTO' => 8, 'SETIEMBRE' => 9, 'SEPTIEMBRE' => 9, 'OCTUBRE' => 10,
            'NOVIEMBRE' => 11, 'DICIEMBRE' => 12,
        ];

        if (preg_match('/PERIODO(?:\s+TRIBUTARIO)?\s*[:\-]?\s*([A-Z]+)\s+(20\d{2})/', $texto, $coincidencia) === 1) {
            $mes = $meses[$coincidencia[1]] ?? null;

            return $mes === null ? null : sprintf('%02d-%s', $mes, $coincidencia[2]);
        }

        return null;
    }

    private function periodoValido(int $mes, string $anio): ?string
    {
        if ($mes < 1 || $mes > 12) {
            return null;
        }

        return sprintf('%02d-%s', $mes, $anio);
    }

    private function numeroOrden(string $texto): ?string
    {
        if (preg_match('/\b(?:NUMERO\s+DE\s+ORDEN|NRO\.?\s+DE\s+ORDEN|NRO\.?\s+ORDEN|N\s*ORDEN)(?:\s+(?:NUMERO\s+DE\s+ORDEN|NRO\.?\s+DE\s+ORDEN))?\s*[:\-]?\s*(\d{6,15})\b/', $texto, $coincidencia) !== 1) {
            return null;
        }

        return $coincidencia[1];
    }

    private function fechaPresentacion(string $texto): ?string
    {
        if (preg_match('/\bFECHA(?:\s+Y\s+HORA)?\s+DE\s+PRESENTACION(?:\s+FECHA(?:\s+Y\s+HORA)?\s+DE\s+PRESENTACION)?\s*[:\-]?\s*(\d{2})\/(\d{2})\/(\d{4})(?:\s+(\d{2}:\d{2}(?::\d{2})?))?/', $texto, $coincidencia) !== 1) {
            return null;
        }

        $hora = $coincidencia[4] ?? '00:00:00';
        if (strlen($hora) === 5) {
            $hora .= ':00';
        }

        $fecha = sprintf('%s-%s-%s %s', $coincidencia[3], $coincidencia[2], $coincidencia[1], $hora);
        $momento = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $fecha);

        return $momento === false ? null : $momento->format('Y-m-d H:i:s');
    }

    private function tipo(string $texto): string
    {
        if (str_contains($texto, 'RECTIFICATORIA')) {
            return 'rectificatoria';
        }

        if (str_contains($texto, 'SUSTITUTORIA')) {
            return 'sustitutoria';
        }

        return 'original';
    }

    /**
     * @return array{0: array<string, float>, 1: list<string>}
     */
    private function casillas(string $texto): array
    {
        $casillas = [];
        $repetidas = [];
        $monto = MontoTributario::regex();
        $plano = preg_replace("/\R/u", ' ', $texto) ?? $texto;

        $etiqueta = $this->tokenEtiqueta(false);
        foreach (self::CASILLAS as $numero) {
            $patron = '/\b(?:CASILLA\s+|CAS\.\s+)?'.$numero.'\b'.$etiqueta.'{0,12}\s+('.$monto.')/';
            if (preg_match_all($patron, $plano, $coincidencias) < 1) {
                continue;
            }

            $valores = [];
            foreach ($coincidencias[1] as $crudo) {
                $valor = MontoTributario::parse($crudo);
                if ($valor !== null) {
                    $valores[] = $valor;
                }
            }

            if ($valores === []) {
                continue;
            }

            $distintos = array_unique(array_map(fn (float $valor): string => number_format($valor, 2, '.', ''), $valores));
            if (count($distintos) > 1) {
                $repetidas[] = $numero;
            }

            $casillas[$numero] = (float) end($valores);
        }

        if (preg_match('/\b315\b\s+(\d+[.,]\d{1,4})\b/', $plano, $tasa) === 1) {
            $casillas['315'] = round((float) str_replace(',', '.', $tasa[1]), 4);
        }

        return [$casillas, $repetidas];
    }

    /**
     * @param  list<string>  $codigosAdicionales
     * @return array{0: array<string, float>, 1: ?float, 2: ?float, 3: list<string>}
     */
    private function tributos(string $texto, array $codigosAdicionales = []): array
    {
        $tributos = [];
        $repetidos = [];
        $essaludBase = null;
        $renta4taBase = null;
        $codigos = implode('|', $this->codigosTributo($codigosAdicionales));
        $monto = MontoTributario::regexTributo();
        $plano = preg_replace("/\R/u", ' ', $texto) ?? $texto;
        $deudaEsLaPrimera = str_contains($plano, 'TOTAL DEUDA') && str_contains($plano, 'MONTO PAGO');
        $patron = '/\b('.$codigos.')\b'.$this->tokenEtiqueta(true).'{0,16}((?:\s+'.$monto.'){1,3})/';

        if (preg_match_all($patron, $plano, $coincidencias, PREG_SET_ORDER) < 1) {
            return [$tributos, null, null, []];
        }

        foreach ($coincidencias as $coincidencia) {
            $codigo = $coincidencia[1];
            preg_match_all('/'.$monto.'/', $coincidencia[2], $montos);
            $valores = [];
            foreach ($montos[0] as $crudo) {
                $valor = MontoTributario::parse($crudo);
                if ($valor !== null) {
                    $valores[] = $valor;
                }
            }

            if ($valores === []) {
                continue;
            }

            $pagar = $deudaEsLaPrimera ? (float) $valores[0] : (float) end($valores);
            if (isset($tributos[$codigo]) && ! MontoTributario::iguales($tributos[$codigo], $pagar)) {
                $repetidos[] = $codigo;
            }
            $tributos[$codigo] = $pagar;

            if (! $deudaEsLaPrimera && count($valores) >= 2 && $valores[0] > $pagar) {
                if ($codigo === '5210') {
                    $essaludBase = $valores[0];
                }
                if ($codigo === '3042') {
                    $renta4taBase = $valores[0];
                }
            }
        }

        return [$tributos, $essaludBase, $renta4taBase, array_values(array_unique($repetidos))];
    }

    private function afp(string $texto): ?float
    {
        $monto = MontoTributario::regex();
        if (preg_match('/\b(?:TOTAL\s+AFP|AFP\s+NETO|APORTES?\s+AFP)\b'.$this->tokenEtiqueta(true).'{0,12}\s+('.$monto.')/', $texto, $coincidencia) === 1) {
            return MontoTributario::parse($coincidencia[1]);
        }

        $suma = 0.0;
        $hay = false;
        $lineas = preg_split("/\R/u", $texto) ?: [];
        foreach ($lineas as $linea) {
            if (preg_match('/APORTE\s+OBLIGATORIO|COMISION|PRIMA\s+DE\s+SEGURO/', $linea) !== 1 || preg_match('/\bAFP\b/', $linea) !== 1) {
                continue;
            }
            if (preg_match('/('.$monto.')/', $linea, $coincidencia) !== 1) {
                continue;
            }
            $valor = MontoTributario::parse($coincidencia[1]);
            if ($valor === null) {
                continue;
            }
            $suma += $valor;
            $hay = true;
        }

        return $hay ? round($suma, 2) : null;
    }

    /**
     * Palabra de la denominación o un guion. En la constancia también un número de ley.
     * La casilla no salta enteros: el número siguiente es otra casilla, no parte del rótulo.
     */
    private function tokenEtiqueta(bool $conEnteros): string
    {
        $palabra = '(?!S\\/)(?!\\d+[.,]\\d)(?=[A-Z0-9]*[A-Z])[A-Z0-9.\\/+\\-()%]+';
        $guion = '[-–—]';
        $opciones = $palabra.'|'.$guion;
        if ($conEnteros) {
            $opciones .= '|(?!\\d+[.,])\\d+';
        }

        return '(?:\\s+(?:'.$opciones.'))';
    }

    /**
     * @param  list<string>  $adicionales
     * @return list<string>
     */
    private function codigosTributo(array $adicionales): array
    {
        $codigos = self::TRIBUTOS;
        foreach ($adicionales as $codigo) {
            $codigo = strtoupper(trim((string) $codigo));
            if (preg_match('/^[A-Z0-9]{3,10}$/', $codigo) === 1) {
                $codigos[] = $codigo;
            }
        }

        $codigos = array_values(array_unique($codigos));
        usort($codigos, fn (string $a, string $b): int => strlen($b) <=> strlen($a) ?: $a <=> $b);

        return $codigos;
    }

    private function baseEtiquetada(string $texto, string $etiqueta): ?float
    {
        $monto = MontoTributario::regex();
        $plano = preg_replace("/\R/u", ' ', $texto) ?? $texto;
        if (preg_match('/(?:'.$etiqueta.')(?:\s+[A-Z0-9\.\/\+\-\(\)%]{2,}){0,6}\s+('.$monto.')/', $plano, $coincidencia) !== 1) {
            return null;
        }

        return MontoTributario::parse($coincidencia[1]);
    }
}
