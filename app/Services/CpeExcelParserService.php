<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class CpeExcelParserService
{
    private const SPREADSHEET_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELATIONSHIPS_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * Procesa un archivo subido (.xlsx, .csv, .txt) y retorna las filas normalizadas para validación.
     *
     * @param  UploadedFile  $file  Archivo cargado por el usuario
     * @param  string|null  $rucEmisorPorDefecto  RUC a usar si las filas no contienen RUC de emisor (ej. Ventas)
     * @return array{filas: list<array>, total: int, formato_detectado: string}
     */
    public function procesarArchivo(UploadedFile $file, ?string $rucEmisorPorDefecto = null): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $ruta = $file->getRealPath();

        $filasBrutas = [];
        $formato = 'Desconocido';

        if ($extension === 'xlsx') {
            $filasBrutas = $this->extraerFilasXlsx($ruta);
            $formato = 'Excel OpenXML (.xlsx)';
        } elseif (in_array($extension, ['csv', 'txt'])) {
            $filasBrutas = $this->extraerFilasCsv($ruta);
            $formato = 'Texto Delimitado (.csv)';
        } else {
            // Intentar XLSX primero, si falla probar CSV
            try {
                $filasBrutas = $this->extraerFilasXlsx($ruta);
                $formato = 'Excel OpenXML (.xlsx)';
            } catch (Throwable) {
                $filasBrutas = $this->extraerFilasCsv($ruta);
                $formato = 'Texto Delimitado (.csv)';
            }
        }

        return $this->normalizarFilas($filasBrutas, $formato, $rucEmisorPorDefecto);
    }

    /**
     * Extrae filas desde un archivo .xlsx nativo sin librerías pesadas externas.
     *
     * @return list<list<string>>
     */
    public function extraerFilasXlsx(string $rutaArchivo): array
    {
        $zip = new ZipArchive;
        if ($zip->open($rutaArchivo) !== true) {
            return [];
        }

        $sharedStrings = $this->obtenerCadenasCompartidas($zip);
        $hojaXml = $this->obtenerXmlPrimeraHoja($zip);
        $zip->close();

        if ($hojaXml === null) {
            return [];
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($hojaXml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::SPREADSHEET_NS);

        $filas = [];
        $rows = $xpath->query('//m:sheetData/m:row');
        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            $celdas = [];
            $columnas = $xpath->query('./m:c', $row);
            if ($columnas === false) {
                continue;
            }

            foreach ($columnas as $columna) {
                if (! $columna instanceof DOMElement) {
                    continue;
                }

                $indiceColumna = $this->calcularIndiceColumna($columna->getAttribute('r'));
                $valor = $this->extraerValorCelda($xpath, $columna, $sharedStrings);

                if ($indiceColumna !== null) {
                    $celdas[$indiceColumna] = $valor;
                }
            }

            if (empty($celdas)) {
                continue;
            }

            ksort($celdas);
            $maxIndex = max(array_keys($celdas));
            $linea = [];
            for ($i = 0; $i <= $maxIndex; $i++) {
                $linea[] = $celdas[$i] ?? '';
            }

            // Ignorar filas completamente vacías
            if (array_filter($linea, fn ($v) => trim((string) $v) !== '') !== []) {
                $filas[] = $linea;
            }
        }

        return $filas;
    }

    /**
     * Extrae filas desde un archivo CSV con auto-detección de delimitador.
     *
     * @return list<list<string>>
     */
    public function extraerFilasCsv(string $rutaArchivo): array
    {
        $handle = fopen($rutaArchivo, 'r');
        if (! $handle) {
            return [];
        }

        // Leer primera línea para detectar delimitador
        $primeraLinea = fgets($handle);
        rewind($handle);

        $delimitador = ',';
        if ($primeraLinea !== false) {
            $conteos = [
                ';' => substr_count($primeraLinea, ';'),
                ',' => substr_count($primeraLinea, ','),
                "\t" => substr_count($primeraLinea, "\t"),
                '|' => substr_count($primeraLinea, '|'),
            ];
            arsort($conteos);
            $delimitador = key($conteos) ?: ',';
        }

        $filas = [];
        while (($datos = fgetcsv($handle, 0, $delimitador)) !== false) {
            // Limpiar BOM UTF-8 si existe en la primera celda
            if (! empty($datos) && isset($datos[0])) {
                $datos[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $datos[0]);
            }

            if (array_filter($datos, fn ($v) => trim((string) $v) !== '') !== []) {
                $filas[] = array_map('trim', $datos);
            }
        }

        fclose($handle);

        return $filas;
    }

    /**
     * Identifica los encabezados y normaliza cada fila a los campos requeridos por SUNAT.
     */
    protected function normalizarFilas(array $filasBrutas, string $formato, ?string $rucEmisorPorDefecto): array
    {
        if (empty($filasBrutas)) {
            return ['filas' => [], 'total' => 0, 'formato_detectado' => $formato];
        }

        // Encontrar la fila de encabezados (usualmente la fila 0, o en reportes Contasis puede estar en la fila 1 a 4)
        $indiceEncabezado = 0;
        $mapaColumnas = [];

        foreach (array_slice($filasBrutas, 0, 8) as $idx => $fila) {
            $map = $this->identificarColumnas($fila);
            // Si detecta al menos serie y número o serie y ruc
            if (isset($map['numero_serie']) && isset($map['numero'])) {
                $indiceEncabezado = $idx;
                $mapaColumnas = $map;
                break;
            }
        }

        // Si no encontró por keywords, usar mapeo por defecto de posiciones estándar
        if (empty($mapaColumnas)) {
            $mapaColumnas = [
                'num_ruc' => 0,
                'cod_comp' => 1,
                'numero_serie' => 2,
                'numero' => 3,
                'fecha_emision' => 4,
                'monto' => 5,
            ];
            $indiceEncabezado = is_numeric($filasBrutas[0][0] ?? null) ? -1 : 0;
        }

        $filasNormalizadas = [];
        $filasDatos = array_slice($filasBrutas, $indiceEncabezado + 1);

        foreach ($filasDatos as $i => $fila) {
            $rucRaw = $mapaColumnas['num_ruc'] !== null ? ($fila[$mapaColumnas['num_ruc']] ?? '') : '';
            $numRuc = preg_replace('/\D/', '', (string) $rucRaw);

            // Si no hay RUC y tenemos RUC por defecto (ej. Ventas propias)
            if (strlen($numRuc) !== 11 && filled($rucEmisorPorDefecto)) {
                $numRuc = preg_replace('/\D/', '', (string) $rucEmisorPorDefecto);
            }

            $tipoRaw = $mapaColumnas['cod_comp'] !== null ? ($fila[$mapaColumnas['cod_comp']] ?? '01') : '01';
            $codComp = $this->normalizarTipoComprobante($tipoRaw);

            $serieRaw = $mapaColumnas['numero_serie'] !== null ? ($fila[$mapaColumnas['numero_serie']] ?? '') : '';
            $numeroSerie = strtoupper(trim((string) $serieRaw));

            $numeroRaw = $mapaColumnas['numero'] !== null ? ($fila[$mapaColumnas['numero']] ?? '0') : '0';
            $numero = (string) (int) preg_replace('/\D/', '', (string) $numeroRaw);
            if ($numero === '0' && filled($numeroRaw)) {
                $numero = trim((string) $numeroRaw);
            }

            $fechaRaw = $mapaColumnas['fecha_emision'] !== null ? ($fila[$mapaColumnas['fecha_emision']] ?? '') : '';
            $fechaEmision = (new SunatValidezCpeService)->normalizarFechaSunat($fechaRaw);

            $montoRaw = $mapaColumnas['monto'] !== null ? ($fila[$mapaColumnas['monto']] ?? '0') : '0';
            $montoFloat = (float) str_replace(',', '', (string) $montoRaw);
            $monto = number_format($montoFloat, 2, '.', '');

            $razonSocial = $mapaColumnas['razon_social'] !== null ? trim((string) ($fila[$mapaColumnas['razon_social']] ?? '')) : '';

            // Solo agregar filas que tengan al menos serie o número
            if (filled($numeroSerie) && filled($numero)) {
                $filasNormalizadas[] = [
                    'item' => count($filasNormalizadas) + 1,
                    'numRuc' => $numRuc,
                    'razonSocial' => $razonSocial,
                    'codComp' => $codComp,
                    'numeroSerie' => $numeroSerie,
                    'numero' => $numero,
                    'fechaEmision' => $fechaEmision,
                    'monto' => $monto,
                    'estado_validacion' => 'PENDIENTE',
                ];
            }
        }

        return [
            'filas' => $filasNormalizadas,
            'total' => count($filasNormalizadas),
            'formato_detectado' => $formato,
        ];
    }

    /**
     * Mapea nombres de columnas con tolerancia a variantes de software contable (New Contasis, SIRE, Concar).
     */
    protected function identificarColumnas(array $encabezados): array
    {
        $map = [
            'num_ruc' => null,
            'cod_comp' => null,
            'numero_serie' => null,
            'numero' => null,
            'fecha_emision' => null,
            'monto' => null,
            'razon_social' => null,
        ];

        foreach ($encabezados as $idx => $texto) {
            $col = Str::slug(trim((string) $texto), '_');

            // RUC Emisor
            if ($map['num_ruc'] === null) {
                if (in_array($col, ['ruc', 'ruc_emisor', 'ruc_proveedor', 'num_ruc', 'documento_proveedor', 'nro_ruc', 'ruc_del_emisor'])) {
                    $map['num_ruc'] = $idx;
                }
            }

            // Tipo Comprobante
            if ($map['cod_comp'] === null) {
                if (in_array($col, ['td', 'tipo', 'tipo_comp', 'tipo_comprobante', 'cod_comp', 'tipo_doc', 'codigo_comprobante', 'cd'])) {
                    $map['cod_comp'] = $idx;
                }
            }

            // Serie
            if ($map['numero_serie'] === null) {
                if (in_array($col, ['serie', 'ser', 'numero_serie', 'num_serie', 'nro_serie'])) {
                    $map['numero_serie'] = $idx;
                }
            }

            // Número / Correlativo
            if ($map['numero'] === null) {
                if (in_array($col, ['numero', 'num', 'correlativo', 'nro_doc', 'nro_comprobante', 'nro', 'numero_comprobante', 'numero_documento'])) {
                    $map['numero'] = $idx;
                }
            }

            // Fecha Emisión
            if ($map['fecha_emision'] === null) {
                if (in_array($col, ['fecha', 'fecha_emision', 'fec_emi', 'fec_emision', 'fecha_comp', 'fec_doc', 'fecha_de_emision'])) {
                    $map['fecha_emision'] = $idx;
                }
            }

            // Monto / Total
            if ($map['monto'] === null) {
                if (in_array($col, ['total', 'monto', 'importe', 'importe_total', 'precio_venta', 'total_compra', 'total_venta'])) {
                    $map['monto'] = $idx;
                }
            }

            // Razón Social
            if ($map['razon_social'] === null) {
                if (in_array($col, ['razon_social', 'proveedor', 'nombre_o_razon_social', 'apellidos_y_nombres', 'cliente_razon_social'])) {
                    $map['razon_social'] = $idx;
                }
            }
        }

        return $map;
    }

    /**
     * Normaliza los códigos de tipo de comprobante (convierte texto o números sueltos a códigos SUNAT).
     */
    public function normalizarTipoComprobante(mixed $valor): string
    {
        $v = trim((string) $valor);
        $limpio = preg_replace('/\D/', '', $v);

        if ($limpio === '1') {
            return '01';
        }
        if ($limpio === '3') {
            return '03';
        }
        if ($limpio === '7') {
            return '07';
        }
        if ($limpio === '8') {
            return '08';
        }
        if ($limpio === '9') {
            return '09';
        }

        if (strlen($limpio) === 2) {
            return $limpio;
        }

        $upper = strtoupper($v);
        if (str_contains($upper, 'FACTURA')) {
            return '01';
        }
        if (str_contains($upper, 'BOLETA')) {
            return '03';
        }
        if (str_contains($upper, 'CREDITO') || str_contains($upper, 'CRÉDITO')) {
            return '07';
        }
        if (str_contains($upper, 'DEBITO') || str_contains($upper, 'DÉBITO')) {
            return '08';
        }
        if (str_contains($upper, 'GUIA') || str_contains($upper, 'GUÍA')) {
            return '09';
        }
        if (str_contains($upper, 'HONORARIO')) {
            return 'R1';
        }

        return filled($v) ? str_pad($v, 2, '0', STR_PAD_LEFT) : '01';
    }

    /**
     * Genera la plantilla oficial descargable en formato CSV con delimitador coma y UTF-8 con BOM.
     */
    public function generarPlantillaCsv(): string
    {
        $salida = fopen('php://memory', 'w+');
        // Escribir BOM UTF-8 para apertura limpia en Excel
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, [
            'RUC Emisor',
            'Razón Social (Opcional)',
            'Tipo CPE (01=Factura, 03=Boleta, 07=NC, 08=ND)',
            'Serie',
            'Número',
            'Fecha Emisión (DD/MM/AAAA)',
            'Importe Total',
        ]);

        // Filas de ejemplo real
        fputcsv($salida, ['20100047218', 'BANCO DE CREDITO DEL PERU', '01', 'F001', '1245', now()->format('d/m/Y'), '1500.00']);
        fputcsv($salida, ['20504787010', 'TELEFONICA DEL PERU S.A.A.', '01', 'F002', '5890', now()->subDays(3)->format('d/m/Y'), '245.50']);
        fputcsv($salida, ['20100070970', 'SUPERMERCADOS PERUANOS S.A.', '03', 'B001', '9843', now()->subDays(7)->format('d/m/Y'), '89.90']);

        rewind($salida);
        $contenido = stream_get_contents($salida);
        fclose($salida);

        return $contenido ?: '';
    }

    /**
     * Genera el reporte CSV exportable con los resultados completos de la auditoría y diagnóstico tributario.
     *
     * @param  list<array>  $resultados
     */
    public function exportarResultadosCsv(array $resultados, string $razonSocialConsultante = ''): string
    {
        $salida = fopen('php://memory', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");

        // Fila de Encabezado Principal
        fputcsv($salida, [
            'Item',
            'RUC Emisor',
            'Razón Social',
            'Tipo CPE',
            'Serie',
            'Número',
            'Fecha Emisión',
            'Importe Total',
            'Estado SUNAT (CPE)',
            'Estado RUC',
            'Condición Domicilio',
            'Diagnóstico Tributario',
            '¿Apto Crédito Fiscal?',
            'Observaciones SUNAT',
            'Fecha Validación',
        ]);

        foreach ($resultados as $idx => $item) {
            $comp = $item['comprobante'] ?? [];
            $estadoCp = $item['estado_cp']['label'] ?? 'NO INFORMADO';
            $estadoRuc = $item['estado_ruc']['label'] ?? '—';
            $condDomi = $item['condicion_domicilio']['label'] ?? '—';
            $diagnostico = $item['diagnostico'] ?? '';
            $apto = ($item['es_valido_credito_fiscal'] ?? false) ? 'SÍ' : 'NO';
            $obs = implode(' | ', (array) ($item['observaciones'] ?? []));
            $fechaVal = isset($item['consultado_at']) ? substr($item['consultado_at'], 0, 19) : now()->format('d/m/Y H:i:s');

            fputcsv($salida, [
                $idx + 1,
                $comp['numRuc'] ?? '',
                $item['razonSocial'] ?? '',
                $comp['codComp'] ?? '',
                $comp['numeroSerie'] ?? '',
                $comp['numero'] ?? '',
                $comp['fechaEmision'] ?? '',
                $comp['monto'] ?? '',
                $estadoCp,
                $estadoRuc,
                $condDomi,
                $diagnostico,
                $apto,
                $obs,
                $fechaVal,
            ]);
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return $csv ?: '';
    }

    /* ══════════════════════════ MÉTODOS AUXILIARES OPENXML ══════════════════════════ */

    private function obtenerCadenasCompartidas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $dom = new DOMDocument;
        if (! @$dom->loadXML($xml)) {
            return [];
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::SPREADSHEET_NS);

        $lista = [];
        $items = $xpath->query('//m:si');
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            $partes = $xpath->query('.//m:t', $item);
            $texto = '';
            if ($partes !== false) {
                foreach ($partes as $parte) {
                    $texto .= $parte->textContent;
                }
            }
            $lista[] = $texto;
        }

        return $lista;
    }

    private function obtenerXmlPrimeraHoja(ZipArchive $zip): ?string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro === false || $relaciones === false) {
            return null;
        }

        $domLibro = new DOMDocument;
        $domRels = new DOMDocument;
        if (! @$domLibro->loadXML($libro) || ! @$domRels->loadXML($relaciones)) {
            return null;
        }

        $xpathLibro = new DOMXPath($domLibro);
        $xpathLibro->registerNamespace('m', self::SPREADSHEET_NS);

        $hojas = $xpathLibro->query('//m:sheet');
        if ($hojas === false || $hojas->length === 0) {
            return null;
        }

        $primeraHoja = $hojas->item(0);
        if (! $primeraHoja instanceof DOMElement) {
            return null;
        }

        $rId = $primeraHoja->getAttributeNS(self::RELATIONSHIPS_NS, 'id');

        $xpathRels = new DOMXPath($domRels);
        $xpathRels->registerNamespace('pr', 'http://schemas.openxmlformats.org/package/2006/relationships');

        $destinos = $xpathRels->query('//pr:Relationship[@Id="'.$rId.'"]');
        if ($destinos === false || ! $destinos->item(0) instanceof DOMElement) {
            return null;
        }

        $target = $destinos->item(0)->getAttribute('Target');
        $target = ltrim(str_replace('\\', '/', $target), '/');
        if (! str_starts_with($target, 'xl/')) {
            $target = 'xl/'.$target;
        }

        $xml = $zip->getFromName($target);

        return $xml === false ? null : $xml;
    }

    private function extraerValorCelda(DOMXPath $xpath, DOMElement $columna, array $sharedStrings): string
    {
        $tipo = $columna->getAttribute('t');

        if ($tipo === 's') {
            $nodoV = $xpath->query('./m:v', $columna);
            if ($nodoV !== false && $nodoV->length > 0) {
                $idx = (int) $nodoV->item(0)->textContent;

                return $sharedStrings[$idx] ?? '';
            }
        } elseif ($tipo === 'inlineStr') {
            $nodoT = $xpath->query('./m:is/m:t', $columna);
            if ($nodoT !== false && $nodoT->length > 0) {
                return $nodoT->item(0)->textContent;
            }
        } else {
            $nodoV = $xpath->query('./m:v', $columna);
            if ($nodoV !== false && $nodoV->length > 0) {
                return $nodoV->item(0)->textContent;
            }
        }

        return '';
    }

    private function calcularIndiceColumna(string $referencia): ?int
    {
        if (preg_match('/^([A-Z]+)/', strtoupper($referencia), $m)) {
            $letras = $m[1];
            $len = strlen($letras);
            $indice = 0;
            for ($i = 0; $i < $len; $i++) {
                $indice = $indice * 26 + (ord($letras[$i]) - 64);
            }

            return $indice - 1;
        }

        return null;
    }
}
