<?php

namespace App\Http\Controllers;

use App\Services\CpePdfGeneratorService;
use App\Services\UblXmlParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class VisorXmlController extends Controller
{
    public function __construct(
        protected UblXmlParserService $parserService,
        protected CpePdfGeneratorService $pdfService
    ) {}

    /**
     * Muestra la interfaz interactiva del Visor XML & Generador Premium de CPE.
     */
    public function index(): View
    {
        return view('cpe.visor');
    }

    /**
     * Parsea un archivo XML UBL 2.1 en memoria y devuelve los datos estructurados en JSON.
     * Operación 100% Stateless: no toca la base de datos ni almacena archivos en disco.
     */
    public function parse(Request $request): JsonResponse
    {
        try {
            $xmlContent = $this->extraerContenidoXml($request);

            if (empty(trim($xmlContent))) {
                return response()->json([
                    'success' => false,
                    'error' => 'No se proporcionó ningún archivo XML o el contenido está vacío.',
                ], 422);
            }

            $datos = $this->parserService->parsearXml($xmlContent);

            $nombreOriginal = 'comprobante.xml';
            if ($request->hasFile('xml_file')) {
                $nombreOriginal = $request->file('xml_file')->getClientOriginalName();
            }

            return response()->json([
                'success' => true,
                'data' => $datos,
                'filename' => $nombreOriginal,
                'raw_xml' => $xmlContent,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al procesar el archivo XML: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Genera al vuelo el archivo PDF en memoria y lo descarga inmediatamente en el navegador del usuario.
     * Cero almacenamiento en el servidor y cero registros en base de datos.
     */
    public function descargarPdf(Request $request): Response|JsonResponse
    {
        try {
            $datos = [];

            if ($request->filled('cpe_data')) {
                $rawCpe = $request->input('cpe_data');
                $datos = is_array($rawCpe) ? $rawCpe : json_decode((string) $rawCpe, true);
            }

            if (empty($datos)) {
                $xmlContent = $this->extraerContenidoXml($request);
                if (! empty($xmlContent)) {
                    $datos = $this->parserService->parsearXml($xmlContent);
                }
            }

            if (empty($datos)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Datos insuficientes para generar el PDF.',
                ], 422);
            }

            $pdfBinario = $this->pdfService->generarPdfStream($datos);

            $rucEmisor = $datos['emisor']['ruc'] ?? '00000000000';
            $tipoDoc = $datos['tipo_comprobante'] ?? '01';
            $serie = $datos['serie'] ?? 'F001';
            $correlativo = str_pad($datos['correlativo'] ?? '1', 8, '0', STR_PAD_LEFT);

            $nombreArchivo = sprintf('%s-%s-%s-%s.pdf', $rucEmisor, $tipoDoc, $serie, $correlativo);

            return response($pdfBinario, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al generar el PDF: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Extrae el contenido del XML desde un archivo subido o un campo de texto/base64.
     */
    protected function extraerContenidoXml(Request $request): string
    {
        if ($request->hasFile('xml_file')) {
            $file = $request->file('xml_file');
            if ($file->isValid()) {
                return (string) file_get_contents($file->getRealPath());
            }
        }

        if ($request->filled('xml_content')) {
            $content = (string) $request->input('xml_content');
            // Si viene codificado en base64
            if (str_starts_with($content, 'data:') || (! str_contains($content, '<') && base64_encode(base64_decode($content, true) ?: '') === $content)) {
                $decoded = base64_decode(preg_replace('#^data:.*?base64,#', '', $content) ?: '', true);
                if ($decoded !== false) {
                    return $decoded;
                }
            }

            return $content;
        }

        return '';
    }
}
