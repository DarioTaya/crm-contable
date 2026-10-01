<?php

namespace App\Services\Clientes;

use DOMElement;
use DOMXPath;
use ZipArchive;

/**
 * Plantilla de alta masiva en Excel y lectura de ese mismo libro.
 * La hoja de clientes queda limpia; las indicaciones viven en su propia hoja.
 */
class LibroCargaMasivaClientes
{
    private const ESPACIO = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const RELACIONES = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @var list<string>
     */
    private const COLUMNAS = [
        'RUC',
        'Régimen',
        'Correo',
        'Teléfono',
        'Usuario SOL',
        'Clave SOL',
        'Client ID API SUNAT',
        'Client Secret API SUNAT',
    ];

    public function binario(): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cmx');
        if ($ruta === false) {
            return '';
        }

        unlink($ruta);
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::CREATE) !== true) {
            return '';
        }

        foreach ($this->partes() as $nombre => $xml) {
            $zip->addFromString($nombre, $xml);
        }
        $zip->close();

        $bytes = file_get_contents($ruta);
        unlink($ruta);

        return $bytes === false ? '' : $bytes;
    }

    public function aTexto(string $bytes): ?string
    {
        if (! str_starts_with($bytes, 'PK')) {
            return null;
        }

        $ruta = tempnam(sys_get_temp_dir(), 'cmr');
        if ($ruta === false) {
            return null;
        }

        file_put_contents($ruta, $bytes);
        $zip = new ZipArchive;
        if ($zip->open($ruta) !== true) {
            unlink($ruta);

            return null;
        }

        try {
            $hoja = $this->xmlDeHojaClientes($zip);
            if ($hoja === null) {
                return null;
            }

            $filas = $this->filas($hoja, $this->cadenas($zip));
            $desde = $this->filaDeTitulos($filas);
            if ($desde === null) {
                return null;
            }

            return $this->csv(array_slice($filas, $desde));
        } finally {
            $zip->close();
            unlink($ruta);
        }
    }

    /**
     * @return array<string, string>
     */
    private function partes(): array
    {
        return [
            '[Content_Types].xml' => $this->tipos(),
            '_rels/.rels' => $this->relacionesRaiz(),
            'xl/workbook.xml' => $this->libro(),
            'xl/_rels/workbook.xml.rels' => $this->relacionesLibro(),
            'xl/styles.xml' => $this->estilos(),
            'xl/worksheets/sheet1.xml' => $this->hojaClientes(),
            'xl/worksheets/sheet2.xml' => $this->hojaInstrucciones(),
        ];
    }

    private function tipos(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/worksheets/sheet2.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML;
    }

    private function relacionesRaiz(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>
XML;
    }

    private function libro(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <workbookViews><workbookView activeTab="0"/></workbookViews>
    <sheets>
        <sheet name="Clientes" sheetId="1" r:id="rId1"/>
        <sheet name="Instrucciones" sheetId="2" r:id="rId2"/>
    </sheets>
</workbook>
XML;
    }

    private function relacionesLibro(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;
    }

    private function estilos(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <numFmts count="1">
        <numFmt numFmtId="164" formatCode="@"/>
    </numFmts>
    <fonts count="7">
        <font><sz val="11"/><color rgb="FF0F172A"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
        <font><sz val="10"/><color rgb="FF475569"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>
        <font><b/><sz val="16"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>
        <font><sz val="11"/><color rgb="FF334155"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><color rgb="FF1E3A8A"/><name val="Calibri"/></font>
    </fonts>
    <fills count="6">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FF1E3A8A"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFEFF6FF"/><bgColor indexed="64"/></patternFill></fill>
        <fill><patternFill patternType="solid"><fgColor rgb="FFFFFFFF"/><bgColor indexed="64"/></patternFill></fill>
    </fills>
    <borders count="2">
        <border><left/><right/><top/><bottom/><diagonal/></border>
        <border>
            <left style="thin"><color rgb="FFCBD5E1"/></left>
            <right style="thin"><color rgb="FFCBD5E1"/></right>
            <top style="thin"><color rgb="FFCBD5E1"/></top>
            <bottom style="thin"><color rgb="FFCBD5E1"/></bottom>
            <diagonal/>
        </border>
    </borders>
    <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
    <cellXfs count="11">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>
        <xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1" indent="1"/></xf>
        <xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="5" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>
        <xf numFmtId="164" fontId="0" fillId="4" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="left" vertical="center" indent="1"/></xf>
        <xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>
        <xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
        <xf numFmtId="0" fontId="5" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center" wrapText="1"/></xf>
        <xf numFmtId="0" fontId="6" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="left" vertical="center"/></xf>
    </cellXfs>
</styleSheet>
XML;
    }

    private function hojaClientes(): string
    {
        $anchos = [18, 16, 38, 16, 20, 20, 32, 36];
        $columnas = '';
        foreach ($anchos as $indice => $ancho) {
            $min = $indice + 1;
            $columnas .= '<col min="'.$min.'" max="'.$min.'" width="'.$ancho.'" customWidth="1"/>';
        }

        $filas = $this->filaTitulo(1, 'CRM Contable  ·  Carga masiva de clientes', 1, 28);
        $filas .= $this->filaTitulo(2, 'Una fila por cliente. Régimen: NRUS, RER, RMT o General. El CRM consulta la razón social y el domicilio en SUNAT. No incluya bancos, usuario AFP ni cuenta de detracciones.', 2, 36);
        $filas .= '<row r="3" ht="30" customHeight="1">';
        foreach (self::COLUMNAS as $indice => $titulo) {
            $filas .= $this->celda($indice, 3, $titulo, 3);
        }
        $filas .= '</row>';

        for ($fila = 4; $fila <= 18; $fila++) {
            $zebra = $fila % 2 === 0;
            $filas .= '<row r="'.$fila.'" ht="22" customHeight="1">';
            for ($columna = 0; $columna < count(self::COLUMNAS); $columna++) {
                $estilo = $columna === 1 ? ($zebra ? 7 : 6) : ($zebra ? 5 : 4);
                $filas .= $this->celda($columna, $fila, '', $estilo);
            }
            $filas .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::ESPACIO.'" xmlns:r="'.self::RELACIONES.'">'
            .'<sheetPr><tabColor rgb="FF1E3A8A"/></sheetPr>'
            .'<dimension ref="A1:H18"/>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/><selection pane="bottomLeft" activeCell="A4" sqref="A4"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols>'.$columnas.'</cols>'
            .'<sheetData>'.$filas.'</sheetData>'
            .'<autoFilter ref="A3:H18"/>'
            .'<mergeCells count="2"><mergeCell ref="A1:H1"/><mergeCell ref="A2:H2"/></mergeCells>'
            .'<dataValidations count="1"><dataValidation type="list" allowBlank="1" showInputMessage="1" showErrorMessage="1" promptTitle="Régimen" prompt="NRUS, RER, RMT o General." errorTitle="Régimen" error="Escriba NRUS, RER, RMT o General." sqref="B4:B503"><formula1>"NRUS,RER,RMT,General"</formula1></dataValidation></dataValidations>'
            .'<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="0"/>'
            .'<headerFooter><oddHeader>&amp;LCRM Contable&amp;RCarga masiva de clientes</oddHeader><oddFooter>&amp;LHoja Clientes&amp;RPágina &amp;P</oddFooter></headerFooter>'
            .'</worksheet>';
    }

    private function hojaInstrucciones(): string
    {
        $bloques = [
            [1, 'Cómo completar la plantilla', 8, 28],
            [3, 'Complete la hoja Clientes. Esta hoja es solo la guía.', 9, 20],
            [5, 'Qué escribe usted', 10, 22],
            [6, 'RUC. Once dígitos, en formato texto. La columna ya viene así para que Excel no lo convierta a notación científica.', 9, 32],
            [7, 'Régimen. NRUS, RER, RMT o General. El padrón de SUNAT no informa el régimen y sin él no hay PDT 621 ni balances.', 9, 32],
            [8, 'Correo, teléfono, usuario SOL, clave SOL y, si ya las tiene, las credenciales API SUNAT (Client ID y Client Secret).', 9, 32],
            [10, 'Qué completa el CRM al consultar SUNAT', 10, 22],
            [11, 'Razón social, tipo de contribuyente, estado, condición de domicilio, dirección fiscal, ubigeo, distrito, provincia y departamento.', 9, 32],
            [12, 'El CRM consulta un RUC y espera a que esa lectura termine antes de pasar al siguiente. Si una fila no responde, queda para reintentar y no se registra.', 9, 32],
            [14, 'Qué no va en esta plantilla', 10, 22],
            [15, 'Usuario AFP, cuenta de detracciones, bancos y los demás datos libres. Eso se completa después, dentro de cada empresa.', 9, 32],
            [16, 'Los enlaces de AFPnet y de detracciones se confirman en la pantalla de carga, con el portal oficial ya escrito.', 9, 32],
            [18, 'La clave SOL se guarda cifrada. La entrada al portal se comprueba cuando abra la empresa.', 9, 22],
            [20, 'Al terminar, suba este mismo archivo en Clientes, Carga masiva. También puede copiar las filas y pegarlas.', 9, 32],
        ];

        $filas = '';
        foreach ($bloques as [$numero, $texto, $estilo, $alto]) {
            $filas .= '<row r="'.$numero.'" ht="'.$alto.'" customHeight="1">';
            $filas .= $this->celda(0, $numero, $texto, $estilo);
            $filas .= '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="'.self::ESPACIO.'">'
            .'<sheetPr><tabColor rgb="FF64748B"/></sheetPr>'
            .'<dimension ref="A1:A20"/>'
            .'<sheetViews><sheetView workbookViewId="0" showGridLines="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<cols><col min="1" max="1" width="118" customWidth="1"/></cols>'
            .'<sheetData>'.$filas.'</sheetData>'
            .'<pageMargins left="0.6" right="0.6" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" paperSize="9" fitToWidth="1" fitToHeight="1"/>'
            .'</worksheet>';
    }

    private function filaTitulo(int $fila, string $texto, int $estilo, int $alto): string
    {
        return '<row r="'.$fila.'" ht="'.$alto.'" customHeight="1">'.$this->celda(0, $fila, $texto, $estilo).'</row>';
    }

    private function celda(int $columna, int $fila, string $texto, int $estilo): string
    {
        $referencia = $this->letra($columna).$fila;
        if ($texto === '') {
            return '<c r="'.$referencia.'" s="'.$estilo.'"/>';
        }

        return '<c r="'.$referencia.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'.$this->xml($texto).'</t></is></c>';
    }

    private function letra(int $columna): string
    {
        $letra = '';
        $indice = $columna;
        do {
            $letra = chr(65 + ($indice % 26)).$letra;
            $indice = intdiv($indice, 26) - 1;
        } while ($indice >= 0);

        return $letra;
    }

    private function xml(string $texto): string
    {
        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function xmlDeHojaClientes(ZipArchive $zip): ?string
    {
        $libro = $zip->getFromName('xl/workbook.xml');
        $relaciones = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($libro === false || $relaciones === false) {
            return null;
        }

        $consultaLibro = $this->consulta($libro);
        $consultaRelaciones = $this->consulta($relaciones);
        if ($consultaLibro === null || $consultaRelaciones === null) {
            return null;
        }

        $hojas = $consultaLibro->query('//m:sheet');
        if ($hojas === false || $hojas->length === 0) {
            return null;
        }

        $elegida = $hojas->item(0);
        foreach ($hojas as $hoja) {
            if ($hoja instanceof DOMElement && $hoja->getAttribute('name') === 'Clientes') {
                $elegida = $hoja;
                break;
            }
        }
        if (! $elegida instanceof DOMElement) {
            return null;
        }

        $id = $elegida->getAttributeNS(self::RELACIONES, 'id');
        $id = str_replace(['"', "'"], '', $id);
        $destinos = $consultaRelaciones->query('//pr:Relationship[@Id="'.$id.'"]');
        if ($destinos === false || ! $destinos->item(0) instanceof DOMElement) {
            return null;
        }

        $destino = $destinos->item(0)->getAttribute('Target');
        $destino = ltrim(str_replace('\\', '/', $destino), '/');
        if (! str_starts_with($destino, 'xl/')) {
            $destino = 'xl/'.$destino;
        }

        $xml = $zip->getFromName($destino);

        return $xml === false ? null : $xml;
    }

    /**
     * @return list<string>
     */
    private function cadenas(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $consulta = $this->consulta($xml);
        if ($consulta === null) {
            return [];
        }

        $lista = [];
        $items = $consulta->query('//m:si');
        if ($items === false) {
            return [];
        }

        foreach ($items as $item) {
            $texto = '';
            $partes = $consulta->query('.//m:t', $item);
            if ($partes !== false) {
                foreach ($partes as $parte) {
                    $texto .= $parte->textContent;
                }
            }
            $lista[] = $texto;
        }

        return $lista;
    }

    /**
     * @param  list<string>  $cadenas
     * @return list<list<string>>
     */
    private function filas(string $xml, array $cadenas): array
    {
        $consulta = $this->consulta($xml);
        if ($consulta === null) {
            return [];
        }

        $filas = [];
        $rows = $consulta->query('//m:sheetData/m:row');
        if ($rows === false) {
            return [];
        }

        foreach ($rows as $row) {
            $celdas = [];
            $columnas = $consulta->query('./m:c', $row);
            if ($columnas === false) {
                continue;
            }
            foreach ($columnas as $columna) {
                if (! $columna instanceof DOMElement) {
                    continue;
                }
                $indice = $this->indice($columna->getAttribute('r'));
                if ($indice === null) {
                    $indice = count($celdas);
                }
                $celdas[$indice] = $this->valor($consulta, $columna, $cadenas);
            }
            if ($celdas === []) {
                continue;
            }
            ksort($celdas);
            $linea = [];
            $ultimo = max(array_keys($celdas));
            for ($i = 0; $i <= $ultimo; $i++) {
                $linea[] = $celdas[$i] ?? '';
            }
            $filas[] = $linea;
        }

        return $filas;
    }

    /**
     * @param  list<string>  $cadenas
     */
    private function valor(DOMXPath $consulta, DOMElement $celda, array $cadenas): string
    {
        $tipo = $celda->getAttribute('t');
        if ($tipo === 'inlineStr') {
            $texto = '';
            $partes = $consulta->query('.//m:t', $celda);
            if ($partes !== false) {
                foreach ($partes as $parte) {
                    $texto .= $parte->textContent;
                }
            }

            return $texto;
        }

        $nodo = $consulta->query('./m:v', $celda);
        $crudo = ($nodo !== false && $nodo->item(0) !== null) ? $nodo->item(0)->textContent : '';
        if ($tipo === 's') {
            return $cadenas[(int) $crudo] ?? '';
        }

        if ($tipo === '' || $tipo === 'n') {
            return $this->numero($crudo);
        }

        return $crudo;
    }

    private function numero(string $valor): string
    {
        if ($valor === '' || preg_match('/e/i', $valor) === 1 || ! is_numeric($valor)) {
            return $valor;
        }

        $numero = (float) $valor;
        if (abs($numero - round($numero)) < 0.0000001 && abs($numero) < 1e15) {
            return sprintf('%.0f', $numero);
        }

        return $valor;
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function filaDeTitulos(array $filas): ?int
    {
        foreach ($filas as $indice => $fila) {
            foreach ($fila as $celda) {
                if (mb_strtolower(trim($celda)) === 'ruc') {
                    return $indice;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<list<string>>  $filas
     */
    private function csv(array $filas): string
    {
        $archivo = fopen('php://temp', 'r+');
        if ($archivo === false) {
            return '';
        }

        foreach ($filas as $fila) {
            if (implode('', array_map(trim(...), $fila)) === '') {
                continue;
            }
            fputcsv($archivo, $fila, ';', '"', '\\');
        }
        rewind($archivo);
        $texto = (string) stream_get_contents($archivo);
        fclose($archivo);

        return $texto;
    }

    private function indice(string $referencia): ?int
    {
        if (preg_match('/^([A-Z]+)/', $referencia, $coincidencia) !== 1) {
            return null;
        }

        $indice = 0;
        foreach (str_split($coincidencia[1]) as $letra) {
            $indice = ($indice * 26) + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    private function consulta(string $xml): ?DOMXPath
    {
        $documento = new \DOMDocument;
        if (! $documento->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return null;
        }

        $consulta = new DOMXPath($documento);
        $consulta->registerNamespace('m', self::ESPACIO);
        $consulta->registerNamespace('pr', 'http://schemas.openxmlformats.org/package/2006/relationships');

        return $consulta;
    }
}
