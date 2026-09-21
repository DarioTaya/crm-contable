<?php

namespace App\Services;

class CpePdfGeneratorService
{
    /**
     * Genera un stream binario PDF versión 1.4 estándar en memoria con el diseño correspondiente.
     *
     * @param  array<string, mixed>  $datos  Estructura devuelta por UblXmlParserService
     */
    public function generarPdfStream(array $datos): string
    {
        $esPortalSol = ! empty($datos['es_portal_sol']);

        if ($esPortalSol) {
            return $this->generarPdfPortalSol($datos);
        }

        return $this->generarPdfFacturadorPrivado($datos);
    }

    /**
     * Genera la representación impresa con la estética y estructura oficial de SUNAT - Portal SOL (E001/EB01).
     *
     * @param  array<string, mixed>  $d
     */
    protected function generarPdfPortalSol(array $d): string
    {
        $clean = fn ($str) => $this->sanitizarTexto($str);

        $emisor = $d['emisor'] ?? [];
        $receptor = $d['receptor'] ?? [];
        $moneda = $d['moneda'] ?? 'PEN';
        $simbolo = $d['simbolo_moneda'] ?? ($moneda === 'USD' ? '$' : 'S/');
        $serie = $d['serie'] ?? 'E001';
        $correlativo = str_pad($d['correlativo'] ?? '1', 8, '0', STR_PAD_LEFT);
        $numeroCompleto = "{$serie}-{$correlativo}";
        $tipoDocNombre = $d['tipo_comprobante_descripcion'] ?? 'FACTURA ELECTRÓNICA';

        $stream = "q\n";

        // ═══════════════════ ENCABEZADO SUNAT SOL ═══════════════════
        // Banda decorativa superior SOL (Azul oscuro institucional)
        $stream .= "0.08 0.22 0.45 rg\n";
        $stream .= "35 805 525 15 re f\n";
        $stream .= "BT /F2 8 Tf 1 1 1 rg 45 809 Td (SUNAT - SISTEMA DE EMISION ELECTRONICA DESDE EL PORTAL DE SUNAT) Tj ET\n";

        // Recuadro izquierdo: Datos Emisor
        $stream .= "0.6 0.6 0.6 RG 0.7 w\n";
        $stream .= "35 700 325 95 re S\n";
        $stream .= "BT\n";
        $stream .= '/F2 11 Tf 0.08 0.22 0.45 rg 45 778 Td ('.$clean(mb_substr($emisor['razon_social'] ?? 'EMISOR SOL', 0, 42)).") Tj\n";
        $stream .= '/F2 8 Tf 0.3 0.3 0.3 rg 0 -16 Td (R.U.C.: '.$clean($emisor['ruc'] ?? '').") Tj\n";
        $stream .= "/F1 7.5 Tf 0.4 0.4 0.4 rg 0 -13 Td (DOMICILIO FISCAL:) Tj\n";
        $dirEmisor = $clean(mb_substr($emisor['direccion'] ?? 'PERU', 0, 60));
        $stream .= '0 -11 Td ('.$dirEmisor.") Tj\n";
        $stream .= "0 -11 Td (EMITIDO VIA: PORTAL SOL (SOL - SUNAT OPERACIONES EN LINEA)) Tj\n";
        $stream .= "ET\n";

        // Recuadro derecho: RUC SUNAT (Caja roja/azul oficial)
        $stream .= "0.08 0.22 0.45 RG 1.5 w\n";
        $stream .= "370 700 190 95 re S\n";
        $stream .= "BT\n";
        $stream .= '/F2 11 Tf 0 0 0 rg 385 768 Td (R.U.C. '.$clean($emisor['ruc'] ?? '').") Tj\n";
        $stream .= '/F2 10 Tf 0.8 0.1 0.1 rg 0 -22 Td ('.$clean($tipoDocNombre).") Tj\n";
        $stream .= '/F2 12 Tf 0.08 0.22 0.45 rg 0 -25 Td (Nro. '.$clean($numeroCompleto).") Tj\n";
        $stream .= "ET\n";

        // ═══════════════════ DATOS DEL RECEPTOR / CLIENTE ═══════════════════
        $stream .= "0.75 0.75 0.75 RG 0.5 w\n";
        $stream .= "35 625 525 65 re S\n";
        $stream .= "BT\n";
        $stream .= "/F2 8 Tf 0.1 0.1 0.1 rg 45 675 Td (SEÑOR(ES):) Tj\n";
        $stream .= '/F1 8 Tf 0 0 0 rg 55 0 Td ('.$clean(mb_substr($receptor['razon_social'] ?? 'CLIENTE', 0, 65)).") Tj\n";
        $stream .= "/F2 8 Tf -55 -15 Td (RUC / DOC:) Tj\n";
        $stream .= '/F1 8 Tf 0 0 0 rg 55 0 Td ('.$clean($receptor['ruc'] ?? '-').") Tj\n";
        $stream .= "/F2 8 Tf 140 0 Td (FECHA EMISIÓN:) Tj\n";
        $stream .= '/F1 8 Tf 75 0 Td ('.$clean($d['fecha_emision'] ?? date('Y-m-d')).") Tj\n";
        $stream .= "/F2 8 Tf -270 -15 Td (DIRECCIÓN:) Tj\n";
        $stream .= '/F1 8 Tf 0 0 0 rg 55 0 Td ('.$clean(mb_substr($receptor['direccion'] ?? '-', 0, 55)).") Tj\n";
        $stream .= "/F2 8 Tf 215 0 Td (MONEDA:) Tj\n";
        $stream .= '/F1 8 Tf 45 0 Td ('.$clean($moneda).") Tj\n";
        $stream .= "/F2 8 Tf -315 -15 Td (FORMA PAGO:) Tj\n";
        $stream .= '/F1 8 Tf 0.1 0.5 0.2 rg 65 0 Td ('.$clean(strtoupper($d['forma_pago'] ?? 'CONTADO')).") Tj\n";
        if (! empty($d['fecha_vencimiento'])) {
            $stream .= "/F2 8 Tf 130 0 Td (VENCIMIENTO:) Tj\n";
            $stream .= '/F1 8 Tf 75 0 Td ('.$clean($d['fecha_vencimiento']).") Tj\n";
        }
        $stream .= "ET\n";

        // ═══════════════════ TABLA DE ÍTEMS ═══════════════════
        $stream .= "0.08 0.22 0.45 rg\n";
        $stream .= "35 595 525 20 re f\n";
        $stream .= "BT /F2 7.5 Tf 1 1 1 rg\n";
        $stream .= "42 602 Td (ITEM) Tj\n";
        $stream .= "26 0 Td (CANT) Tj\n";
        $stream .= "32 0 Td (UND) Tj\n";
        $stream .= "32 0 Td (CÓDIGO) Tj\n";
        $stream .= "55 0 Td (DESCRIPCIÓN) Tj\n";
        $stream .= "205 0 Td (V. UNIT) Tj\n";
        $stream .= "45 0 Td (IGV) Tj\n";
        $stream .= "40 0 Td (IMPORTE) Tj\n";
        $stream .= "ET\n";

        $items = $d['items'] ?? [];
        $y = 575;
        $maxItems = min(count($items), 7);

        if ($maxItems === 0) {
            $stream .= "0.85 0.85 0.85 RG 0.5 w\n";
            $stream .= "35 {$y} 525 20 re S\n";
            $stream .= 'BT /F1 7.5 Tf 0 0 0 rg 45 '.($y + 6).' Td (1) Tj 26 0 Td (1.00) Tj 32 0 Td (NIU) Tj 32 0 Td (-) Tj 55 0 Td (POR CONCEPTO SEGÚN FACTURA) Tj 205 0 Td ('.number_format($d['base_imponible'] ?? 0, 2).') Tj 45 0 Td ('.number_format($d['igv'] ?? 0, 2).') Tj 40 0 Td ('.number_format($d['total'] ?? 0, 2).") Tj ET\n";
            $y -= 22;
        } else {
            for ($i = 0; $i < $maxItems; $i++) {
                $it = $items[$i];
                $stream .= "0.9 0.9 0.9 RG 0.5 w\n";
                $stream .= "35 {$y} 525 20 re S\n";
                $stream .= "BT /F1 7 Tf 0 0 0 rg\n";
                $stream .= '45 '.($y + 6).' Td ('.($i + 1).") Tj\n";
                $stream .= '26 0 Td ('.number_format((float) ($it['cantidad'] ?? 1), 2).") Tj\n";
                $stream .= '32 0 Td ('.$clean($it['unidad'] ?? 'NIU').") Tj\n";
                $stream .= '32 0 Td ('.$clean(substr($it['codigo'] ?? '-', 0, 10)).") Tj\n";
                $stream .= '55 0 Td ('.$clean(mb_substr($it['descripcion'] ?? 'ITEM', 0, 42)).") Tj\n";
                $stream .= '205 0 Td ('.number_format((float) ($it['valor_unitario'] ?? 0), 2).") Tj\n";
                $stream .= '45 0 Td ('.number_format((float) ($it['igv'] ?? 0), 2).") Tj\n";
                $stream .= '40 0 Td ('.number_format((float) ($it['total'] ?? 0), 2).") Tj\n";
                $stream .= "ET\n";
                $y -= 20;
            }
        }

        // ═══════════════════ DETRACCIÓN Y FORMA DE PAGO ═══════════════════
        $det = $d['detraccion'] ?? [];
        $tieneDet = ! empty($det['aplica']) || ! empty($det['tiene_detraccion']);

        if ($tieneDet) {
            $stream .= '0.98 0.94 0.90 rg 35 '.($y - 15)." 315 32 re f\n";
            $stream .= '0.8 0.4 0.1 RG 0.8 w 35 '.($y - 15)." 315 32 re S\n";
            $stream .= "BT\n";
            $stream .= '/F2 7 Tf 0.7 0.2 0 rg 42 '.($y + 5)." Td (OPERACIÓN SUJETA AL SISTEMA DE PAGO DE OBLIGACIONES TRIBUTARIAS (SPOT)) Tj\n";
            $stream .= '/F1 6.5 Tf 0.2 0.2 0.2 rg 0 -9 Td (TASA: '.number_format((float) ($det['porcentaje'] ?? 0), 1).'% | MONTO DETRACCIÓN: S/ '.number_format((float) ($det['monto'] ?? 0), 2).") Tj\n";
            $stream .= '0 -8 Td (CTA BANCO DE LA NACIÓN: '.$clean($det['cuenta_banco'] ?? $det['cuenta_banco_nacion'] ?? 'NO ESPECIFICADA').") Tj\n";
            $stream .= "ET\n";
        }

        // ═══════════════════ CUADRO DE TOTALES (DERECHA) ═══════════════════
        $stream .= "0.8 0.8 0.8 RG 0.5 w\n";
        $stream .= '360 '.($y - 45)." 200 62 re S\n";
        $stream .= "BT\n";
        $stream .= '/F2 7.5 Tf 0.2 0.2 0.2 rg 368 '.($y + 3)." Td (OP. GRAVADA:) Tj\n";
        $stream .= '/F1 7.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['base_imponible'] ?? 0), 2).") Tj\n";
        $stream .= "/F2 7.5 Tf -95 -13 Td (I.G.V. (18%):) Tj\n";
        $stream .= '/F1 7.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['igv'] ?? 0), 2).") Tj\n";
        $stream .= "/F2 8.5 Tf 0.08 0.22 0.45 rg -95 -18 Td (IMPORTE TOTAL:) Tj\n";
        $stream .= '/F2 8.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['total'] ?? 0), 2).") Tj\n";
        $stream .= "ET\n";

        // ═══════════════════ MONTO EN LETRAS ═══════════════════
        $stream .= '0.95 0.95 0.95 rg 35 '.($y - 68)." 525 18 re f\n";
        $stream .= '0.8 0.8 0.8 RG 0.5 w 35 '.($y - 68)." 525 18 re S\n";
        $stream .= 'BT /F2 7.5 Tf 0 0 0 rg 42 '.($y - 58).' Td ('.$clean(mb_substr($d['monto_letras'] ?? 'SON: CERO CON 00/100 SOLES', 0, 95)).") Tj ET\n";

        // ═══════════════════ PIE DE PÁGINA Y HASH SUNAT ═══════════════════
        $stream .= "0.75 0.75 0.75 RG 0.5 w\n";
        $stream .= "35 80 525 55 re S\n";
        $stream .= "BT\n";
        $stream .= "/F2 7 Tf 0.2 0.2 0.2 rg 42 122 Td (INFORMACIÓN OFICIAL SUNAT - SEE PORTAL SOL) Tj\n";
        $stream .= "/F1 6.5 Tf 0.4 0.4 0.4 rg 0 -10 Td (Representación impresa de la Factura Electrónica generada mediante el Sistema de Emisión Electrónica SUNAT.) Tj\n";
        $stream .= "0 -9 Td (Consulte la validez del comprobante en SUNAT Virtual: www.sunat.gob.pe con RUC, Tipo, Serie y Número.) Tj\n";
        $stream .= '/F2 6.5 Tf 0.08 0.22 0.45 rg 0 -11 Td (CÓDIGO HASH UBL: '.$clean($d['digest_value'] ?: hash('sha256', $numeroCompleto)).") Tj\n";
        $stream .= '/F1 6 Tf 0.5 0.5 0.5 rg 0 -8 Td (CADENA QR: '.$clean(mb_substr($d['qr_cadena'] ?? '', 0, 90)).") Tj\n";
        $stream .= "ET\n";

        $stream .= "Q\n";

        return $this->compilarPdfStandard($stream);
    }

