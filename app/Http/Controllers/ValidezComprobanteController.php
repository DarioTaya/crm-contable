<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Services\CpeExcelParserService;
use App\Services\SunatValidezCpeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class ValidezComprobanteController extends Controller
{
    public function __construct(
        private SunatValidezCpeService $validezService,
        private CpeExcelParserService $parserService
    ) {}

    /**
     * Muestra la vista principal del módulo de Consulta Integrada de Validez de Comprobantes.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Cliente::class);

        // Listado de clientes del estudio visibles para el usuario actual
        $clientes = Cliente::query()
            ->paraUsuario()
            ->where('estado', 'activo')
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social', 'sunat_cpe_client_id', 'sunat_cpe_client_secret']);

        // Empresa preseleccionada: activa en sesión o el primer cliente con credenciales CPE (ej. Huamani Apaza Yanira)
        $empresaActivaId = $request->session()->get('empresa_activa_id');
        $clienteSeleccionado = null;

        if ($empresaActivaId) {
            $clienteSeleccionado = $clientes->firstWhere('id', (int) $empresaActivaId);
        }

        if (! $clienteSeleccionado) {
            $clienteSeleccionado = $clientes->first(fn (Cliente $c) => $c->tieneCredencialesCpe()) ?? $clientes->first();
        }

        $clientesData = $clientes->map(fn (Cliente $c) => [
            'id' => $c->id,
            'ruc' => $c->ruc,
            'razon_social' => $c->razon_social,
            'tiene_credenciales' => $c->tieneCredencialesCpe(),
            'cpe_client_id' => $c->sunat_cpe_client_id,
        ]);

        $tiposComprobante = SunatValidezCpeService::TIPOS_COMPROBANTE;

        return view('validez_cpe.index', compact(
            'clientesData',
            'clienteSeleccionado',
            'tiposComprobante'
        ));
    }

    /**
     * Realiza la validación en tiempo real de un comprobante individual contra SUNAT.
     */
    public function validarIndividual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'numRuc' => ['required', 'string', 'size:11'],
            'codComp' => ['required', 'string', 'max:4'],
            'numeroSerie' => ['required', 'string', 'max:10'],
            'numero' => ['required', 'string', 'max:15'],
            'fechaEmision' => ['required', 'string', 'max:20'],
            'monto' => ['nullable', 'numeric'],
        ]);

        $cliente = Cliente::query()
            ->paraUsuario()
            ->whereKey($validated['cliente_id'])
            ->firstOrFail();

        Gate::authorize('view', $cliente);

        if (! $cliente->tieneCredencialesCpe()) {
            return response()->json([
                'success' => false,
                'message' => "La empresa '{$cliente->razon_social}' no tiene configuradas las Credenciales de API SUNAT para Validez de Comprobantes.",
            ], 422);
        }

        try {
            $resultado = $this->validezService->validarComprobante($cliente, $validated);

            return response()->json($resultado);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Carga y procesa un archivo Excel / CSV en memoria sin alterar ni ensuciar la base de datos.
     */
    public function cargarArchivo(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'max:15360'], // Máximo 15MB
            'cliente_id' => ['required', 'integer'],
            'es_registro_ventas' => ['nullable', 'boolean'],
        ]);

        $cliente = Cliente::query()
            ->paraUsuario()
            ->whereKey($request->input('cliente_id'))
            ->firstOrFail();

        Gate::authorize('view', $cliente);

        $esVentas = filter_var($request->input('es_registro_ventas'), FILTER_VALIDATE_BOOLEAN);
        $rucEmisorPorDefecto = $esVentas ? $cliente->ruc : null;

        try {
            $resultado = $this->parserService->procesarArchivo(
                $request->file('archivo'),
                $rucEmisorPorDefecto
            );

            return response()->json([
                'success' => true,
                'total' => $resultado['total'],
                'formato_detectado' => $resultado['formato_detectado'],
                'filas' => $resultado['filas'],
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al leer el archivo: '.$e->getMessage(),
            ], 422);
        }
    }

    /**
     * Valida un lote o fragmento de comprobantes de forma concurrente y controlada.
     */
    public function validarLote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'comprobantes' => ['required', 'array', 'min:1', 'max:25'],
            'comprobantes.*.numRuc' => ['required', 'string'],
            'comprobantes.*.codComp' => ['required', 'string'],
            'comprobantes.*.numeroSerie' => ['required', 'string'],
            'comprobantes.*.numero' => ['required', 'string'],
            'comprobantes.*.fechaEmision' => ['nullable', 'string'],
            'comprobantes.*.monto' => ['nullable'],
            'comprobantes.*.razonSocial' => ['nullable', 'string'],
            'comprobantes.*.item' => ['nullable', 'integer'],
        ]);

        $cliente = Cliente::query()
            ->paraUsuario()
            ->whereKey($validated['cliente_id'])
            ->firstOrFail();

        Gate::authorize('view', $cliente);

        if (! $cliente->tieneCredencialesCpe()) {
            return response()->json([
                'success' => false,
                'message' => 'El cliente no tiene credenciales configuradas.',
            ], 422);
        }

        $resultados = $this->validezService->validarLoteComprobantes($cliente, $validated['comprobantes']);

        return response()->json([
            'success' => true,
            'resultados' => $resultados,
        ]);
    }

    /**
     * Descarga la plantilla oficial en formato Excel (.xlsx) o CSV para validación masiva.
     */
    public function descargarPlantilla(Request $request): Response
    {
        $formato = strtolower((string) $request->query('formato', 'xlsx'));

        if ($formato === 'csv') {
            $csv = $this->parserService->generarPlantillaCsv();

            return response($csv, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="plantilla_validacion_cpe_crm.csv"',
            ]);
        }

        $xlsx = $this->parserService->generarPlantillaXlsx();

        return response($xlsx, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla_validacion_cpe_crm.xlsx"',
        ]);
    }

    /**
     * Exporta los resultados de la auditoría efectuada a un archivo CSV compatible con Excel.
     */
    public function exportarReporte(Request $request): Response
    {
        $validated = $request->validate([
            'cliente_id' => ['required', 'integer'],
            'resultados' => ['required', 'array'],
        ]);

        $cliente = Cliente::query()
            ->paraUsuario()
            ->whereKey($validated['cliente_id'])
            ->firstOrFail();

        Gate::authorize('view', $cliente);

        $csv = $this->parserService->exportarResultadosCsv(
            $validated['resultados'],
            $cliente->razon_social
        );

        $nombreArchivo = 'Auditoria_CPE_'.Str::slug($cliente->razon_social, '_').'_'.now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
        ]);
    }

    /**
     * Prueba en vivo la conexión de las credenciales de Validez del cliente.
     */
    public function probarConexion(Cliente $cliente): JsonResponse
    {
        Gate::authorize('view', $cliente);

        $resultado = $this->validezService->testConnection($cliente);

        return response()->json($resultado);
    }
}
