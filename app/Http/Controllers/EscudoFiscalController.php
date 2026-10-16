<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\PadronRiesgoSunat;
use App\Services\SunatEscudoFiscalService;
use App\Services\SunatSscoPadronService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

class EscudoFiscalController extends Controller
{
    public function __construct(
        private SunatSscoPadronService $padronService,
        private SunatEscudoFiscalService $escudoService
    ) {}

    /**
     * Muestra la vista principal del módulo "Escudo Fiscal / Auditoría SSCO".
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Cliente::class);

        // Si la tabla está completamente vacía (primera vez que el usuario entra),
        // sincronizar automáticamente SSCO Firme y SSCO Vinculados (son archivos ligeros de ~120 KB en total)
        if (! app()->environment('testing') && PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)->count() === 0) {
            try {
                $this->padronService->sincronizarPadronesOficiales(false);
            } catch (Throwable) {
                // Continuar sin bloquear la vista si no hay internet momentáneo
            }
        }

        $clientes = Cliente::query()
            ->paraUsuario()
            ->where('estado', 'activo')
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social', 'sunat_cpe_client_id', 'sunat_cpe_client_secret']);

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
        ]);

        $estadisticasPadron = $this->padronService->obtenerEstadisticas();

        return view('escudo_fiscal.index', compact(
            'clientesData',
            'clienteSeleccionado',
            'estadisticasPadron'
        ));
    }

    /**
     * Ejecuta en tiempo real el Escáner Forense 360° sobre un RUC y opcionalmente un comprobante.
     */
    public function auditarRuc(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cliente::class);

        $validated = $request->validate([
            'ruc' => ['required', 'string', 'max:15'],
            'cliente_id' => ['nullable', 'integer'],
            'cod_comp' => ['nullable', 'string', 'max:4'],
            'numero_serie' => ['nullable', 'string', 'max:10'],
            'numero' => ['nullable', 'string', 'max:15'],
            'fecha_emision' => ['nullable', 'string', 'max:20'],
            'monto' => ['nullable', 'numeric'],
            'moneda' => ['nullable', 'string', 'in:PEN,USD'],
            'concepto_operacion' => ['nullable', 'string', 'max:500'],
            'cci_proveedor' => ['nullable', 'string', 'max:30'],
            'forzar_refresh' => ['nullable', 'boolean'],
        ]);

        $clienteConsultante = null;
        if (! empty($validated['cliente_id'])) {
            $clienteConsultante = Cliente::query()
                ->paraUsuario()
                ->whereKey($validated['cliente_id'])
                ->first();
        }

        $resultado = $this->escudoService->auditarProveedor($validated, $clienteConsultante);

        if (! ($resultado['success'] ?? false)) {
            return response()->json($resultado, 422);
        }

        return response()->json($resultado);
    }

    /**
     * Sincroniza manualmente desde los servidores de SUNAT los 3 padrones oficiales.
     */
    public function sincronizarPadrones(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cliente::class);

        $incluirCoactiva = $request->boolean('incluir_coactiva', true);

        try {
            $meta = $this->padronService->sincronizarPadronesOficiales($incluirCoactiva);
            $estadisticas = $this->padronService->obtenerEstadisticas();

            return response()->json([
                'success' => true,
                'message' => 'Padrones oficiales sincronizados correctamente desde los servidores de SUNAT.',
                'meta' => $meta,
                'estadisticas' => $estadisticas,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al sincronizar con SUNAT: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Busca y pagina los registros de los padrones oficiales (SSCO Firme, SSCO Vinculados y Deuda Coactiva).
     */
    public function listarPadron(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Cliente::class);

        $tipo = (string) $request->query('tipo', PadronRiesgoSunat::TIPO_SSCO_FIRME);
        $buscar = trim((string) $request->query('buscar', ''));
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        $query = PadronRiesgoSunat::query()
            ->when($tipo !== 'todos', fn ($q) => $q->where('tipo_padron', $tipo))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('ruc', 'like', "%{$buscar}%")
                        ->orWhere('razon_social', 'like', "%{$buscar}%")
                        ->orWhere('nombre_representante', 'like', "%{$buscar}%")
                        ->orWhere('doc_representante', 'like', "%{$buscar}%")
                        ->orWhere('resolucion_atribucion', 'like', "%{$buscar}%")
                        ->orWhere('ruc_ssco_origen', 'like', "%{$buscar}%");
                });
            });

        if ($tipo === PadronRiesgoSunat::TIPO_DEUDA_COACTIVA) {
            $query->orderByDesc('monto_deuda');
        } else {
            $query->orderBy('id');
        }

        $paginado = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'items' => $paginado->items(),
            'total' => $paginado->total(),
            'current_page' => $paginado->currentPage(),
            'last_page' => $paginado->lastPage(),
            'per_page' => $paginado->perPage(),
        ]);
    }

    /**
     * Exporta a CSV compatible con Excel el padrón filtrado.
     */
    public function exportarPadronCsv(Request $request): Response
    {
        Gate::authorize('viewAny', Cliente::class);

        $tipo = (string) $request->query('tipo', PadronRiesgoSunat::TIPO_SSCO_FIRME);
        $buscar = trim((string) $request->query('buscar', ''));

        $registros = PadronRiesgoSunat::query()
            ->when($tipo !== 'todos', fn ($q) => $q->where('tipo_padron', $tipo))
            ->when($buscar !== '', function ($q) use ($buscar) {
                $q->where(function ($sub) use ($buscar) {
                    $sub->where('ruc', 'like', "%{$buscar}%")
                        ->orWhere('razon_social', 'like', "%{$buscar}%")
                        ->orWhere('nombre_representante', 'like', "%{$buscar}%")
                        ->orWhere('doc_representante', 'like', "%{$buscar}%");
                });
            })
            ->limit(10000)
            ->get();

        $salida = fopen('php://memory', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");

        fputcsv($salida, [
            'Tipo Padrón',
            'RUC',
            'Razón Social',
            'Domicilio Fiscal / Dependencia',
            'Resolución / Causal',
            'Fecha Emisión Res.',
            'Fecha Firme Res.',
            'Doc. Representante Legal',
            'Nombre Representante Legal',
            'RUC SSCO Origen',
            'Monto Deuda Coactiva (S/)',
            'Fecha Publicación',
            'Corte SUNAT',
        ]);

        foreach ($registros as $r) {
            fputcsv($salida, [
                strtoupper($r->tipo_padron),
                $r->ruc,
                $r->razon_social,
                $r->domicilio_fiscal ?? $r->dependencia_sunat ?? '',
                $r->resolucion_atribucion ?? $r->supuesto_inclusion ?? '',
                $r->fecha_emision_resolucion ?? '',
                $r->fecha_firme_resolucion ?? '',
                $r->doc_representante ?? '',
                $r->nombre_representante ?? '',
                $r->ruc_ssco_origen ?? '',
                $r->monto_deuda !== null ? number_format((float) $r->monto_deuda, 2, '.', '') : '',
                $r->fecha_publicacion ?? '',
                $r->corte_sunat ?? '',
            ]);
        }

        rewind($salida);
        $csv = stream_get_contents($salida) ?: '';
        fclose($salida);

        $filename = 'Padron_SUNAT_'.strtoupper($tipo).'_'.now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
