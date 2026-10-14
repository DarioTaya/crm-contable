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

        // Encontrar la fila de encabezados examinando hasta las primeras 25 filas
        // (los reportes de New Contasis suelen tener 6-8 filas de título/período antes de los encabezados)
        $indiceEncabezado = -1;
        $mapaColumnas = [];

        foreach (array_slice($filasBrutas, 0, 25) as $idx => $fila) {
            $map = $this->identificarColumnas($fila);
            $tieneNumero = isset($map['numero']) || isset($map['numero_serie']);
            $tieneIdentificador = isset($map['num_ruc']) || isset($map['fecha_emision']) || isset($map['cod_comp']);

            if ($tieneNumero && $tieneIdentificador) {
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
                'razon_social' => null,
            ];
            $indiceEncabezado = is_numeric($filasBrutas[0][0] ?? null) ? -1 : 0;
        }

        $filasNormalizadas = [];
        $filasDatos = array_slice($filasBrutas, $indiceEncabezado + 1);

        foreach ($filasDatos as $fila) {
            // Descartar filas de totales, resúmenes o metadatos de software contable (ej. "TOTAL REGISTRO:", "Generado automáticamente...")
            $filaTexto = strtoupper(implode(' ', array_map('strval', $fila)));
            if (
                str_contains($filaTexto, 'TOTAL REGISTRO') ||
                str_contains($filaTexto, 'TOTAL GENERAL') ||
                str_contains($filaTexto, 'RESUMEN') ||
                str_contains($filaTexto, 'GENERADO AUTOM') ||
                str_contains($filaTexto, 'NEWCONTASIS') ||
                str_contains($filaTexto, 'VAN :') ||
                str_contains($filaTexto, 'VIENEN :')
            ) {
                continue;
            }

            // RUC Emisor / Proveedor
            $rucRaw = $mapaColumnas['num_ruc'] !== null ? ($fila[$mapaColumnas['num_ruc']] ?? '') : '';
            $numRuc = preg_replace('/\D/', '', (string) $rucRaw);

            // Si no hay RUC o es inválido y se proveyó RUC por defecto (ej. en Registro de Ventas)
            if (strlen($numRuc) !== 11 && strlen($numRuc) !== 8 && filled($rucEmisorPorDefecto)) {
                $numRuc = preg_replace('/\D/', '', (string) $rucEmisorPorDefecto);
            }

            // Tipo de Comprobante (01=Factura, 03=Boleta, 07=NC, 08=ND, R1/02=Honorarios)
            $tipoRaw = $mapaColumnas['cod_comp'] !== null ? ($fila[$mapaColumnas['cod_comp']] ?? '01') : '01';
            $codComp = $this->normalizarTipoComprobante($tipoRaw);

            // Serie y Número Correlativo
            $serieRaw = $mapaColumnas['numero_serie'] !== null ? trim((string) ($fila[$mapaColumnas['numero_serie']] ?? '')) : '';
            $numeroRaw = $mapaColumnas['numero'] !== null ? trim((string) ($fila[$mapaColumnas['numero']] ?? '')) : '';

            // Caso New Contasis / Concar: Si la columna 'NUMERO' contiene Serie y Correlativo juntos con guion
            // Ejemplo: "00F007-0000000472035" o "00FE02-0000000005733" o "F001-1245"
            if (empty($serieRaw) && str_contains($numeroRaw, '-')) {
                $partes = explode('-', $numeroRaw, 2);
                $serieRaw = trim($partes[0]);
                $numeroRaw = trim($partes[1]);
            } elseif (str_contains($serieRaw, '-') && empty($numeroRaw)) {
                $partes = explode('-', $serieRaw, 2);
                $serieRaw = trim($partes[0]);
                $numeroRaw = trim($partes[1]);
            }

            // Normalizar Serie:
            // En New Contasis las series electrónicas vienen con padding a 6 caracteres: "00F007", "00E001", "00FE02", "00FF01".
            // Una serie de CPE en SUNAT es de exactamente 4 caracteres alfanuméricos (ej: F007, E001, FE02, B001, 0001).
            $numeroSerie = strtoupper(trim($serieRaw));
            if (strlen($numeroSerie) > 4) {
                if (preg_match('/^0+([A-Z0-9]{4})$/', $numeroSerie, $matches)) {
                    $numeroSerie = $matches[1];
                } elseif (preg_match('/^0+([A-Z0-9]{1,4})$/', $numeroSerie, $matches)) {
                    $numeroSerie = str_pad($matches[1], 4, '0', STR_PAD_LEFT);
                }
            }

            // Normalizar Correlativo:
            // En New Contasis viene con padding de ceros a la izquierda (ej: "0000000472035").
            // SUNAT exige el número de comprobante sin ceros a la izquierda innecesarios (ej: "472035").
            $numeroLimpio = ltrim(preg_replace('/\D/', '', $numeroRaw), '0');
            $numero = $numeroLimpio !== '' ? $numeroLimpio : '0';

            // Fecha de Emisión (normalizada a DD/MM/AAAA para SUNAT)
            $fechaRaw = $mapaColumnas['fecha_emision'] !== null ? ($fila[$mapaColumnas['fecha_emision']] ?? '') : '';
            $fechaEmision = (new SunatValidezCpeService)->normalizarFechaSunat($fechaRaw);

            // Importe Total (Moneda Original):
            // En Notas de Crédito, New Contasis registra importes en negativo (-260.00). SUNAT requiere valor positivo.
            $montoRaw = $mapaColumnas['monto'] !== null ? ($fila[$mapaColumnas['monto']] ?? '0') : '0';
            $montoFloat = abs((float) str_replace(',', '', (string) $montoRaw));
            $monto = number_format($montoFloat, 2, '.', '');

            // Razón Social / Denominación del emisor
            $razonSocial = $mapaColumnas['razon_social'] !== null ? trim((string) ($fila[$mapaColumnas['razon_social']] ?? '')) : '';

            // Solo agregar filas válidas con serie, número y RUC formal
            if (filled($numeroSerie) && filled($numero) && $numero !== '0' && (strlen($numRuc) === 11 || strlen($numRuc) === 8)) {
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

            // RUC Emisor (Soporta 'R.U.C.', 'RUC', 'RUC Emisor', 'RUC Proveedor')
            if ($map['num_ruc'] === null) {
                if (in_array($col, [
                    'ruc', 'r_u_c', 'ruc_emisor', 'ruc_proveedor', 'num_ruc',
                    'documento_proveedor', 'nro_ruc', 'ruc_del_emisor', 'nro_de_ruc',
                ])) {
                    $map['num_ruc'] = $idx;
                }
            }

            // Tipo Comprobante (Soporta 'D.', 'TD', 'Tipo', 'Tipo Doc', 'Cod Comp')
            if ($map['cod_comp'] === null) {
                if (in_array($col, [
                    'd', 'td', 'tipo', 'tipo_comp', 'tipo_comprobante', 'cod_comp',
                    'tipo_doc', 'codigo_comprobante', 'cd', 'tipo_de_documento',
                ])) {
                    $map['cod_comp'] = $idx;
                }
            }

            // Serie
            if ($map['numero_serie'] === null) {
                if (in_array($col, ['serie', 'ser', 'numero_serie', 'num_serie', 'nro_serie', 'serie_cpe'])) {
                    $map['numero_serie'] = $idx;
                }
            }

            // Número / Correlativo / Documento combinado
            if ($map['numero'] === null) {
                if (in_array($col, [
                    'numero', 'num', 'correlativo', 'nro_doc', 'nro_comprobante',
                    'nro', 'numero_comprobante', 'numero_documento', 'documento', 'comprobante',
                ])) {
                    $map['numero'] = $idx;
                }
            }

            // Fecha Emisión (Soporta 'F.EMIS.', 'Fecha', 'Fecha Emision', 'Fec Emision')
            if ($map['fecha_emision'] === null) {
                if (in_array($col, [
                    'femis', 'f_emis', 'f_emision', 'fecha', 'fecha_emision', 'fec_emi',
                    'fec_emision', 'fecha_comp', 'fec_doc', 'fecha_de_emision', 'femision',
                ])) {
                    $map['fecha_emision'] = $idx;
                }
            }

            // Monto / Total en Moneda Original
            if ($map['monto'] === null) {
                if (in_array($col, [
                    'total', 'monto', 'importe', 'importe_total', 'precio_venta',
                    'total_compra', 'total_venta', 'total_moneda_original', 'imp_total',
                ])) {
                    $map['monto'] = $idx;
                }
            }

            // Razón Social
            if ($map['razon_social'] === null) {
                if (in_array($col, [
                    'razon_social', 'proveedor', 'nombre_o_razon_social',
                    'apellidos_y_nombres', 'cliente_razon_social', 'denominacion',
                ])) {
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
     * Genera la plantilla oficial descargable en formato Excel nativo (.xlsx) con estilos y formato de texto.
     */
    public function generarPlantillaXlsx(): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cpe_tpl_').'.xlsx';
        $zip = new ZipArchive;

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return $this->generarPlantillaCsv();
        }

        // [Content_Types].xml
        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
        $zip->addFromString('[Content_Types].xml', $contentTypes);

        // _rels/.rels
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
        $zip->addFromString('_rels/.rels', $rels);

        // xl/_rels/workbook.xml.rels
        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);

        // xl/workbook.xml
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'
            .'<sheet name="Plantilla CPE SUNAT" sheetId="1" r:id="rId1"/>'
            .'</sheets>'
            .'</workbook>';
        $zip->addFromString('xl/workbook.xml', $workbook);

        // xl/styles.xml
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2">'
            .'<font><sz val="10"/><name val="Segoe UI"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Segoe UI"/></font>'
            .'</fonts>'
            .'<fills count="3">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'</cellXfs>'
            .'</styleSheet>';
        $zip->addFromString('xl/styles.xml', $styles);

        // Estructura de columnas
        $cols = [
            'A' => ['w' => 17, 'title' => 'RUC Emisor'],
            'B' => ['w' => 45, 'title' => 'Razón Social (Opcional)'],
            'C' => ['w' => 12, 'title' => 'Tipo CPE'],
            'D' => ['w' => 12, 'title' => 'Serie'],
            'E' => ['w' => 14, 'title' => 'Número'],
            'F' => ['w' => 16, 'title' => 'Fecha Emisión'],
            'G' => ['w' => 22, 'title' => 'Importe Total (Moneda Original)'],
        ];

        $sampleRows = [
            ['20498456856', 'EMPRESA DE TRANSPORTES Y SERVICIOS GENERALES TRANSMOTAR S.A.C.', '01', 'F007', '472035', '01/08/2026', '620.00'],
            ['20140441083', 'TIRESOL SAC', '01', 'F001', '577369', '25/08/2026', '3366.36'],
            ['20100084768', 'PEVISA AUTO PARTS S.A.C.', '01', 'F050', '256275', '31/08/2026', '432.97'],
            ['20609720400', 'AMICARGO EXPRESS E.I.R.L.', '01', 'E001', '3359', '01/09/2026', '260.00'],
            ['20609720400', 'AMICARGO EXPRESS E.I.R.L. (Nota de Crédito)', '07', 'E001', '53', '04/09/2026', '260.00'],
            ['10741301275', 'HUAMANI APAZA YANIRA', '01', 'F001', '1245', '14/10/2026', '1500.00'],
        ];

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<cols>';
        $cIdx = 1;
        foreach ($cols as $col) {
            $sheetXml .= '<col min="'.$cIdx.'" max="'.$cIdx.'" width="'.$col['w'].'" customWidth="1"/>';
            $cIdx++;
        }
        $sheetXml .= '</cols><sheetData>';

        // Fila 1: Encabezados
        $sheetXml .= '<row r="1">';
        $cLetters = array_keys($cols);
        foreach ($cLetters as $letter) {
            $val = htmlspecialchars($cols[$letter]['title'], ENT_XML1);
            $sheetXml .= '<c r="'.$letter.'1" t="inlineStr" s="1"><is><t>'.$val.'</t></is></c>';
        }
        $sheetXml .= '</row>';

        // Filas de ejemplo (formato texto para preservar ceros a la izquierda en RUC, Serie y Número)
        $rowNum = 2;
        foreach ($sampleRows as $row) {
            $sheetXml .= '<row r="'.$rowNum.'">';
            foreach ($row as $colIdx => $val) {
                $letter = $cLetters[$colIdx];
                $valXml = htmlspecialchars((string) $val, ENT_XML1);
                $s = ($colIdx <= 5) ? '2' : '0';
                $sheetXml .= '<c r="'.$letter.$rowNum.'" t="inlineStr" s="'.$s.'"><is><t>'.$valXml.'</t></is></c>';
            }
            $sheetXml .= '</row>';
            $rowNum++;
        }

        $sheetXml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);

        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return $content ?: '';
    }

    /**
     * Genera la plantilla oficial descargable en formato CSV con UTF-8 y BOM para apertura limpia en Excel.
     */
    public function generarPlantillaCsv(): string
    {
        $salida = fopen('php://memory', 'w+');
        // Escribir BOM UTF-8 para apertura limpia en Microsoft Excel en español
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, [
            'RUC Emisor',
            'Razón Social (Opcional)',
            'Tipo CPE (01=Factura, 03=Boleta, 07=NC, 08=ND)',
            'Serie',
            'Número',
            'Fecha Emisión (DD/MM/AAAA)',
            'Importe Total (Moneda Original)',
        ]);

        // Filas de ejemplo real
        fputcsv($salida, ['20498456856', 'EMPRESA DE TRANSPORTES TRANSMOTAR S.A.C.', '01', 'F007', '472035', '01/08/2026', '620.00']);
        fputcsv($salida, ['20140441083', 'TIRESOL SAC', '01', 'F001', '577369', '25/08/2026', '3366.36']);
        fputcsv($salida, ['20100084768', 'PEVISA AUTO PARTS S.A.C.', '01', 'F050', '256275', '31/08/2026', '432.97']);
        fputcsv($salida, ['20609720400', 'AMICARGO EXPRESS E.I.R.L.', '01', 'E001', '3359', '01/09/2026', '260.00']);
        fputcsv($salida, ['20609720400', 'AMICARGO EXPRESS E.I.R.L. (NC)', '07', 'E001', '53', '04/09/2026', '260.00']);
        fputcsv($salida, ['10741301275', 'HUAMANI APAZA YANIRA', '01', 'F001', '1245', '14/10/2026', '1500.00']);

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
