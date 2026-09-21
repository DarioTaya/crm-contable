<?php

namespace App\Services;

use Exception;
use SimpleXMLElement;
use Throwable;

class UblXmlParserService
{
    /**
     * Parsea una cadena de texto XML o el contenido de un archivo y extrae toda la estructura UBL 2.1.
     *
     * @return array<string, mixed>
     */
    public function parsearXml(string $xmlContent): array
    {
        $cleanXml = trim($xmlContent);
        // Quitar BOM si existe
        if (str_starts_with($cleanXml, "\xEF\xBB\xBF")) {
            $cleanXml = substr($cleanXml, 3);
        }

        try {
            $sxml = new SimpleXMLElement($cleanXml);
        } catch (Throwable $e) {
            throw new Exception('El archivo no contiene un XML válido: '.$e->getMessage());
        }

        // Registrar namespaces de UBL 2.1
        $namespaces = $sxml->getNamespaces(true);
        $sxml->registerXPathNamespace('cbc', $namespaces['cbc'] ?? 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $sxml->registerXPathNamespace('cac', $namespaces['cac'] ?? 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $sxml->registerXPathNamespace('ds', $namespaces['ds'] ?? 'http://www.w3.org/2000/09/xmldsig#');

        $rootName = $sxml->getName();
        $tipoComprobante = match ($rootName) {
            'CreditNote' => '07',
            'DebitNote' => '08',
            default => (string) ($this->xpathValue($sxml, '//cbc:InvoiceTypeCode') ?: '01'),
        };

        $tipoComprobanteDescripcion = match ($tipoComprobante) {
            '01' => 'FACTURA ELECTRÓNICA',
            '03' => 'BOLETA DE VENTA ELECTRÓNICA',
            '07' => 'NOTA DE CRÉDITO ELECTRÓNICA',
            '08' => 'NOTA DE DÉBITO ELECTRÓNICA',
            default => 'COMPROBANTE DE PAGO ELECTRÓNICO',
        };

        $idCompleto = (string) $this->xpathValue($sxml, '//cbc:ID');
        $partesId = explode('-', $idCompleto);
        $serie = trim($partesId[0] ?? '');
        $correlativo = trim($partesId[1] ?? '');

        // Detección de origen de emisión (Portal SOL vs Facturador Privado / OSE)
        $serieUpper = strtoupper($serie);
        $esPortalSol = str_starts_with($serieUpper, 'E');
        $esFacturadorPrivado = ! $esPortalSol;

        $fechaEmision = (string) ($this->xpathValue($sxml, '//cbc:IssueDate') ?: date('Y-m-d'));
        $fechaVencimiento = (string) ($this->xpathValue($sxml, '//cbc:DueDate') ?: '');
        $periodo = substr($fechaEmision, 0, 7);
        $moneda = strtoupper((string) ($this->xpathValue($sxml, '//cbc:DocumentCurrencyCode') ?: 'PEN'));
        $simboloMoneda = match ($moneda) {
            'USD' => '$',
            'EUR' => '€',
            default => 'S/',
        };

        // Emisor
        $rucEmisor = (string) $this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PartyIdentification/cbc:ID');
        $razonEmisor = (string) ($this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')
            ?: $this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PartyName/cbc:Name')
            ?: 'PROVEEDOR');
        $direccionEmisor = (string) ($this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PartyPostalAddress/cac:AddressLine/cbc:Line')
            ?: $this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cac:AddressLine/cbc:Line')
            ?: $this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PartyPostalAddress/cbc:StreetName')
            ?: $this->xpathValue($sxml, '//cac:AccountingSupplierParty/cac:Party/cac:PostalAddress/cbc:StreetName')
            ?: '');

        // Receptor
        $rucReceptor = (string) $this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PartyIdentification/cbc:ID');
        $razonReceptor = (string) ($this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PartyLegalEntity/cbc:RegistrationName')
            ?: $this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PartyName/cbc:Name')
            ?: 'CLIENTE');
        $direccionReceptor = (string) ($this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PartyPostalAddress/cac:AddressLine/cbc:Line')
            ?: $this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cac:AddressLine/cbc:Line')
            ?: $this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PartyPostalAddress/cbc:StreetName')
            ?: $this->xpathValue($sxml, '//cac:AccountingCustomerParty/cac:Party/cac:PostalAddress/cbc:StreetName')
            ?: '');
        $tipoDocReceptor = strlen($rucReceptor) === 11 ? '6' : (strlen($rucReceptor) === 8 ? '1' : '-');

        // Importes globales
        $total = (float) ($this->xpathValue($sxml, '//cac:LegalMonetaryTotal/cbc:PayableAmount')
            ?: $this->xpathValue($sxml, '//cac:LegalMonetaryTotal/cbc:TaxInclusiveAmount')
            ?: 0.0);
        $baseImponible = (float) ($this->xpathValue($sxml, '//cac:LegalMonetaryTotal/cbc:LineExtensionAmount')
            ?: $this->xpathValue($sxml, '//cac:TaxTotal/cac:TaxSubtotal/cbc:TaxableAmount')
            ?: 0.0);
        $igv = (float) ($this->xpathValue($sxml, '//cac:TaxTotal/cbc:TaxAmount') ?: 0.0);

        // Desglose de impuestos por categorías
        $opGravadas = 0.0;
        $opExoneradas = 0.0;
        $opInafectas = 0.0;
        $opGratuitas = 0.0;

        $taxSubtotals = $sxml->xpath('//cac:TaxTotal/cac:TaxSubtotal');
        if ($taxSubtotals) {
            foreach ($taxSubtotals as $st) {
                $st->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $st->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
                $taxable = (float) ($this->xpathValue($st, 'cbc:TaxableAmount') ?: 0.0);
                $schemeId = (string) $this->xpathValue($st, 'cac:TaxCategory/cac:TaxScheme/cbc:ID');
                $schemeName = strtoupper((string) $this->xpathValue($st, 'cac:TaxCategory/cac:TaxScheme/cbc:Name'));

                if ($schemeId === '1000' || str_contains($schemeName, 'IGV')) {
                    $opGravadas += $taxable;
                } elseif ($schemeId === '9997' || str_contains($schemeName, 'EXO')) {
                    $opExoneradas += $taxable;
                } elseif ($schemeId === '9998' || str_contains($schemeName, 'INA')) {
                    $opInafectas += $taxable;
                } elseif ($schemeId === '9996' || str_contains($schemeName, 'GRA')) {
                    $opGratuitas += $taxable;
                }
            }
        }

        if ($opGravadas <= 0 && $baseImponible > 0) {
            $opGravadas = $baseImponible;
        } elseif ($baseImponible <= 0 && $opGravadas > 0) {
            $baseImponible = $opGravadas;
        } elseif ($baseImponible <= 0 && $total > 0) {
            $baseImponible = round($total - $igv, 2);
            $opGravadas = $baseImponible;
        }

        // Extracción de ítems / líneas de detalle
        $items = $this->extraerItems($sxml, $rootName);

        // Extracción de detracción
        $detraccion = $this->extraerDetraccion($sxml, $total);

        // Extracción de forma de pago y cuotas
        $formaPago = $this->extraerFormaPago($sxml);

        // Guías de remisión
        $guias = [];
        $guiasNodos = $sxml->xpath('//cac:DespatchDocumentReference/cbc:ID');
        if ($guiasNodos) {
            foreach ($guiasNodos as $g) {
                $guias[] = (string) $g;
            }
        }

        // Hash digital (DigestValue)
        $digestValue = (string) ($this->xpathValue($sxml, '//ds:DigestValue') ?: '');

        // Observaciones y Monto en Letras
        $observaciones = [];
        $montoEnLetras = '';
        $noteNodes = $sxml->xpath('//cbc:Note');
        if ($noteNodes) {
            foreach ($noteNodes as $note) {
                $txt = trim((string) $note);
                if ($txt === '') {
                    continue;
                }
                $langId = (string) ($note['languageLocaleID'] ?? '');
                if ($langId === '1000' || stripos($txt, 'SON:') === 0 || stripos($txt, 'SOLES') !== false || stripos($txt, 'DOLARES') !== false) {
                    if (empty($montoEnLetras)) {
                        $montoEnLetras = $txt;
                    }
                } else {
                    $observaciones[] = $txt;
                }
            }
        }

        if (empty($montoEnLetras)) {
            $montoEnLetras = $this->numeroALetras($total, $moneda);
        }

        // Cadena QR oficial según estándar SUNAT
        $qrCadena = sprintf(
            '%s|%s|%s|%s|%s|%s|%s|%s|%s|%s|',
            $rucEmisor,
            $tipoComprobante,
            $serie,
            $correlativo,
            number_format($igv, 2, '.', ''),
            number_format($total, 2, '.', ''),
            $fechaEmision,
            $tipoDocReceptor,
            $rucReceptor,
            $digestValue
        );

        return [
            'tipo_comprobante' => $tipoComprobante,
            'tipo_comprobante_descripcion' => $tipoComprobanteDescripcion,
            'id_completo' => $idCompleto,
            'serie' => $serie,
            'correlativo' => $correlativo,
            'es_portal_sol' => $esPortalSol,
            'es_facturador_privado' => $esFacturadorPrivado,
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento ?: null,
            'periodo' => $periodo,
            'moneda' => $moneda,
            'simbolo_moneda' => $simboloMoneda,
            'emisor' => [
                'ruc' => $rucEmisor,
                'razon_social' => $razonEmisor,
                'direccion' => $direccionEmisor,
            ],
            'receptor' => [
                'ruc' => $rucReceptor,
                'tipo_doc' => $tipoDocReceptor,
                'razon_social' => $razonReceptor,
                'direccion' => $direccionReceptor,
            ],
            'base_imponible' => $baseImponible,
            'op_gravadas' => $opGravadas,
            'op_exoneradas' => $opExoneradas,
            'op_inafectas' => $opInafectas,
            'op_gratuitas' => $opGratuitas,
            'igv' => $igv,
            'total' => $total,
            'items' => $items,
            'detraccion' => $detraccion,
            'forma_pago' => $formaPago['tipo'],
            'cuotas' => $formaPago['cuotas'],
            'guias' => $guias,
            'guias_remision' => $guias,
            'digest_value' => $digestValue,
            'monto_letras' => $montoEnLetras,
            'observaciones' => $observaciones,
            'qr_cadena' => $qrCadena,
        ];
    }

    /**
     * Extrae el listado de productos/servicios con sus cantidades, precios y descripciones.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function extraerItems(SimpleXMLElement $sxml, string $rootName): array
    {
        $tagLinea = match ($rootName) {
            'CreditNote' => 'cac:CreditNoteLine',
            'DebitNote' => 'cac:DebitNoteLine',
            default => 'cac:InvoiceLine',
        };

        $lineas = $sxml->xpath("//{$tagLinea}");
        $items = [];

        if ($lineas) {
            foreach ($lineas as $idx => $linea) {
                $linea->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $linea->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

                $desc = (string) ($this->xpathValue($linea, 'cac:Item/cbc:Description') ?: 'Ítem '.($idx + 1));
                $codigo = (string) ($this->xpathValue($linea, 'cac:Item/cac:SellersItemIdentification/cbc:ID')
                    ?: $this->xpathValue($linea, 'cac:Item/cac:StandardItemIdentification/cbc:ID')
                    ?: '');
                $cantNode = $linea->xpath('cbc:InvoicedQuantity | cbc:CreditedQuantity | cbc:DebitedQuantity');
                $cantidad = $cantNode ? (float) $cantNode[0] : 1.0;
                $unidad = $cantNode ? (string) ($cantNode[0]['unitCode'] ?? 'NIU') : 'NIU';

                $valorUnitario = (float) ($this->xpathValue($linea, 'cac:Price/cbc:PriceAmount') ?: 0.0);
                $precioUnitario = (float) ($this->xpathValue($linea, 'cac:PricingReference/cac:AlternativeConditionPrice/cbc:PriceAmount') ?: ($valorUnitario * 1.18));
                $subtotal = (float) ($this->xpathValue($linea, 'cbc:LineExtensionAmount') ?: ($cantidad * $valorUnitario));
                $igvLinea = (float) ($this->xpathValue($linea, 'cac:TaxTotal/cbc:TaxAmount') ?: 0.0);

                $items[] = [
                    'linea' => (int) ($this->xpathValue($linea, 'cbc:ID') ?: ($idx + 1)),
                    'codigo' => trim($codigo),
                    'descripcion' => trim($desc),
                    'cantidad' => $cantidad,
                    'unidad' => $unidad,
                    'valor_unitario' => $valorUnitario,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                    'igv' => $igvLinea,
                    'total' => round($subtotal + $igvLinea, 2),
                ];
            }
        }

        return $items;
    }

    /**
     * Extrae información de detracción según estándar SUNAT (código de bien, porcentaje, monto y cuenta de banco).
     *
     * @return array<string, mixed>|null
     */
    protected function extraerDetraccion(SimpleXMLElement $sxml, float $total): ?array
    {
        $tieneDetraccion = false;
        $montoDetraccion = 0.0;
        $porcentaje = 0.0;
        $cuentaBanco = '';
        $codigoBien = '';

        // Revisar notas de detracción (código 2006 en SUNAT)
        $notes = $sxml->xpath('//cbc:Note');
        if ($notes) {
            foreach ($notes as $note) {
                $text = (string) $note;
                if (stripos($text, 'detraccion') !== false || stripos($text, 'spot') !== false) {
                    $tieneDetraccion = true;
                    // Buscar cuenta del Banco de la Nación en el texto si existe
                    if (preg_match('/(\d{2,3}-\d{3,6}-\d{3,6}|\d{10,14})/', $text, $m)) {
                        $cuentaBanco = $m[1];
                    }
                    // Buscar porcentaje en texto (ej: 10% o 12.0%)
                    if (preg_match('/(\d+(?:\.\d+)?)\s*%/i', $text, $mp)) {
                        $porcentaje = (float) $mp[1];
                    }
                    // Buscar código de bien / servicio SPOT (ej: Código: 019 o Código 022)
                    if (preg_match('/c[oó]digo[:\s]+([0-9]{3})/iu', $text, $mc)) {
                        $codigoBien = $mc[1];
                    }
                }
            }
        }

        // Revisar cac:PaymentMeans para SPOT
        $pmNodes = $sxml->xpath('//cac:PaymentMeans');
        if ($pmNodes) {
            foreach ($pmNodes as $pm) {
                $pm->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $pm->registerXPathNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');

                $pmCode = (string) $this->xpathValue($pm, 'cbc:PaymentMeansCode');
                $payeeAcc = (string) $this->xpathValue($pm, 'cac:PayeeFinancialAccount/cbc:ID');

                if ($payeeAcc) {
                    $cuentaBanco = $payeeAcc;
                    $tieneDetraccion = true;
                }
            }
        }

        // Revisar PaymentTerms con código Detraccion
        $ptNodes = $sxml->xpath('//cac:PaymentTerms');
        if ($ptNodes) {
            foreach ($ptNodes as $pt) {
                $pt->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $ptId = (string) $this->xpathValue($pt, 'cbc:PaymentMeansID');
                if (stripos($ptId, 'detraccion') !== false) {
                    $tieneDetraccion = true;
                    $montoDetraccion = (float) ($this->xpathValue($pt, 'cbc:Amount') ?: 0.0);
                    $porcentaje = (float) ($this->xpathValue($pt, 'cbc:PaymentPercent') ?: 0.0);
                }
            }
        }

        if (! $tieneDetraccion) {
            return [
                'aplica' => false,
                'tiene_detraccion' => false,
                'monto' => 0.0,
                'porcentaje' => 0.0,
                'cuenta_banco' => '',
                'cuenta_banco_nacion' => '',
                'codigo_bien' => '',
                'codigo_bien_servicio' => '',
            ];
        }

        if ($porcentaje <= 0 && $montoDetraccion > 0 && $total > 0) {
            $porcentaje = round(($montoDetraccion / $total) * 100, 2);
        } elseif ($montoDetraccion <= 0 && $porcentaje > 0 && $total > 0) {
            $montoDetraccion = round($total * ($porcentaje / 100), 2);
        }

        return [
            'aplica' => true,
            'tiene_detraccion' => true,
            'monto' => $montoDetraccion,
            'porcentaje' => $porcentaje,
            'cuenta_banco' => $cuentaBanco,
            'cuenta_banco_nacion' => $cuentaBanco,
            'codigo_bien' => $codigoBien,
            'codigo_bien_servicio' => $codigoBien,
        ];
    }

    /**
     * Extrae forma de pago ('Contado' / 'Credito') y cuotas de vencimiento.
     *
     * @return array{tipo: string, cuotas: array<int, array<string, mixed>>}
     */
    protected function extraerFormaPago(SimpleXMLElement $sxml): array
    {
        $tipo = 'Contado';
        $cuotas = [];

        $ptNodes = $sxml->xpath('//cac:PaymentTerms');
        if ($ptNodes) {
            foreach ($ptNodes as $pt) {
                $pt->registerXPathNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
                $idTerm = trim((string) $this->xpathValue($pt, 'cbc:PaymentMeansID'));
                $monto = (float) ($this->xpathValue($pt, 'cbc:Amount') ?: 0.0);
                $fechaVence = (string) ($this->xpathValue($pt, 'cbc:PaymentDueDate') ?: '');

                if (stripos($idTerm, 'credito') !== false) {
                    $tipo = 'Credito';
                }

                if (stripos($idTerm, 'cuota') !== false || ($tipo === 'Credito' && $monto > 0 && $fechaVence !== '')) {
                    $cuotas[] = [
                        'cuota' => $idTerm ?: 'Cuota '.(count($cuotas) + 1),
                        'monto' => $monto,
                        'fecha_vencimiento' => $fechaVence,
                    ];
                }
            }
        }

        return [
            'tipo' => $tipo,
            'cuotas' => $cuotas,
        ];
    }

    /**
     * Helper para extraer el valor escalar de un nodo XPath.
     */
    protected function xpathValue(SimpleXMLElement $node, string $xpath): ?string
    {
        $res = $node->xpath($xpath);
        if ($res && isset($res[0])) {
            $val = trim((string) $res[0]);

            return $val !== '' ? $val : null;
        }

        return null;
    }

    /**
     * Convierte una cifra numérica a su expresión en letras en español según el estándar contable de SUNAT.
     */
    public function numeroALetras(float $monto, string $moneda = 'PEN'): string
    {
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);

        $nombreMoneda = match (strtoupper($moneda)) {
            'USD' => 'DÓLARES AMERICANOS',
            'EUR' => 'EUROS',
            default => 'SOLES',
        };

        $textoEntero = $this->convertirGrupo($entero);
        if (trim($textoEntero) === '') {
            $textoEntero = 'CERO';
        }

        return sprintf('SON: %s CON %02d/100 %s', strtoupper($textoEntero), $centavos, $nombreMoneda);
    }

    /**
     * Helper recursivo para convertir enteros hasta 999,999,999 a palabras en español.
     */
    protected function convertirGrupo(int $n): string
    {
        if ($n === 0) {
            return '';
        }
        if ($n === 100) {
            return 'CIEN';
        }

        $unidades = ['', 'UN', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $especiales = [
            11 => 'ONCE', 12 => 'DOCE', 13 => 'TRECE', 14 => 'CATORCE', 15 => 'QUINCE',
            16 => 'DIECISÉIS', 17 => 'DIECISIETE', 18 => 'DIECIOCHO', 19 => 'DIECINUEVE',
            20 => 'VEINTE', 21 => 'VEINTIÚN', 22 => 'VEINTIDÓS', 23 => 'VEINTITRÉS',
            24 => 'VEINTICUATRO', 25 => 'VEINTICINCO', 26 => 'VEINTISÉIS', 27 => 'VEINTISIETE',
            28 => 'VEINTIOCHO', 29 => 'VEINTINUEVE',
        ];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $centenas = [
            '', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
            'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS',
        ];

        if ($n < 10) {
            return $unidades[$n];
        }
        if (isset($especiales[$n])) {
            return $especiales[$n];
        }
        if ($n < 100) {
            $d = (int) ($n / 10);
            $u = $n % 10;

            return $u === 0 ? $decenas[$d] : $decenas[$d].' Y '.$unidades[$u];
        }
        if ($n < 1000) {
            $c = (int) ($n / 100);
            $resto = $n % 100;

            return trim($centenas[$c].' '.$this->convertirGrupo($resto));
        }
        if ($n < 1000000) {
            $miles = (int) ($n / 1000);
            $resto = $n % 1000;
            $prefix = $miles === 1 ? 'MIL' : $this->convertirGrupo($miles).' MIL';

            return trim($prefix.' '.$this->convertirGrupo($resto));
        }
        if ($n < 1000000000) {
            $millones = (int) ($n / 1000000);
            $resto = $n % 1000000;
            $prefix = $millones === 1 ? 'UN MILLÓN' : $this->convertirGrupo($millones).' MILLONES';

            return trim($prefix.' '.$this->convertirGrupo($resto));
        }

        return (string) $n;
    }
}