    /**
     * Genera la representación impresa con diseño corporativo premium para Facturación Privada / OSE (F001, FF01, B001).
     *
     * @param  array<string, mixed>  $d
     */
    protected function generarPdfFacturadorPrivado(array $d): string
    {
        $clean = fn ($str) => $this->sanitizarTexto($str);

        $emisor = $d['emisor'] ?? [];
        $receptor = $d['receptor'] ?? [];
        $moneda = $d['moneda'] ?? 'PEN';
        $simbolo = $d['simbolo_moneda'] ?? ($moneda === 'USD' ? '$' : 'S/');
        $serie = $d['serie'] ?? 'F001';
        $correlativo = str_pad($d['correlativo'] ?? '1', 8, '0', STR_PAD_LEFT);
        $numeroCompleto = "{$serie}-{$correlativo}";
        $tipoDocNombre = $d['tipo_comprobante_descripcion'] ?? 'FACTURA ELECTRÓNICA';

        $stream = "q\n";

        // ═══════════════════ ENCABEZADO CORPORATIVO PRIVADO ═══════════════════
        // Bloque Emisor
        $stream .= "BT\n";
        $stream .= '/F2 13 Tf 0.1 0.15 0.25 rg 35 790 Td ('.$clean(mb_substr($emisor['razon_social'] ?? 'EMPRESA EMISORA S.A.C.', 0, 42)).") Tj\n";
        $stream .= '/F2 8.5 Tf 0.25 0.3 0.4 rg 0 -16 Td (R.U.C.: '.$clean($emisor['ruc'] ?? '').") Tj\n";
        $stream .= '/F1 8 Tf 0.4 0.4 0.4 rg 0 -13 Td ('.$clean(mb_substr($emisor['direccion'] ?? 'PERU', 0, 60)).") Tj\n";
        $stream .= "/F1 7.5 Tf 0.5 0.5 0.5 rg 0 -11 Td (SISTEMA DE EMISIÓN ELECTRÓNICA - FACTURADOR PRIVADO / OSE) Tj\n";
        $stream .= "ET\n";

        // Recuadro RUC Oficial Estilizado
        $stream .= "0.15 0.25 0.4 RG 1.5 w\n";
        $stream .= "365 715 195 90 re S\n";
        $stream .= "0.95 0.97 1.0 rg 365 778 195 27 re f\n"; // Título fondo celeste suave
        $stream .= "BT\n";
        $stream .= '/F2 11 Tf 0.15 0.25 0.4 rg 380 786 Td (R.U.C. '.$clean($emisor['ruc'] ?? '').") Tj\n";
        $stream .= '/F2 10 Tf 0.1 0.1 0.1 rg 0 -24 Td ('.$clean($tipoDocNombre).") Tj\n";
        $stream .= '/F2 12 Tf 0.85 0.15 0.1 rg 0 -24 Td (N° '.$clean($numeroCompleto).") Tj\n";
        $stream .= "ET\n";

        // ═══════════════════ CARD DE CLIENTE / RECEPTOR ═══════════════════
        $stream .= "0.85 0.87 0.9 RG 0.7 w\n";
        $stream .= "35 635 525 65 re S\n";
        $stream .= "BT\n";
        $stream .= "/F2 8 Tf 0.2 0.2 0.3 rg 45 685 Td (CLIENTE:) Tj\n";
        $stream .= '/F1 8.5 Tf 0 0 0 rg 55 0 Td ('.$clean(mb_substr($receptor['razon_social'] ?? 'CLIENTE', 0, 60)).") Tj\n";
        $stream .= "/F2 8 Tf -55 -15 Td (R.U.C. / DOC:) Tj\n";
        $stream .= '/F1 8 Tf 0 0 0 rg 55 0 Td ('.$clean($receptor['ruc'] ?? '-').") Tj\n";
        $stream .= "/F2 8 Tf 135 0 Td (FECHA EMISIÓN:) Tj\n";
        $stream .= '/F1 8 Tf 75 0 Td ('.$clean($d['fecha_emision'] ?? date('Y-m-d')).") Tj\n";
        $stream .= "/F2 8 Tf -265 -15 Td (DIRECCIÓN:) Tj\n";
        $stream .= '/F1 8 Tf 0 0 0 rg 55 0 Td ('.$clean(mb_substr($receptor['direccion'] ?? '-', 0, 52)).") Tj\n";
        $stream .= "/F2 8 Tf 210 0 Td (MONEDA:) Tj\n";
        $stream .= '/F1 8 Tf 45 0 Td ('.$clean($moneda).") Tj\n";
        $stream .= "/F2 8 Tf -310 -15 Td (FORMA DE PAGO:) Tj\n";
        $stream .= '/F2 8 Tf 0.1 0.45 0.2 rg 75 0 Td ('.$clean(strtoupper($d['forma_pago'] ?? 'CONTADO')).") Tj\n";
        if (! empty($d['fecha_vencimiento'])) {
            $stream .= "/F2 8 Tf 120 0 Td (VENCE:) Tj\n";
            $stream .= '/F1 8 Tf 45 0 Td ('.$clean($d['fecha_vencimiento']).") Tj\n";
        }
        $stream .= "ET\n";

        // ═══════════════════ TABLA DE ÍTEMS ═══════════════════
        $stream .= "0.12 0.2 0.35 rg\n";
        $stream .= "35 605 525 20 re f\n";
        $stream .= "BT /F2 7.5 Tf 1 1 1 rg\n";
        $stream .= "42 612 Td (ÍTEM) Tj\n";
        $stream .= "26 0 Td (CANT) Tj\n";
        $stream .= "32 0 Td (UND) Tj\n";
        $stream .= "32 0 Td (CÓDIGO) Tj\n";
        $stream .= "55 0 Td (DESCRIPCIÓN DEL PRODUCTO O SERVICIO) Tj\n";
        $stream .= "205 0 Td (V. UNIT) Tj\n";
        $stream .= "45 0 Td (IGV) Tj\n";
        $stream .= "40 0 Td (TOTAL) Tj\n";
        $stream .= "ET\n";

        $items = $d['items'] ?? [];
        $y = 585;
        $maxItems = min(count($items), 7);

        if ($maxItems === 0) {
            $stream .= "0.9 0.9 0.9 RG 0.5 w\n";
            $stream .= "35 {$y} 525 20 re S\n";
            $stream .= 'BT /F1 7.5 Tf 0 0 0 rg 45 '.($y + 6).' Td (1) Tj 26 0 Td (1.00) Tj 32 0 Td (NIU) Tj 32 0 Td (-) Tj 55 0 Td (SERVICIO / VENTA GENERAL) Tj 205 0 Td ('.number_format($d['base_imponible'] ?? 0, 2).') Tj 45 0 Td ('.number_format($d['igv'] ?? 0, 2).') Tj 40 0 Td ('.number_format($d['total'] ?? 0, 2).") Tj ET\n";
            $y -= 22;
        } else {
            for ($i = 0; $i < $maxItems; $i++) {
                $it = $items[$i];
                $stream .= "0.92 0.92 0.94 RG 0.5 w\n";
                $stream .= "35 {$y} 525 20 re S\n";
                $stream .= "BT /F1 7 Tf 0 0 0 rg\n";
                $stream .= '45 '.($y + 6).' Td ('.($i + 1).") Tj\n";
                $stream .= '26 0 Td ('.number_format((float) ($it['cantidad'] ?? 1), 2).") Tj\n";
                $stream .= '32 0 Td ('.$clean($it['unidad'] ?? 'NIU').") Tj\n";
                $stream .= '32 0 Td ('.$clean(substr($it['codigo'] ?? '-', 0, 10)).") Tj\n";
                $stream .= '55 0 Td ('.$clean(mb_substr($it['descripcion'] ?? 'ITEM', 0, 42)).") Tj\n";
                $stream .= '205 0 Td ('.number_format((float) ($it['valor_unitario'] ?? 0), 2).") Tj\n";
                $stream .= '45 0 Td ('.number_format((float) ($it['igv'] ?? 0), 2).") Tj\n";
                $stream .= '40 0 Td ('.number_format((float) ($it['total'] ?? 0), 2).") Tj\n";
                $stream .= "ET\n";
                $y -= 20;
            }
        }

        // ═══════════════════ SPOT & CRÉDITO ═══════════════════
        $det = $d['detraccion'] ?? [];
        $tieneDet = ! empty($det['aplica']) || ! empty($det['tiene_detraccion']);
        $cuotas = $d['cuotas'] ?? [];

        if ($tieneDet) {
            $stream .= '0.99 0.96 0.90 rg 35 '.($y - 18)." 315 35 re f\n";
            $stream .= '0.85 0.5 0.1 RG 0.8 w 35 '.($y - 18)." 315 35 re S\n";
            $stream .= "BT\n";
            $stream .= '/F2 7.5 Tf 0.7 0.25 0 rg 42 '.($y + 7)." Td (SUJETO A DETRACCIÓN SPOT - SUNAT) Tj\n";
            $stream .= '/F1 7 Tf 0.2 0.2 0.2 rg 0 -9 Td (Porcentaje: '.number_format((float) ($det['porcentaje'] ?? 0), 1).'% | Monto Detracción: S/ '.number_format((float) ($det['monto'] ?? 0), 2).") Tj\n";
            $stream .= '0 -9 Td (Cta Cte Banco de la Nación: '.$clean($det['cuenta_banco'] ?? $det['cuenta_banco_nacion'] ?? 'NO ESPECIFICADA').") Tj\n";
            $stream .= "ET\n";
        }

        // Cuadro de Cuotas al Crédito si aplica
        if (! empty($cuotas) && count($cuotas) > 0) {
            $yCuotas = $tieneDet ? ($y - 58) : ($y - 18);
            $stream .= "0.95 0.95 0.97 rg 35 {$yCuotas} 315 34 re f\n";
            $stream .= "0.8 0.8 0.85 RG 0.5 w 35 {$yCuotas} 315 34 re S\n";
            $stream .= 'BT /F2 7 Tf 0.2 0.3 0.4 rg 42 '.($yCuotas + 24)." Td (CONDICIONES DE PAGO AL CRÉDITO) Tj\n";
            $stream .= "/F1 6.5 Tf 0 0 0 rg\n";
            foreach (array_slice($cuotas, 0, 2) as $idx => $cuota) {
                $stream .= '0 -9 Td ('.$clean($cuota['cuota'] ?? 'Cuota '.($idx + 1)).' | Vence: '.$clean($cuota['fecha_vencimiento'] ?? '-').' | Importe: '.$simbolo.' '.number_format((float) ($cuota['monto'] ?? 0), 2).") Tj\n";
            }
            $stream .= "ET\n";
        }

        // ═══════════════════ TOTALES (DERECHA) ═══════════════════
        $stream .= "0.85 0.87 0.9 RG 0.7 w\n";
        $stream .= '360 '.($y - 45)." 200 62 re S\n";
        $stream .= "BT\n";
        $stream .= '/F2 7.5 Tf 0.3 0.3 0.3 rg 368 '.($y + 3)." Td (OP. GRAVADA:) Tj\n";
        $stream .= '/F1 7.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['base_imponible'] ?? 0), 2).") Tj\n";
        $stream .= "/F2 7.5 Tf -95 -13 Td (I.G.V. (18%):) Tj\n";
        $stream .= '/F1 7.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['igv'] ?? 0), 2).") Tj\n";
        $stream .= "/F2 8.5 Tf 0.1 0.2 0.35 rg -95 -18 Td (TOTAL A PAGAR:) Tj\n";
        $stream .= '/F2 8.5 Tf 95 0 Td ('.$simbolo.' '.number_format((float) ($d['total'] ?? 0), 2).") Tj\n";
        $stream .= "ET\n";

        // ═══════════════════ MONTO EN LETRAS ═══════════════════
        $stream .= '0.96 0.97 0.98 rg 35 '.($y - 70)." 525 18 re f\n";
        $stream .= '0.85 0.85 0.85 RG 0.5 w 35 '.($y - 70)." 525 18 re S\n";
        $stream .= 'BT /F2 7.5 Tf 0.15 0.15 0.15 rg 42 '.($y - 60).' Td ('.$clean(mb_substr($d['monto_letras'] ?? 'SON: CERO CON 00/100 SOLES', 0, 95)).") Tj ET\n";

        // ═══════════════════ PIE DE PÁGINA PRIVADO ═══════════════════
        $stream .= "0.8 0.85 0.9 RG 0.5 w\n";
        $stream .= "35 80 525 55 re S\n";
        $stream .= "BT\n";
        $stream .= "/F2 7 Tf 0.15 0.2 0.3 rg 42 122 Td (AUTORIZACIÓN Y VALIDEZ DEL COMPROBANTE DE PAGO) Tj\n";
        $stream .= "/F1 6.5 Tf 0.4 0.4 0.4 rg 0 -10 Td (Representación impresa del Comprobante de Pago Electrónico generado bajo el Estándar UBL 2.1 autorizado por SUNAT.) Tj\n";
        $stream .= "0 -9 Td (Consulte este comprobante con el emisor o valide su estado en la plataforma SUNAT OSE.) Tj\n";
        $stream .= '/F2 6.5 Tf 0.1 0.2 0.4 rg 0 -11 Td (HASH DIGITAL: '.$clean($d['digest_value'] ?: hash('sha256', $numeroCompleto)).") Tj\n";
        $stream .= '/F1 6 Tf 0.5 0.5 0.5 rg 0 -8 Td (CADENA QR: '.$clean(mb_substr($d['qr_cadena'] ?? '', 0, 90)).") Tj\n";
        $stream .= "ET\n";

        $stream .= "Q\n";

        return $this->compilarPdfStandard($stream);
    }

