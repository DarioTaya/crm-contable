<?php

namespace App\Services\Detracciones;

use App\Models\Cliente;
use App\Models\MovimientoDetraccion;
use App\Services\ControlImpuestos\ExtractorTextoPdf;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class LectorConstanciaDetraccionPdf
{
    public function __construct(
        private ExtractorTextoPdf $extractor,
    ) {}

    /**
     * Extrae y procesa los datos oficiales de una o varias constancias de detracción SPOT sin almacenar el PDF en disco.
     *
     * @return array{
     *     valido: bool,
     *     mensaje: string,
     *     archivo_nombre: string,
     *     ya_registrado: bool,
     *     movimiento_existente_id: ?int,
     *     ruc_coincide: bool,
     *     datos: array{
     *         numero_constancia: ?string,
     *         numero_operacion: ?string,
     *         fecha_pago: ?string,
     *         fecha_pago_formateada: ?string,
     *         hora_pago: ?string,
     *         periodo_tributario: ?string,
     *         monto: float,
     *         monto_formateado: string,
     *         cuenta_detracciones: ?string,
     *         proveedor_ruc: ?string,
     *         proveedor_razon_social: ?string,
     *         adquiriente_ruc: ?string,
     *         adquiriente_razon_social: ?string,
     *         tipo_comprobante: string,
     *         comprobante_numero: ?string,
     *         comprobante_formateado: ?string,
     *         tipo_bien: ?string,
     *         glosa_sugerida: string
     *     }
     * }
     */
    public function procesar(UploadedFile|string $archivo, ?Cliente $cliente = null): array
    {
        $nombreArchivo = $archivo instanceof UploadedFile ? $archivo->getClientOriginalName() : (is_string($archivo) && file_exists($archivo) ? basename($archivo) : 'constancia.pdf');
        $bytes = $archivo instanceof UploadedFile
            ? file_get_contents($archivo->getRealPath())
            : (is_string($archivo) && file_exists($archivo) ? file_get_contents($archivo) : $archivo);

        if (! is_string($bytes) || $bytes === '') {
            throw ValidationException::withMessages([
                'archivo' => "No se pudo leer el archivo {$nombreArchivo}.",
            ]);
        }

        $texto = $this->extraerTextoConCmap($bytes);
        if (trim($texto) === '') {
            throw ValidationException::withMessages([
                'archivo' => "El archivo {$nombreArchivo} no contiene texto digital legible. Asegúrese de subir la constancia oficial de SUNAT.",
            ]);
        }

        $datos = $this->parsearCampos($texto);

        if (empty($datos['monto']) || $datos['monto'] <= 0) {
            throw ValidationException::withMessages([
                'archivo' => "No se detectó un monto de depósito válido en {$nombreArchivo}. Verifique que sea una constancia oficial SPOT del Banco de la Nación.",
            ]);
        }

        // Validar si el RUC del proveedor de la constancia corresponde al cliente
        $rucCoincide = true;
        if ($cliente && ! empty($datos['proveedor_ruc'])) {
            $rucCoincide = ($cliente->ruc === $datos['proveedor_ruc']);
        }

        // Validar si ya fue registrada anteriormente (por número de constancia o número de operación + monto + fecha)
        $yaRegistrado = false;
        $movimientoExistenteId = null;

        if ($cliente) {
            $queryExistente = MovimientoDetraccion::where('cliente_id', $cliente->id)
                ->where('tipo', 'deposito');

            if (! empty($datos['numero_constancia'])) {
                $existente = (clone $queryExistente)->where('numero_constancia', $datos['numero_constancia'])->first();
                if ($existente) {
                    $yaRegistrado = true;
                    $movimientoExistenteId = $existente->id;
                }
            }

            if (! $yaRegistrado && ! empty($datos['numero_operacion']) && ! empty($datos['fecha_pago'])) {
                $existente = (clone $queryExistente)
                    ->where('numero_operacion', $datos['numero_operacion'])
                    ->where('fecha', $datos['fecha_pago'])
                    ->where('monto', $datos['monto'])
                    ->first();

                if ($existente) {
                    $yaRegistrado = true;
                    $movimientoExistenteId = $existente->id;
                }
            }
        }

        return [
            'valido' => true,
            'mensaje' => $yaRegistrado
                ? "La constancia N° {$datos['numero_constancia']} ya se encuentra registrada en los movimientos."
                : 'Constancia oficial de detracción leída correctamente.',
            'archivo_nombre' => $nombreArchivo,
            'ya_registrado' => $yaRegistrado,
            'movimiento_existente_id' => $movimientoExistenteId,
            'ruc_coincide' => $rucCoincide,
            'datos' => $datos,
        ];
    }

    /**
     * Extrae el texto del PDF y decodifica las fuentes con ToUnicode CMap por fuente.
     */
    private function extraerTextoConCmap(string $binario): string
    {
        // 1. Extraer flujos (streams) y asociar con sus IDs de objeto
        $streams = [];
        $offset = 0;
        $largo = strlen($binario);
        while ($offset < $largo && ($pos = strpos($binario, 'stream', $offset)) !== false) {
            $before = substr($binario, max(0, $pos - 150), 150);
            $objId = null;
            if (preg_match('/(\d+)\s+0\s+obj/s', $before, $om)) {
                $objId = (int) $om[1];
            }

            $start = $pos + 6;
            if (substr($binario, $start, 2) === "\r\n") {
                $start += 2;
            } elseif (substr($binario, $start, 1) === "\n" || substr($binario, $start, 1) === "\r") {
                $start += 1;
            }

            $end = strpos($binario, 'endstream', $start);
            if ($end === false) {
                break;
            }

            $streamData = substr($binario, $start, $end - $start);
            $uncompressed = @gzuncompress($streamData) ?: $streamData;
            if ($objId) {
                $streams[$objId] = $uncompressed;
            }

            $offset = $end + 9;
        }

        // 2. Extraer mapa de fuentes (/Font <</F4 4 0 R ...>>) a su ID de objeto
        $fontNameToObj = [];
        if (preg_match('/\/Font\s*<<(.*?)>>/s', $binario, $fm)) {
            preg_match_all('/\/([A-Za-z0-9_]+)\s+(\d+)\s+\d+\s+R/', $fm[1], $frs, PREG_SET_ORDER);
            foreach ($frs as $fr) {
                $fontNameToObj[$fr[1]] = (int) $fr[2];
            }
        }

        // Mapear objeto fuente a su objeto ToUnicode CMap
        $fontObjToUnicode = [];
        preg_match_all('/(\d+)\s+0\s+obj\s*<<(.*?)>>/s', $binario, $fos, PREG_SET_ORDER);
        foreach ($fos as $fo) {
            $objId = (int) $fo[1];
            if (preg_match('/\/ToUnicode\s+(\d+)\s+\d+\s+R/', $fo[2], $tu)) {
                $fontObjToUnicode[$objId] = (int) $tu[1];
            }
        }

        $fontNameToCmapObj = [];
        foreach ($fontNameToObj as $fName => $fObjId) {
            if (isset($fontObjToUnicode[$fObjId])) {
                $fontNameToCmapObj[$fName] = $fontObjToUnicode[$fObjId];
            }
        }

        // 3. Parsear los CMaps incrustados con alcance estricto por bloques (beginbfchar / beginbfrange)
        $cmaps = [];
        foreach ($streams as $objId => $data) {
            if (str_contains($data, 'beginbfchar') || str_contains($data, 'beginbfrange')) {
                $map = [];
                if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $data, $charBlocks)) {
                    foreach ($charBlocks[1] as $block) {
                        if (preg_match_all('/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $block, $bcm, PREG_SET_ORDER)) {
                            foreach ($bcm as $bc) {
                                $map[hexdec($bc[1])] = mb_chr(hexdec($bc[2]), 'UTF-8');
                            }
                        }
                    }
                }
                if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $data, $rangeBlocks)) {
                    foreach ($rangeBlocks[1] as $block) {
                        if (preg_match_all('/<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>\s+<([0-9a-fA-F]+)>/', $block, $brm, PREG_SET_ORDER)) {
                            foreach ($brm as $br) {
                                $src1 = hexdec($br[1]);
                                $src2 = hexdec($br[2]);
                                $dst = hexdec($br[3]);
                                for ($s = $src1; $s <= $src2; $s++) {
                                    $map[$s] = mb_chr($dst + ($s - $src1), 'UTF-8');
                                }
                            }
                        }
                    }
                }
                $cmaps[$objId] = $map;
            }
        }

        // 4. Decodificar el texto de los bloques de contenido aplicando el CMap correspondiente
        $lines = [];
        foreach ($streams as $objId => $content) {
            if (! str_contains($content, 'BT') || ! str_contains($content, 'ET')) {
                continue;
            }

            preg_match_all('/BT(.*?)ET/s', $content, $bts);
            foreach ($bts[1] as $block) {
                $currentFont = null;
                $line = '';

                preg_match_all('/\/([A-Za-z0-9_]+)\s+[\d\.]+\s+Tf|<([0-9a-fA-F]+)>\s*Tj|\((.*?)\)\s*Tj|\[(.*?)\]\s*TJ/s', $block, $ops, PREG_SET_ORDER);
                foreach ($ops as $op) {
                    if (! empty($op[1])) {
                        $currentFont = $op[1];
                    } elseif (! empty($op[2])) {
                        $hex = $op[2];
                        $cmapId = $fontNameToCmapObj[$currentFont] ?? null;
                        $cmap = $cmaps[$cmapId] ?? [];
                        for ($k = 0; $k < strlen($hex); $k += 4) {
                            $cid = hexdec(substr($hex, $k, 4));
                            $line .= $cmap[$cid] ?? '';
                        }
                    } elseif (isset($op[3]) && $op[3] !== '') {
                        $line .= $op[3];
                    } elseif (isset($op[4]) && $op[4] !== '') {
                        $cmapId = $fontNameToCmapObj[$currentFont] ?? null;
                        $cmap = $cmaps[$cmapId] ?? [];
                        preg_match_all('/<([0-9a-fA-F]+)>|\((.*?)\)/', $op[4], $items, PREG_SET_ORDER);
                        foreach ($items as $item) {
                            if (! empty($item[1])) {
                                $hex = $item[1];
                                for ($k = 0; $k < strlen($hex); $k += 4) {
                                    $cid = hexdec(substr($hex, $k, 4));
                                    $line .= $cmap[$cid] ?? '';
                                }
                            } elseif (isset($item[2])) {
                                $line .= $item[2];
                            }
                        }
                    }
                }
                if (trim($line) !== '') {
                    $lines[] = trim($line);
                }
            }
        }

        if (! empty($lines)) {
            return implode("\n", $lines);
        }

        // Respaldo de extracción básica si el documento no tiene fuentes con CMap incrustado
        return $this->extractor->extraer($binario);
    }

    /**
     * Extrae los campos fiscales y bancarios del texto de la constancia SPOT.
     *
     * @return array{
     *     numero_constancia: ?string,
     *     numero_operacion: ?string,
     *     fecha_pago: ?string,
     *     fecha_pago_formateada: ?string,
     *     hora_pago: ?string,
     *     periodo_tributario: ?string,
     *     monto: float,
     *     monto_formateado: string,
     *     cuenta_detracciones: ?string,
     *     proveedor_ruc: ?string,
     *     proveedor_razon_social: ?string,
     *     adquiriente_ruc: ?string,
     *     adquiriente_razon_social: ?string,
     *     tipo_comprobante: string,
     *     comprobante_numero: ?string,
     *     comprobante_formateado: ?string,
     *     tipo_bien: ?string,
     *     glosa_sugerida: string
     * }
     */
    private function parsearCampos(string $texto): array
    {
        $t = str_replace(["\r", "\t", '<'], ' ', $texto);
        $t = preg_replace('/\s+/', ' ', $t) ?? $t;

        $numeroConstancia = null;
        if (preg_match('/(?:constancia)[^\d]*(\d{6,14})/iu', $t, $m)) {
            $numeroConstancia = $m[1];
        }

        $numeroOperacion = null;
        if (preg_match('/(?:operaci[eēōoó\x{00F3}\x{0113}]n)[^\d]*(\d{8,14})/iu', $t, $m)) {
            $numeroOperacion = $m[1];
        }

        $cuentaDetracciones = null;
        if (preg_match('/(?:detracciones|naci[eēōoó\x{00F3}\x{0113}]n)[^\d]*(\d{10,14})/iu', $t, $m)) {
            $cuentaDetracciones = $m[1];
        }

        $proveedorRuc = null;
        if (preg_match('/(?:Ruc\s+del\s+Proveedor)[^\d]*(\d{11})/iu', $t, $m)) {
            $proveedorRuc = $m[1];
        }

        $proveedorRazonSocial = null;
        if (preg_match('/(?:Proveedor)[^\d]*\d{11}[^\w]*([A-Z\s]{4,60}?)(?:Tipo|Adquiriente|06)/iu', $t, $m)) {
            $rs = trim(preg_replace('/\bC\b/', '', $m[1]) ?? '');
            $rs = preg_replace('/\s+/', ' ', $rs) ?? $rs;
            $proveedorRazonSocial = trim($rs) ?: null;
        }

        $adquirienteRuc = null;
        if (preg_match('/(?:Documento\s+del\s+C?Adquiriente)[^\d]*(\d{11})/iu', $t, $m)) {
            $adquirienteRuc = $m[1];
        } elseif (preg_match('/(?:Adquiriente)[^\d]*(\d{11})/iu', $t, $m)) {
            $adquirienteRuc = $m[1];
        }

        $adquirienteRazonSocial = null;
        if (preg_match('/(?:Social\s+del\s+C?Adquiriente)[^\w]*([A-Z0-9\s,\.&]{4,70}?)(?:Tipo\s+de\s+Operaci|01)/iu', $t, $m)) {
            $rs = trim(preg_replace('/\bC\b/', '', $m[1]) ?? '');
            $rs = preg_replace('/\s+/', ' ', $rs) ?? $rs;
            $adquirienteRazonSocial = trim($rs) ?: null;
        }

        $monto = 0.0;
        if (preg_match('/(?:Monto\s+del\s+dep[eēoó\x{00F3}\x{0113}]sito)[^\d]*S\/?\s*([\d\.]+)/iu', $t, $m)) {
            $monto = (float) $m[1];
        }

        $fechaPago = null;
        $fechaPagoFormateada = null;
        $horaPago = null;
        if (preg_match('/(?:Fecha\s+y\s+hora\s+de\s+pago)[^\d]*(\d{2}\/\d{2}\/\d{4})\s*(\d{2}:\d{2}:\d{2})?/iu', $t, $m)) {
            $fechaPagoFormateada = $m[1];
            $horaPago = $m[2] ?? null;
            try {
                $fechaPago = Carbon::createFromFormat('d/m/Y', $m[1])->toDateString();
            } catch (\Throwable) {
                $fechaPago = now()->toDateString();
            }
        }

        $periodoTributario = null;
        if (preg_match('/(?:Periodo)[^\d]*(\d{4})(\d{2})/iu', $t, $m)) {
            $periodoTributario = $m[2].'-'.$m[1]; // MM-YYYY
        }

        $comprobanteNumero = null;
        $comprobanteFormateado = null;
        if (preg_match('/(?:Comprobante)[^EBF0-9]*([EBF]\d{3,4}\s*-\s*\d{1,8})/iu', $t, $m)) {
            $comp = str_replace(' ', '', $m[1]);
            $comprobanteNumero = $comp;
            if (preg_match('/^([A-Z0-9]+)-0*(\d+)$/', $comp, $cm)) {
                $comprobanteFormateado = $cm[1].'-'.$cm[2];
            } else {
                $comprobanteFormateado = $comp;
            }
        }

        $tipoBien = null;
        if (preg_match('/(?:Bien\s+[ēeóo\x{00F3}\x{0113}]?\s*servicio)[^\d]*(\d{3}\s*-\s*[^C\n<]+?)(?:Monto|S\/)/iu', $t, $m)) {
            $tipoBien = trim(preg_replace('/\s+/', ' ', $m[1]) ?? '') ?: null;
        }

        // Glosa limpia y profesional: "Factura de Venta E001-45"
        if ($comprobanteFormateado) {
            $glosaSugerida = "Factura de Venta {$comprobanteFormateado}";
        } elseif ($comprobanteNumero) {
            $glosaSugerida = "Factura de Venta {$comprobanteNumero}";
        } else {
            $glosaSugerida = 'Depósito Detracción SPOT BN';
        }

        return [
            'numero_constancia' => $numeroConstancia,
            'numero_operacion' => $numeroOperacion,
            'fecha_pago' => $fechaPago,
            'fecha_pago_formateada' => $fechaPagoFormateada,
            'hora_pago' => $horaPago,
            'periodo_tributario' => $periodoTributario,
            'monto' => $monto,
            'monto_formateado' => 'S/ '.number_format($monto, 2),
            'cuenta_detracciones' => $cuentaDetracciones,
            'proveedor_ruc' => $proveedorRuc,
            'proveedor_razon_social' => $proveedorRazonSocial,
            'adquiriente_ruc' => $adquirienteRuc,
            'adquiriente_razon_social' => $adquirienteRazonSocial,
            'tipo_comprobante' => 'Factura',
            'comprobante_numero' => $comprobanteNumero,
            'comprobante_formateado' => $comprobanteFormateado,
            'tipo_bien' => $tipoBien,
            'glosa_sugerida' => $glosaSugerida,
        ];
    }
}
