<?php

namespace App\Http\Controllers;

use App\Exceptions\SunatApiException;
use App\Jobs\DownloadSunatXmlJob;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Services\ComprobanteDocumentService;
use App\Services\SunatSireService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class ComprobanteController extends Controller
{
    /**
     * Módulo Global: Bóveda de Comprobantes SIRE.
     * Muestra el resumen analítico y la tabla filtrable de comprobantes de compras (RCE) y ventas (RVIE).
     */
    public function index(Request $request): View
    {
        $query = Comprobante::with('cliente');

        // Filtro por Cliente
        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->query('cliente_id'));
        }

        // Filtro por Tipo de Registro (RCE / RVIE)
        if ($request->filled('tipo_registro') && in_array($request->query('tipo_registro'), ['RCE', 'RVIE'])) {
            $query->where('tipo_registro', $request->query('tipo_registro'));
        }

        // Filtro por Periodo Tributario (YYYY-MM)
        if ($request->filled('periodo')) {
            $query->where('periodo', $request->query('periodo'));
        }

        // Búsqueda general
        if ($request->filled('buscar')) {
            $term = '%'.trim($request->query('buscar')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('serie', 'like', $term)
                    ->orWhere('correlativo', 'like', $term)
                    ->orWhere('ruc_tercero', 'like', $term)
                    ->orWhere('razon_social_tercero', 'like', $term);
            });
        }

        // Métricas de tarjetas resumen calculadas sobre el dataset filtrado
        $statsQuery = clone $query;
        $totalBaseImponible = (float) $statsQuery->sum('base_imponible');
        $totalIgv = (float) $statsQuery->sum('igv');
        $totalFacturado = (float) $statsQuery->sum('total');
        $totalRegistros = (int) $statsQuery->count();

        // Paginación de resultados ordenados por periodo y más recientes
        $comprobantes = $query->orderBy('periodo', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(15)
            ->withQueryString();

        // Clientes activos para el selector
        $clientes = Cliente::orderBy('numero')->get(['id', 'numero', 'razon_social', 'ruc']);

        return view('comprobantes.index', compact(
            'comprobantes',
            'clientes',
            'totalBaseImponible',
            'totalIgv',
            'totalFacturado',
            'totalRegistros'
        ));
    }

    /**
     * Descarga de forma individual un archivo (XML, PDF, CDR) asegurando la ruta de storage local.
     */
    public function downloadFile(Comprobante $comprobante, string $tipo, ComprobanteDocumentService $docService): BinaryFileResponse|RedirectResponse
    {
        $tipo = strtolower(trim($tipo));
        if (! in_array($tipo, ['xml', 'pdf', 'cdr'])) {
            return back()->with('error', 'Tipo de archivo no admitido.');
        }

        $columnaRuta = "ruta_{$tipo}";
        $rutaRelativa = $comprobante->{$columnaRuta};

        if (empty($rutaRelativa)) {
            try {
                $rutaAbsoluta = $docService->asegurarDocumento($comprobante, $tipo);
            } catch (Throwable $e) {
                Log::error("Error al generar documento {$tipo} para comprobante {$comprobante->id}: {$e->getMessage()}");

                return back()->with('error', "No existe ruta registrada para el archivo {$tipo} de este comprobante.");
            }
        } else {
            $rutaAbsoluta = storage_path('app/'.ltrim($rutaRelativa, '/\\'));

            if (! file_exists($rutaAbsoluta)) {
                if (Storage::disk('local')->exists($rutaRelativa)) {
                    $rutaAbsoluta = Storage::disk('local')->path($rutaRelativa);
                } else {
                    return back()->with('error', "El archivo físico {$tipo} aún no ha sido descargado en el almacenamiento local.");
                }
            }
        }

        $extension = $tipo === 'cdr' ? 'zip' : $tipo;
        $nombreDescarga = sprintf(
            '%s-%s-%s.%s',
            $comprobante->tipo_comprobante ?: '01',
            $comprobante->numero_comprobante ?: 'COMP',
            strtoupper($tipo),
            $extension
        );

        return response()->download($rutaAbsoluta, $nombreDescarga);
    }

    /**
     * Empaqueta en un archivo ZIP los comprobantes y archivos existentes según los filtros seleccionados.
     */
    public function downloadZip(Request $request, ComprobanteDocumentService $docService): BinaryFileResponse|RedirectResponse
    {
        $query = Comprobante::with('cliente');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->query('cliente_id'));
        }
        if ($request->filled('tipo_registro') && in_array($request->query('tipo_registro'), ['RCE', 'RVIE'])) {
            $query->where('tipo_registro', $request->query('tipo_registro'));
        }
        if ($request->filled('periodo')) {
            $query->where('periodo', $request->query('periodo'));
        }

        $comprobantes = $query->orderBy('id', 'desc')->get();

        if ($comprobantes->isEmpty()) {
            return back()->with('error', 'No se encontraron comprobantes para empaquetar en ZIP.');
        }

        $zipFileName = sprintf(
            'SIRE_%s_%s_%s.zip',
            $request->query('tipo_registro', 'GLOBAL'),
            $request->query('periodo', date('Y-m')),
            date('Ymd_His')
        );

        $tempZipPath = tempnam(sys_get_temp_dir(), 'sire_zip_');
        $zip = new ZipArchive;

        if ($zip->open($tempZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return back()->with('error', 'No se pudo crear el archivo ZIP.');
        }

        // Manifiesto con detalle de los registros empaquetados
        $manifiesto = "=== BÓVEDA SIRE - GRUPO DC SOLUCIONES CONTABLES ===\n";
        $manifiesto .= 'Fecha de Exportación: '.now()->toDateTimeString()."\n";
        $manifiesto .= 'Total Comprobantes: '.$comprobantes->count()."\n";
        $manifiesto .= 'Total Facturado: S/ '.number_format($comprobantes->sum('total'), 2)."\n\n";
        $manifiesto .= str_repeat('-', 90)."\n";
        $manifiesto .= sprintf(
            "%-10s | %-6s | %-15s | %-12s | %-25s | %-10s\n",
            'Periodo', 'Tipo', 'Comprobante', 'RUC 3ro', 'Razón Social 3ro', 'Total'
        );
        $manifiesto .= str_repeat('-', 90)."\n";

        $archivosAdjuntos = 0;

        foreach ($comprobantes as $comp) {
            // Asegurar que cada comprobante cuente con sus archivos en disco
            try {
                $docService->asegurarDocumentos($comp);
            } catch (Throwable $e) {
                Log::warning("No se pudieron generar documentos para ZIP en comprobante {$comp->id}: {$e->getMessage()}");
            }

            $manifiesto .= sprintf(
                "%-10s | %-6s | %-15s | %-12s | %-25s | S/ %-8.2f\n",
                $comp->periodo,
                $comp->tipo_registro,
                $comp->tipo_comprobante.' '.$comp->numero_comprobante,
                $comp->ruc_tercero ?: '-',
                mb_substr($comp->razon_social_tercero ?: '-', 0, 25),
                (float) $comp->total
            );

            $rucCliente = $comp->cliente?->ruc ?? 'GENERICO';
            $carpetaEnZip = "{$rucCliente}/{$comp->periodo}/{$comp->tipo_registro}";

            // Adjuntar XML si existe
            if (! empty($comp->ruta_xml)) {
                $xmlPath = storage_path('app/'.ltrim($comp->ruta_xml, '/\\'));
                if (file_exists($xmlPath)) {
                    $zip->addFile($xmlPath, "{$carpetaEnZip}/".basename($xmlPath));
                    $archivosAdjuntos++;
                }
            }

            // Adjuntar PDF si existe
            if (! empty($comp->ruta_pdf)) {
                $pdfPath = storage_path('app/'.ltrim($comp->ruta_pdf, '/\\'));
                if (file_exists($pdfPath)) {
                    $zip->addFile($pdfPath, "{$carpetaEnZip}/".basename($pdfPath));
                    $archivosAdjuntos++;
                }
            }

            // Adjuntar CDR si existe
            if (! empty($comp->ruta_cdr)) {
                $cdrPath = storage_path('app/'.ltrim($comp->ruta_cdr, '/\\'));
                if (file_exists($cdrPath)) {
                    $zip->addFile($cdrPath, "{$carpetaEnZip}/".basename($cdrPath));
                    $archivosAdjuntos++;
                }
            }
        }

        $manifiesto .= str_repeat('-', 90)."\n";
        $manifiesto .= "Archivos físicos adjuntos en este paquete: {$archivosAdjuntos}\n";
        $zip->addFromString('MANIFIESTO_SIRE.txt', $manifiesto);

        $zip->close();

        return response()->download($tempZipPath, $zipFileName)->deleteFileAfterSend(true);
    }

    /**
     * Exporta los comprobantes filtrados a formato Excel / CSV estructurado con compatibilidad UTF-8 BOM.
     */
    public function exportExcel(Request $request): StreamedResponse
    {
        $query = Comprobante::with('cliente');

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->query('cliente_id'));
        }
        if ($request->filled('tipo_registro') && in_array($request->query('tipo_registro'), ['RCE', 'RVIE'])) {
            $query->where('tipo_registro', $request->query('tipo_registro'));
        }
        if ($request->filled('periodo')) {
            $query->where('periodo', $request->query('periodo'));
        }

        $comprobantes = $query->orderBy('periodo', 'desc')->orderBy('id', 'desc')->get();

        $fileName = sprintf('SIRE_Export_%s_%s.csv', $request->query('tipo_registro', 'TODOS'), date('Ymd_His'));

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($comprobantes) {
            $output = fopen('php://output', 'w');

            // Escribir marca UTF-8 Byte Order Mark (BOM) para compatibilidad total con Excel
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

            // Encabezados
            fputcsv($output, [
                'ID',
                'Periodo',
                'Registro',
                'Tipo Comprobante',
                'Descripción Comprobante',
                'Serie',
                'Correlativo',
                'RUC Cliente',
                'Razón Social Cliente',
                'RUC Tercero',
                'Razón Social Tercero',
                'Moneda',
                'Base Imponible',
                'IGV',
                'Total',
                'Estado SIRE',
                'Tiene XML',
                'Tiene CDR',
                'Tiene PDF',
            ]);

            foreach ($comprobantes as $comp) {
                fputcsv($output, [
                    $comp->id,
                    $comp->periodo,
                    $comp->tipo_registro,
                    $comp->tipo_comprobante,
                    $comp->descripcion_tipo_comprobante,
                    $comp->serie,
                    $comp->correlativo,
                    $comp->cliente?->ruc ?? '',
                    $comp->cliente?->razon_social ?? '',
                    $comp->ruc_tercero,
                    $comp->razon_social_tercero,
                    $comp->moneda,
                    number_format((float) $comp->base_imponible, 2, '.', ''),
                    number_format((float) $comp->igv, 2, '.', ''),
                    number_format((float) $comp->total, 2, '.', ''),
                    $comp->estado_sire,
                    $comp->tiene_xml ? 'SÍ' : 'NO',
                    $comp->tiene_cdr ? 'SÍ' : 'NO',
                    $comp->tiene_pdf ? 'SÍ' : 'NO',
                ]);
            }

            fclose($output);
        }, 200, $headers);
    }

    /**
     * Importa la propuesta de compras (RCE) o ventas (RVIE) desde la API de SUNAT para el cliente y periodo seleccionados.
     */
    public function importar(Request $request, SunatSireService $sireService): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer', Cliente::reglaPerteneceACartera()],
            'registro' => ['required', 'string'],
            'periodo' => ['required', 'string', 'max:10'],
            'ticket' => ['nullable', 'string', 'max:60'],
        ], [
            'registro.required' => 'Por favor, selecciona específicamente RCE o RVIE para importar.',
        ]);

        $tipoRegistro = strtoupper(trim($validated['registro']));

        if (! in_array($tipoRegistro, ['RCE', 'RVIE'])) {
            return response()->json([
                'success' => false,
                'message' => 'Por favor, selecciona específicamente RCE o RVIE para importar.',
            ], 422);
        }

        $cliente = Cliente::findOrFail($validated['cliente_id']);

        if (! $cliente->isActivo()) {
            return response()->json([
                'success' => false,
                'message' => "El cliente {$cliente->razon_social} se encuentra {$cliente->estado->value}. No es posible importar comprobantes para clientes inactivos o retirados.",
            ], 422);
        }

        $ticket = ! empty($validated['ticket']) ? trim($validated['ticket']) : null;

        try {
            if ($ticket) {
                // Reanudar sondeo de ticket pendiente
                $resultado = $sireService->procesarTicketPendiente($cliente, $tipoRegistro, $validated['periodo'], $ticket);
            } else {
                // Limpieza forzada de caché de token para asegurar credenciales frescas
                Cache::forget("sunat_token_{$cliente->id}");

                // Iniciar flujo asíncrono oficial
                $resultado = $tipoRegistro === 'RCE'
                    ? $sireService->importarPropuestaRCE($cliente, $validated['periodo'])
                    : $sireService->importarPropuestaRVIE($cliente, $validated['periodo']);
            }

            if (($resultado['status'] ?? '') === 'procesando') {
                return response()->json([
                    'success' => true,
                    'status' => 'procesando',
                    'mensaje' => $resultado['mensaje'] ?? 'Ticket generado en SUNAT. Procesando descarga...',
                    'ticket' => $resultado['ticket'],
                    'cliente_id' => $cliente->id,
                    'registro' => $tipoRegistro,
                    'periodo' => $validated['periodo'],
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 'completado',
                'message' => $resultado['message'] ?? ($tipoRegistro === 'RCE'
                    ? 'Comprobantes de compras (RCE) importados exitosamente.'
                    : 'Comprobantes de ventas (RVIE) importados exitosamente.'),
                'recarga' => true,
                'data' => $resultado,
            ]);
        } catch (SunatApiException $e) {
            Log::warning("Fallo al importar {$tipoRegistro} desde SUNAT para cliente {$cliente->ruc}: {$e->getMessage()}");

            $httpStatus = $e->getHttpStatus();
            $statusCode = ($httpStatus && $httpStatus >= 400 && $httpStatus < 600) ? $httpStatus : 422;

            $mensaje = str_starts_with($e->getMessage(), 'Error SUNAT')
                ? $e->getMessage()
                : 'Error SUNAT: '.$e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $mensaje,
                'status_code' => $statusCode,
            ], $statusCode);
        } catch (Throwable $e) {
            Log::error("Excepción crítica al importar {$tipoRegistro} para cliente {$cliente->ruc}: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Error inesperado al importar desde SUNAT: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Endpoint reactivo de sondeo AJAX para actualizar el estado de descarga de comprobantes en tiempo real.
     */
    public function syncStatus(Request $request): JsonResponse
    {
        $ids = $request->input('ids');
        if (is_string($ids)) {
            $ids = array_filter(array_map('intval', explode(',', $ids)));
        } elseif (! is_array($ids)) {
            $ids = [];
        }

        if (empty($ids)) {
            return response()->json(['success' => true, 'comprobantes' => []]);
        }

        $comprobantes = Comprobante::whereIn('id', $ids)->get();

        $data = $comprobantes->map(fn (Comprobante $c) => [
            'id' => $c->id,
            'estado_descarga' => $c->estado_descarga ?: 'pendiente',
            'estado_xml' => $c->estado_xml ?: ($c->tiene_xml ? 'disponible' : 'pendiente'),
            'estado_cdr' => $c->estado_cdr ?: ($c->tiene_cdr ? 'disponible' : 'pendiente'),
            'estado_pdf' => $c->estado_pdf ?: ($c->tiene_pdf ? 'disponible' : 'pendiente'),
            'tiene_xml' => $c->tiene_xml,
            'tiene_cdr' => $c->tiene_cdr,
            'tiene_pdf' => $c->tiene_pdf,
            'ruta_xml' => $c->ruta_xml,
            'ruta_cdr' => $c->ruta_cdr,
            'ruta_pdf' => $c->ruta_pdf,
            'mensaje_soap' => $c->mensaje_soap,
            'url_xml' => route('comprobantes.download', [$c, 'xml']),
            'url_cdr' => route('comprobantes.download', [$c, 'cdr']),
            'url_pdf' => route('comprobantes.download', [$c, 'pdf']),
        ]);

        return response()->json([
            'success' => true,
            'comprobantes' => $data,
        ]);
    }

    /**
     * Reintenta o activa individualmente la consulta SOAP y descarga de un comprobante.
     */
    public function reintentarDescarga(Comprobante $comprobante): JsonResponse
    {
        $comprobante->estado_descarga = 'pendiente';
        $comprobante->estado_xml = 'pendiente';
        $comprobante->estado_cdr = 'pendiente';
        $comprobante->estado_pdf = 'pendiente';
        $comprobante->mensaje_soap = null;
        $comprobante->saveQuietly();

        DownloadSunatXmlJob::dispatch($comprobante);

        return response()->json([
            'success' => true,
            'message' => "Descarga SOAP encolada para el comprobante {$comprobante->numero_comprobante}.",
        ]);
    }

    /**
     * Sincroniza en lote los comprobantes pendientes o con error aplicando anti-bloqueo.
     */
    public function sincronizarLote(Request $request): JsonResponse
    {
        $query = Comprobante::query();

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->input('cliente_id'));
        }
        if ($request->filled('tipo_registro')) {
            $query->where('tipo_registro', $request->input('tipo_registro'));
        }
        if ($request->filled('periodo')) {
            $query->where('periodo', $request->input('periodo'));
        }
        if ($request->filled('ids') && is_array($request->input('ids'))) {
            $query->whereIn('id', $request->input('ids'));
        }

        // Seleccionar los que no estén completamente disponibles o tengan estado distinto a 'disponible'
        $pendientes = $query->where(function ($q) {
            $q->where('estado_descarga', '!=', 'disponible')
                ->orWhereNull('ruta_xml')
                ->orWhereNull('ruta_cdr')
                ->orWhereNull('ruta_pdf');
        })->get();

        $despachados = 0;
        foreach ($pendientes as $index => $comp) {
            $comp->estado_descarga = 'pendiente';
            $comp->saveQuietly();

            // Delay escalonado de 2 segundos por comprobante
            DownloadSunatXmlJob::dispatch($comp)->delay(now()->addSeconds($index * 2));
            $despachados++;
        }

        return response()->json([
            'success' => true,
            'message' => "Se encolaron {$despachados} comprobantes para consulta SOAP con delay de protección anti-bloqueo.",
            'total' => $despachados,
        ]);
    }

    /**
     * Helper para garantizar que exista la ruta física en el disco local:
     * storage/app/comprobantes/{ruc_cliente}/{anio}/{mes}/{tipo_registro}/
     */
    public static function asegurarRutaStorage(string $rucCliente, string $periodo, string $tipoRegistro): string
    {
        $relPath = Comprobante::storagePathFor($rucCliente, $periodo, $tipoRegistro);
        $absPath = storage_path('app/'.$relPath);

        if (! is_dir($absPath)) {
            mkdir($absPath, 0755, true);
        }

        return $absPath;
    }
}