    /**
     * Ensambla un documento PDF versión 1.4 estándar con tablas y tipografías base en memoria pura.
     */
    protected function compilarPdfStandard(string $stream): string
    {
        $out = "%PDF-1.4\n";
        $offsets = [];

        // Objeto 1: Catalog
        $offsets[1] = strlen($out);
        $out .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";

        // Objeto 2: Pages
        $offsets[2] = strlen($out);
        $out .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";

        // Objeto 3: Page (A4 Portrait: 595.28 x 841.89 pt)
        $offsets[3] = strlen($out);
        $out .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595.28 841.89] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";

        // Objeto 4: Content Stream
        $streamLen = strlen($stream);
        $offsets[4] = strlen($out);
        $out .= "4 0 obj\n<< /Length {$streamLen} >>\nstream\n{$stream}\nendstream\nendobj\n";

        // Objeto 5: Fuente Helvetica Regular
        $offsets[5] = strlen($out);
        $out .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Objeto 6: Fuente Helvetica Bold
        $offsets[6] = strlen($out);
        $out .= "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        // Tabla Xref y Trailer
        $xrefOffset = strlen($out);
        $out .= "xref\n0 7\n0000000000 65535 f \n";
        for ($i = 1; $i <= 6; $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        return $out;
    }

    /**
     * Sanitiza cadenas para compatibilidad estricta con fuentes Type1 WinAnsi en PDF.
     */
    protected function sanitizarTexto(?string $str): string
    {
        if ($str === null || $str === '') {
            return '';
        }

        // Convertir caracteres acentuados o especiales a ASCII estándar
        $translitted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        if ($translitted === false || $translitted === '') {
            $translitted = preg_replace('/[^\x20-\x7E]/', '', $str);
        }

        // Escapar paréntesis y barras invertidas que alteran la sintaxis PDF literal string ( ... )
        $escaped = str_replace('\\', '\\\\', (string) $translitted);
        $escaped = str_replace('(', '\(', $escaped);
        $escaped = str_replace(')', '\)', $escaped);

        return $escaped;
    }
}
