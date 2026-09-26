<?php

namespace App\Http\Controllers;

use App\Enums\EstadoCliente;
use App\Enums\RegimenTributario;
use App\Enums\SeccionExpediente;
use App\Models\Cliente;
use App\Services\Impuestos\ReporteImpuestosCliente;
use App\Services\SunafilBuzonSyncService;
use App\Services\SunatBuzonSyncService;
use App\Services\WhatsAppService;
use App\Support\AccesosSunat;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\Process\Process;
use Throwable;

class ClienteController extends Controller
{
    /**
     * Muestra el listado de clientes con búsqueda y paginación.
     */
    public function index(Request $request): View
    {
        $estadoFiltro = $request->input('estado', 'todos');

        $clientes = Cliente::query()
            ->paraUsuario()
            ->with('credencial')
            ->when($estadoFiltro !== 'todos' && in_array($estadoFiltro, ['activo', 'suspendido', 'retirado']), function ($query) use ($estadoFiltro) {
                $query->where('estado', $estadoFiltro);
            })
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $termino = $request->input('buscar');
                $query->where(function ($q) use ($termino) {
                    $q->where('razon_social', 'like', "%{$termino}%")
                        ->orWhere('ruc', 'like', "%{$termino}%");
                });
            })
            ->orderBy('numero')
            ->paginate(15)
            ->withQueryString();

        $conteoTodos = Cliente::paraUsuario()->count();
        $conteoActivos = Cliente::paraUsuario()->where('estado', 'activo')->count();
        $conteoSuspendidos = Cliente::paraUsuario()->where('estado', 'suspendido')->count();
        $conteoRetirados = Cliente::paraUsuario()->where('estado', 'retirado')->count();
        $totalEnPapelera = auth()->user()?->isAdmin() ? Cliente::onlyTrashed()->count() : 0;

        return view('clientes.index', compact(
            'clientes',
            'estadoFiltro',
            'conteoTodos',
            'conteoActivos',
            'conteoSuspendidos',
            'conteoRetirados',
            'totalEnPapelera'
        ));
    }

    /**
     * Muestra el formulario de onboarding (creación de cliente + credenciales).
     */
    public function create(): View
    {
        return view('clientes.create');
    }

    /**
     * Guarda el cliente y sus credenciales en una sola transacción atómica.
     *
     * Si alguno falla, se revierte todo para evitar datos huérfanos.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Datos Fiscales
            'razon_social' => ['required', 'string', 'max:255'],
            // El RUC es unico dentro del estudio, no a nivel de instalacion:
            // dos estudios distintos pueden atender al mismo contribuyente.
            'ruc' => [
                'required', 'string', 'size:11',
                Rule::unique('clientes', 'ruc')->where('estudio_id', Cliente::estudioActualId()),
            ],
            'regimen_tributario' => ['required', Rule::enum(RegimenTributario::class)],
            'estado_contribuyente' => ['nullable', 'string', 'max:50'],
            'condicion_domicilio' => ['nullable', 'string', 'max:50'],
            'tipo_contribuyente' => ['nullable', 'string', 'max:100'],
            'direccion_fiscal' => ['nullable', 'string', 'max:500'],
            'ubigeo' => ['nullable', 'string', 'max:10'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'es_buen_contribuyente' => ['nullable', 'boolean'],

            // Credenciales de Acceso
            'usuario_sol' => ['nullable', 'string', 'max:255'],
            'clave_sol' => ['nullable', 'string', 'max:255'],
            'usuario_afp' => ['nullable', 'string', 'max:255'],
            'clave_afp' => ['nullable', 'string', 'max:255'],
            'dni_titular' => ['nullable', 'string', 'size:8'],
            'cuenta_detracciones' => ['nullable', 'string', 'max:50'],
            'clave_detracciones' => ['nullable', 'string', 'max:255'],
            'banco_1_nombre' => ['nullable', 'string', 'max:100'],
            'banco_1_usuario' => ['nullable', 'string', 'max:255'],
            'banco_1_clave' => ['nullable', 'string', 'max:255'],
            'banco_1_notas' => ['nullable', 'string', 'max:1000'],
            'banco_2_nombre' => ['nullable', 'string', 'max:100'],
            'banco_2_usuario' => ['nullable', 'string', 'max:255'],
            'banco_2_clave' => ['nullable', 'string', 'max:255'],
            'banco_2_notas' => ['nullable', 'string', 'max:1000'],

            // Credenciales API SUNAT: opcionales al registrar el cliente.
            'sunat_client_id' => ['nullable', 'string', 'max:255'],
            'sunat_client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $clean = fn (?string $v) => ($v !== null && trim($v) !== '') ? trim($v) : null;

        $cliente = DB::transaction(function () use ($request, $validated, $clean) {
            $cliente = Cliente::create([
                'razon_social' => $validated['razon_social'],
                'ruc' => $validated['ruc'],
                'regimen_tributario' => $validated['regimen_tributario'],
                'estado_contribuyente' => $clean($validated['estado_contribuyente'] ?? null),
                'condicion_domicilio' => $clean($validated['condicion_domicilio'] ?? null),
                'tipo_contribuyente' => $clean($validated['tipo_contribuyente'] ?? null),
                'direccion_fiscal' => $clean($validated['direccion_fiscal'] ?? null),
                'ubigeo' => $clean($validated['ubigeo'] ?? null),
                'distrito' => $clean($validated['distrito'] ?? null),
                'provincia' => $clean($validated['provincia'] ?? null),
                'departamento' => $clean($validated['departamento'] ?? null),
                'fecha_sincronizacion_sunat' => ! empty($validated['estado_contribuyente']) ? now() : null,
                'correo' => $clean($validated['correo'] ?? null),
                'telefono' => $clean($validated['telefono'] ?? null),
                'es_buen_contribuyente' => $request->boolean('es_buen_contribuyente'),
                'estado' => 'activo',
                'sunat_client_id' => $clean($validated['sunat_client_id'] ?? null),
                'sunat_client_secret' => $clean($validated['sunat_client_secret'] ?? null),
            ]);

            $cliente->credencial()->create([
                'usuario_sol' => $clean($validated['usuario_sol'] ?? null),
                'clave_sol' => $clean($validated['clave_sol'] ?? null),
                'usuario_afp' => $clean($validated['usuario_afp'] ?? null),
                'clave_afp' => $clean($validated['clave_afp'] ?? null),
                'dni_titular' => $clean($validated['dni_titular'] ?? null),
                'cuenta_detracciones' => $clean($validated['cuenta_detracciones'] ?? null),
                'clave_detracciones' => $clean($validated['clave_detracciones'] ?? null),
                'banco_1_nombre' => $clean($validated['banco_1_nombre'] ?? null),
                'banco_1_usuario' => $clean($validated['banco_1_usuario'] ?? null),
                'banco_1_clave' => $clean($validated['banco_1_clave'] ?? null),
                'banco_1_notas' => $clean($validated['banco_1_notas'] ?? null),
                'banco_2_nombre' => $clean($validated['banco_2_nombre'] ?? null),
                'banco_2_usuario' => $clean($validated['banco_2_usuario'] ?? null),
                'banco_2_clave' => $clean($validated['banco_2_clave'] ?? null),
                'banco_2_notas' => $clean($validated['banco_2_notas'] ?? null),
            ]);

            return $cliente;
        });

        // Si quien crea el cliente es un asistente, auto-asignarlo a su cartera
        if (auth()->user()?->isAsistente()) {
            $cliente->asistentes()->syncWithoutDetaching([auth()->id()]);
        }

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente registrado exitosamente.');
    }

    /**
     * Hook para pruebas unitarias / feature tests del validador SOL.
     *
     * @var (Closure(string, string, string): array<string, mixed>)|null
     */
    protected static ?Closure $customSolValidator = null;

    /**
     * Permite fijar un validador SOL personalizado para testing.
     *
     * @param  (Closure(string, string, string): array<string, mixed>)|null  $validator
     */
    public static function setCustomSolValidator(?Closure $validator): void
    {
        self::$customSolValidator = $validator;
    }

    /**
     * Valida el formato y el algoritmo matemático oficial Módulo 11 de SUNAT para un RUC,
     * y comprueba si ya existe registrado en la base de datos del CRM.
     */
    public function validarRuc(Request $request): JsonResponse
    {
        $ruc = trim((string) $request->input('ruc', ''));

        if (! preg_match('/^\d{11}$/', $ruc)) {
            return response()->json([
                'valid' => false,
                'message' => 'El RUC debe contener exactamente 11 dígitos numéricos.',
            ], 422);
        }

        $prefijo = substr($ruc, 0, 2);
        $prefijosValidos = ['10', '15', '16', '17', '20'];
        if (! in_array($prefijo, $prefijosValidos, true)) {
            return response()->json([
                'valid' => false,
                'message' => 'El RUC debe iniciar con 10, 20, 15, 16 o 17.',
            ], 422);
        }

        // Algoritmo oficial SUNAT Módulo 11
        $factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        for ($i = 0; $i < 10; $i++) {
            $suma += ((int) $ruc[$i]) * $factores[$i];
        }
        $resto = 11 - ($suma % 11);
        $digitoVerificador = match ($resto) {
            10 => 0,
            11 => 1,
            default => $resto,
        };

        if ((int) $ruc[10] !== $digitoVerificador) {
            return response()->json([
                'valid' => false,
                'message' => "El RUC {$ruc} no supera la validación matemática oficial de SUNAT (dígito verificador incorrecto).",
            ], 422);
        }

        // Verificar unicidad en el CRM
        $clienteExistente = Cliente::where('ruc', $ruc)->first();
        if ($clienteExistente) {
            return response()->json([
                'valid' => true,
                'duplicate' => true,
                'message' => "Este RUC ya se encuentra registrado en el CRM: {$clienteExistente->razon_social}",
                'cliente' => [
                    'id' => $clienteExistente->id,
                    'razon_social' => $clienteExistente->razon_social,
                ],
            ]);
        }

        $tipoPersona = match ($prefijo) {
            '10' => 'Persona Natural con Negocio',
            '20' => 'Persona Jurídica (Empresa / Sociedad)',
            default => 'Contribuyente Especial',
        };

        // Consulta pública automática de Razón Social en SUNAT vía API
        $razonSocial = null;
        $estado = null;
        $condicion = null;
        $direccion = null;
        $ubigeo = null;
        $distrito = null;
        $provincia = null;
        $departamento = null;

        try {
            $apiRes = Http::timeout(3)->get("https://api.apis.net.pe/v1/ruc?numero={$ruc}");
            if ($apiRes->successful()) {
                $datosApi = $apiRes->json();
                if (! empty($datosApi['nombre'])) {
                    $razonSocial = trim($datosApi['nombre']);
                    $estado = trim($datosApi['estado'] ?? '');
                    $condicion = trim($datosApi['condicion'] ?? '');
                    $direccion = trim($datosApi['direccion'] ?? '');
                    $ubigeo = trim($datosApi['ubigeo'] ?? '');
                    $distrito = trim($datosApi['distrito'] ?? '');
                    $provincia = trim($datosApi['provincia'] ?? '');
                    $departamento = trim($datosApi['departamento'] ?? '');
                }
            }
        } catch (Throwable) {
            // Falla de red o timeout se ignora limpiamente para no degradar la experiencia
        }

        $mensaje = $razonSocial
            ? "RUC verificado ante SUNAT: {$razonSocial} (".trim("{$estado} / {$condicion}", ' /').')'
            : "RUC válido ante SUNAT ({$tipoPersona}).";

        return response()->json([
            'valid' => true,
            'duplicate' => false,
            'tipo_persona' => $tipoPersona,
            'razon_social' => $razonSocial,
            'estado_sunat' => $estado,
            'condicion_sunat' => $condicion,
            'direccion_sunat' => $direccion,
            'ubigeo' => $ubigeo,
            'distrito' => $distrito,
            'provincia' => $provincia,
            'departamento' => $departamento,
            'message' => $mensaje,
        ]);
    }

    /**
     * Valida de forma 100% silenciosa y en segundo plano las credenciales Clave SOL en el portal de SUNAT.
     */
    public function validarCredencialesSol(Request $request, SunatBuzonSyncService $syncService): JsonResponse
    {
        $validated = $request->validate([
            'ruc' => ['required', 'string', 'size:11'],
            'usuario_sol' => ['required', 'string', 'max:255'],
            'clave_sol' => ['required', 'string', 'max:255'],
        ]);

        $ruc = trim($validated['ruc']);
        $usuario = trim($validated['usuario_sol']);
        $clave = trim($validated['clave_sol']);

        if (! preg_match('/^\d{11}$/', $ruc)) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'El número de RUC no es válido.',
            ], 422);
        }

        // Hook para tests automatizados
        if (self::$customSolValidator !== null) {
            $mockResult = (self::$customSolValidator)($ruc, $usuario, $clave);

            return response()->json($mockResult, ($mockResult['valid'] ?? false) ? 200 : 422);
        }

        try {
            $nodeExecutable = $this->getNodeExecutable();
            $scriptPath = base_path('scripts/validate_sol.cjs');
            $env = $syncService->getEnvironmentVariables();

            $process = new Process(
                [$nodeExecutable, $scriptPath, $ruc, $usuario, $clave],
                base_path(),
                $env,
                null,
                35 // 35 segundos de timeout
            );

            $process->run();

            $output = trim($process->getOutput());
            $json = json_decode($output, true);

            if (! is_array($json) || ! isset($json['valid'])) {
                Log::warning("Respuesta no estándar de validate_sol.cjs para RUC {$ruc}: {$output}");

                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => 'SUNAT no respondió a tiempo. Puede registrar al cliente de todos modos.',
                ], 200);
            }

            return response()->json([
                'success' => (bool) ($json['success'] ?? true),
                'valid' => (bool) ($json['valid'] ?? false),
                'message' => (string) ($json['message'] ?? 'Validación completada.'),
                'razon_social' => $json['razon_social'] ?? null,
            ], ($json['valid'] ?? false) ? 200 : 422);

        } catch (Throwable $e) {
            Log::error("Error al validar credenciales SOL con SUNAT: {$e->getMessage()}", [
                'ruc' => $ruc,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'No se pudo conectar con el portal de SUNAT: '.$e->getMessage(),
            ], 200);
        }
    }

    /**
     * Muestra el expediente digital del cliente.
     * La sección de impuestos proyecta los tributos y pagos ya registrados en Control de Impuestos.
     */
    public function show(Request $request, Cliente $cliente, ReporteImpuestosCliente $reporteImpuestosCliente): View|RedirectResponse
    {
        if ($redireccion = $this->redirigirSeccionAntigua($request, $cliente)) {
            return $redireccion;
        }

        $seccion = SeccionExpediente::actual($request->route()?->getName());

        $cliente->load(['credencial', 'notificaciones', 'alertas']);

        $anioActual = (int) now()->year;
        $anioPedido = $request->query('anio', $anioActual);
        $anioReporte = is_numeric($anioPedido) ? (int) $anioPedido : $anioActual;
        if ($anioReporte < 2000 || $anioReporte > 2100) {
            $anioReporte = $anioActual;
        }
        $reporteImpuestos = $reporteImpuestosCliente->armar($cliente, $anioReporte);

        // Bóveda SIRE: Comprobantes y Métricas pre-filtradas exclusivamente para este cliente
        $sireQuery = $cliente->comprobantes();

        if (request()->filled('sire_tipo') && in_array(request('sire_tipo'), ['RCE', 'RVIE'])) {
            $sireQuery->where('tipo_registro', request('sire_tipo'));
        }
        if (request()->filled('sire_periodo')) {
            $sireQuery->where('periodo', request('sire_periodo'));
        }

        $sireStatsQuery = clone $sireQuery;
        $sireTotalBase = (float) $sireStatsQuery->sum('base_imponible');
        $sireTotalIgv = (float) $sireStatsQuery->sum('igv');
        $sireTotalFacturado = (float) $sireStatsQuery->sum('total');
        $sireTotalRegistros = (int) $sireStatsQuery->count();

        $comprobantesCliente = $sireQuery->orderBy('periodo', 'desc')->orderBy('id', 'desc')->get();

        $urlsSeccion = [];
        foreach (SeccionExpediente::cases() as $caso) {
            $urlsSeccion[$caso->value] = route($caso->ruta(), $cliente);
        }

        $consultaSire = [];
        foreach (['sire_tipo', 'sire_periodo'] as $filtro) {
            $valor = $request->query($filtro);
            if (is_string($valor) && $valor !== '') {
                $consultaSire[$filtro] = $valor;
            }
        }

        return view('clientes.show', compact(
            'cliente',
            'seccion',
            'urlsSeccion',
            'consultaSire',
            'reporteImpuestos',
            'comprobantesCliente',
            'sireTotalBase',
            'sireTotalIgv',
            'sireTotalFacturado',
            'sireTotalRegistros'
        ));
    }

    /**
     * La pestaña pasó de /operaciones a /impuestos. Conserva el ejercicio pedido.
     */
    public function redirigirOperaciones(Request $request, Cliente $cliente): RedirectResponse
    {
        return redirect()->route('clientes.operaciones', array_merge(
            $request->query(),
            ['cliente' => $cliente],
        ), 301);
    }

    /**
     * Los marcadores ?tab= apuntan a la ruta de la sección y conservan los filtros de SIRE.
     */
    private function redirigirSeccionAntigua(Request $request, Cliente $cliente): ?RedirectResponse
    {
        if (! $request->exists('tab')) {
            return null;
        }

        $seccion = SeccionExpediente::desde($request->query('tab'));
        $consulta = collect($request->except('tab'))
            ->filter(fn (mixed $valor): bool => is_scalar($valor) && $valor !== '')
            ->all();

        return redirect()->route(
            $seccion->ruta(),
            array_merge($consulta, ['cliente' => $cliente]),
            301,
        );
    }

    /**
     * Da de baja lógica (Soft Delete) al cliente.
     * Preserva íntegramente sus comprobantes, declaraciones PDT y expediente para fines de auditoría.
     */
    public function destroy(Cliente $cliente): RedirectResponse
    {
        $razonSocial = $cliente->razon_social;
        $numero = $cliente->numero;

        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', "El cliente N° {$numero} (\"{$razonSocial}\") fue enviado a la papelera de reciclaje. Sus comprobantes y declaraciones históricas han sido preservados.");
    }

    /**
     * Muestra el listado de clientes archivados en la papelera de reciclaje.
     */
    public function papelera(Request $request): View
    {
        $clientes = Cliente::onlyTrashed()
            ->with('credencial')
            ->withCount(['comprobantes', 'operaciones', 'alertas'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $termino = $request->input('buscar');
                $query->where(function ($q) use ($termino) {
                    $q->where('razon_social', 'like', "%{$termino}%")
                        ->orWhere('ruc', 'like', "%{$termino}%");
                });
            })
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $totalEnPapelera = Cliente::onlyTrashed()->count();

        return view('clientes.papelera', compact('clientes', 'totalEnPapelera'));
    }

    /**
     * Restaura un cliente archivado en la papelera de reciclaje, devolviéndolo a la cartera activa.
     */
    public function restaurar(int $id): RedirectResponse
    {
        $cliente = Cliente::onlyTrashed()->findOrFail($id);
        $cliente->restore();

        return redirect()->route('clientes.index')
            ->with('success', "El cliente N° {$cliente->numero} (\"{$cliente->razon_social}\") fue restaurado exitosamente a la cartera activa.");
    }

    /**
     * Elimina permanentemente un cliente y purga sus registros físicos de forma irreversible.
     * Acción exclusiva del Administrador del estudio.
     */
    public function forzarEliminacion(int $id): RedirectResponse
    {
        $cliente = Cliente::onlyTrashed()->findOrFail($id);
        $razonSocial = $cliente->razon_social;
        $numero = $cliente->numero;

        DB::transaction(function () use ($cliente) {
            $cliente->credencial()->delete();
            $cliente->notificaciones()->delete();
            $cliente->operaciones()->delete();
            $cliente->alertas()->delete();
            $cliente->comprobantes()->delete();
            $cliente->asistentes()->detach();
            $cliente->forceDelete();
        });

        return redirect()->route('clientes.papelera')
            ->with('success', "El cliente N° {$numero} (\"{$razonSocial}\") y todos sus registros físicos fueron eliminados permanentemente del sistema.");
    }

    /**
     * Actualiza únicamente los datos fiscales editables del cliente (correo, régimen tributario, teléfono, dirección y ubicación).
     * El RUC permanece inmutable para garantizar la consistencia e integridad tributaria.
     */
    public function updateDatosFiscales(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'regimen_tributario' => ['required', Rule::enum(RegimenTributario::class)],
            'correo' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'direccion_fiscal' => ['nullable', 'string', 'max:500'],
            'distrito' => ['nullable', 'string', 'max:100'],
            'provincia' => ['nullable', 'string', 'max:100'],
            'departamento' => ['nullable', 'string', 'max:100'],
            'ubigeo' => ['nullable', 'string', 'max:10'],
            'es_buen_contribuyente' => ['nullable', 'boolean'],
        ]);

        $cliente->update([
            'regimen_tributario' => $validated['regimen_tributario'],
            'correo' => $validated['correo'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'direccion_fiscal' => $validated['direccion_fiscal'] ?? $cliente->direccion_fiscal,
            'distrito' => $validated['distrito'] ?? $cliente->distrito,
            'provincia' => $validated['provincia'] ?? $cliente->provincia,
            'departamento' => $validated['departamento'] ?? $cliente->departamento,
            'ubigeo' => $validated['ubigeo'] ?? $cliente->ubigeo,
            'es_buen_contribuyente' => $request->boolean('es_buen_contribuyente'),
        ]);

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Datos fiscales actualizados correctamente.');
    }

    /**
     * Consulta en tiempo real la API de SUNAT con el RUC del cliente y sincroniza
     * su Estado (Activo/Baja/etc.), Condición (Habido/No Habido), Domicilio Fiscal y Ubicación.
     */
    public function actualizarEstadoSunat(Request $request, Cliente $cliente): JsonResponse|RedirectResponse
    {
        try {
            $apiRes = Http::timeout(5)->get("https://api.apis.net.pe/v1/ruc?numero={$cliente->ruc}");

            if (! $apiRes->successful()) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No se pudo obtener respuesta del servidor de SUNAT.',
                    ], 502);
                }

                return back()->with('error', 'No se pudo obtener respuesta del servidor de SUNAT.');
            }

            $datos = $apiRes->json();

            if (empty($datos['nombre'])) {
                if ($request->wantsJson() || $request->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'SUNAT no devolvió información para este RUC.',
                    ], 404);
                }

                return back()->with('error', 'SUNAT no devolvió información para este RUC.');
            }

            $prefijo = substr($cliente->ruc, 0, 2);
            $tipoPersona = match ($prefijo) {
                '10' => 'Persona Natural con Negocio',
                '20' => 'Persona Jurídica (Empresa / Sociedad)',
                default => 'Contribuyente Especial',
            };

            $cliente->update([
                'razon_social' => trim($datos['nombre']),
                'estado_contribuyente' => ! empty($datos['estado']) ? trim($datos['estado']) : $cliente->estado_contribuyente,
                'condicion_domicilio' => ! empty($datos['condicion']) ? trim($datos['condicion']) : $cliente->condicion_domicilio,
                'tipo_contribuyente' => $tipoPersona,
                'direccion_fiscal' => ! empty($datos['direccion']) ? trim($datos['direccion']) : $cliente->direccion_fiscal,
                'ubigeo' => ! empty($datos['ubigeo']) ? trim($datos['ubigeo']) : $cliente->ubigeo,
                'distrito' => ! empty($datos['distrito']) ? trim($datos['distrito']) : $cliente->distrito,
                'provincia' => ! empty($datos['provincia']) ? trim($datos['provincia']) : $cliente->provincia,
                'departamento' => ! empty($datos['departamento']) ? trim($datos['departamento']) : $cliente->departamento,
                'fecha_sincronizacion_sunat' => now(),
            ]);

            $mensaje = "Datos fiscales de SUNAT sincronizados con éxito ({$cliente->estado_contribuyente} / {$cliente->condicion_domicilio}).";

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'cliente' => [
                        'razon_social' => $cliente->razon_social,
                        'estado_contribuyente' => $cliente->estado_contribuyente,
                        'condicion_domicilio' => $cliente->condicion_domicilio,
                        'tipo_contribuyente' => $cliente->tipo_contribuyente,
                        'direccion_fiscal' => $cliente->direccion_fiscal,
                        'ubigeo' => $cliente->ubigeo,
                        'distrito' => $cliente->distrito,
                        'provincia' => $cliente->provincia,
                        'departamento' => $cliente->departamento,
                        'fecha_sincronizacion_sunat' => $cliente->fecha_sincronizacion_sunat?->diffForHumans() ?? 'Recién sincronizado',
                        'fecha_sincronizacion_exacta' => $cliente->fecha_sincronizacion_sunat?->format('d/m/Y H:i'),
                    ],
                ]);
            }

            return redirect()->route('clientes.show', $cliente)
                ->with('success', $mensaje);
        } catch (Throwable $e) {
            Log::warning("Error al sincronizar estado SUNAT para cliente {$cliente->id}: {$e->getMessage()}");

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error de conexión al consultar SUNAT: '.$e->getMessage(),
                ], 500);
            }

            return back()->with('error', 'Error al sincronizar estado con SUNAT: '.$e->getMessage());
        }
    }

    /**
     * Actualiza las credenciales de acceso del cliente (SOL, AFPnet y Detracciones Banco de la Nación).
     * Mantiene cifrado AES-256 en contraseñas y garantiza consistencia sin registros huérfanos.
     */
    public function updateCredenciales(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'usuario_sol' => ['nullable', 'string', 'max:255'],
            'clave_sol' => ['nullable', 'string', 'max:255'],
            'usuario_afp' => ['nullable', 'string', 'max:255'],
            'clave_afp' => ['nullable', 'string', 'max:255'],
            'dni_titular' => ['nullable', 'string', 'size:8'],
            'cuenta_detracciones' => ['nullable', 'string', 'max:50'],
            'clave_detracciones' => ['nullable', 'string', 'max:255'],
            'banco_1_nombre' => ['nullable', 'string', 'max:100'],
            'banco_1_usuario' => ['nullable', 'string', 'max:255'],
            'banco_1_clave' => ['nullable', 'string', 'max:255'],
            'banco_1_notas' => ['nullable', 'string', 'max:1000'],
            'banco_2_nombre' => ['nullable', 'string', 'max:100'],
            'banco_2_usuario' => ['nullable', 'string', 'max:255'],
            'banco_2_clave' => ['nullable', 'string', 'max:255'],
            'banco_2_notas' => ['nullable', 'string', 'max:1000'],
        ]);

        $clean = fn (?string $v) => ($v !== null && trim($v) !== '') ? trim($v) : null;

        DB::transaction(function () use ($cliente, $validated, $clean) {
            $cliente->credencial()->updateOrCreate(
                ['cliente_id' => $cliente->id],
                [
                    'usuario_sol' => $clean($validated['usuario_sol'] ?? null),
                    'clave_sol' => $clean($validated['clave_sol'] ?? null),
                    'usuario_afp' => $clean($validated['usuario_afp'] ?? null),
                    'clave_afp' => $clean($validated['clave_afp'] ?? null),
                    'dni_titular' => $clean($validated['dni_titular'] ?? null),
                    'cuenta_detracciones' => $clean($validated['cuenta_detracciones'] ?? null),
                    'clave_detracciones' => $clean($validated['clave_detracciones'] ?? null),
                    'banco_1_nombre' => $clean($validated['banco_1_nombre'] ?? null),
                    'banco_1_usuario' => $clean($validated['banco_1_usuario'] ?? null),
                    'banco_1_clave' => $clean($validated['banco_1_clave'] ?? null),
                    'banco_1_notas' => $clean($validated['banco_1_notas'] ?? null),
                    'banco_2_nombre' => $clean($validated['banco_2_nombre'] ?? null),
                    'banco_2_usuario' => $clean($validated['banco_2_usuario'] ?? null),
                    'banco_2_clave' => $clean($validated['banco_2_clave'] ?? null),
                    'banco_2_notas' => $clean($validated['banco_2_notas'] ?? null),
                ]
            );
        });

        return redirect()->route('clientes.show', $cliente)
            ->with('success', 'Credenciales de acceso actualizadas correctamente.');
    }

    /**
     * Actualiza el estado del cliente en el estudio contable (Activo, Suspendido, Retirado / Cambio de Contador).
     * Permite registrar fecha y mes de baja, motivo y notas de entrega o razones de cese sin perder el historial.
     */
    public function updateEstadoEstudio(Request $request, Cliente $cliente): RedirectResponse
    {
        $validated = $request->validate([
            'estado' => ['required', Rule::enum(EstadoCliente::class)],
            'fecha_baja' => ['nullable', 'date'],
            'mes_baja' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'motivo_baja' => ['nullable', 'string', 'max:255'],
            'notas_estado' => ['nullable', 'string', 'max:1000'],
        ], [
            'estado.required' => 'El estado en el estudio es obligatorio.',
            'estado.enum' => 'El estado debe ser Activo, Suspendido o Retirado.',
            'mes_baja.regex' => 'El mes de baja debe tener el formato AAAA-MM (ej. 2026-05).',
        ]);

        $clean = fn (?string $v) => ($v !== null && trim($v) !== '') ? trim($v) : null;

        $nuevoEstado = EstadoCliente::from($validated['estado']);
        $fechaBaja = $clean($validated['fecha_baja'] ?? null);
        $mesBaja = $clean($validated['mes_baja'] ?? null);
        $motivoBaja = $clean($validated['motivo_baja'] ?? null);
        $notasEstado = $clean($validated['notas_estado'] ?? null);

        if ($nuevoEstado === EstadoCliente::Activo) {
            $fechaBaja = null;
            $mesBaja = null;
            $motivoBaja = null;
        } elseif ($nuevoEstado === EstadoCliente::Retirado) {
            $fechaBaja = $fechaBaja ?: now()->toDateString();
            $mesBaja = $mesBaja ?: now()->format('Y-m');
            $motivoBaja = $motivoBaja ?: 'Cambio de contador';
        }

        $cliente->update([
            'estado' => $nuevoEstado,
            'fecha_baja' => $fechaBaja,
            'mes_baja' => $mesBaja,
            'motivo_baja' => $motivoBaja,
            'notas_estado' => $notasEstado,
        ]);

        return back()->with('success', "Estado del cliente en el estudio contable actualizado a: {$nuevoEstado->etiqueta()}.");
    }

    /**
     * Lanza el script Puppeteer para autenticarse en SUNAT SOL con las credenciales del cliente.
     *
     * Ejecuta en segundo plano sin ventana en Windows usando WScript/VBScript y popen/pclose,
     * garantizando que no se abra ninguna consola negra y sin bloquear el hilo de PHP.
     */
    public function loginSunat(Request $request, Cliente $cliente): RedirectResponse|JsonResponse
    {
        $cliente->load('credencial');

        if (! $cliente->credencial || ! $cliente->credencial->usuario_sol || ! $cliente->credencial->clave_sol) {
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este cliente no tiene credenciales SOL registradas.',
                ], 422);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('error', 'Este cliente no tiene credenciales SOL registradas.');
        }

        $ruc = $cliente->ruc;
        $usuarioSol = $cliente->credencial->usuario_sol;
        $claveSol = $cliente->credencial->clave_sol; // Desencriptada automáticamente por el cast 'encrypted'

        $baseDir = realpath(base_path()) ?: base_path();
        $scriptPath = realpath(base_path('scripts/sunat_login.cjs')) ?: base_path('scripts/sunat_login.cjs');
        $nodeExecutable = realpath($this->getNodeExecutable()) ?: $this->getNodeExecutable();

        // Control de concurrencia: si ya existía una sesión previa colgada para ESTE mismo cliente, limpiarla
        $pidFile = storage_path("framework/login_pid_sunat_{$ruc}.pid");
        if (file_exists($pidFile)) {
            $oldPid = (int) @file_get_contents($pidFile);
            if ($oldPid > 0) {
                if (DIRECTORY_SEPARATOR === '\\') {
                    @exec("taskkill /F /T /PID {$oldPid} 2>NUL");
                } else {
                    @exec("kill -9 {$oldPid} 2>/dev/null");
                }
            }
            @unlink($pidFile);
        }

        $signalFile = storage_path("framework/login_ready_sunat_{$ruc}.json");
        if (file_exists($signalFile)) {
            @unlink($signalFile);
        }

        try {
            // Cada ejecución tiene su archivo de log dedicado con uniqid para evitar bloqueos por uso concurrente en Windows
            $uniqueId = uniqid();
            $logPath = $baseDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR."sunat_login_{$ruc}_{$uniqueId}.log";

            if (DIRECTORY_SEPARATOR === '\\') {
                // En Windows: Ejecución 100% oculta sin consola negra usando WScript/VBScript nativo
                $vbsPath = realpath(base_path('scripts/run_hidden.vbs')) ?: base_path('scripts/run_hidden.vbs');
                $logArg = '--log-file='.$logPath;
                $destino = AccesosSunat::url($request->query('destino'));
                $command = sprintf(
                    'wscript.exe //B "%s" "%s" "%s" "%s" "%s" %s %s %s %s',
                    $vbsPath,
                    $baseDir,
                    $logArg,
                    $nodeExecutable,
                    $scriptPath,
                    escapeshellarg($ruc),
                    escapeshellarg($usuarioSol),
                    escapeshellarg($claveSol),
                    escapeshellarg($destino)
                );
            } else {
                $destino = AccesosSunat::url($request->query('destino'));
                $command = sprintf(
                    'cd "%s" && "%s" "%s" %s %s %s %s > "%s" 2>&1 &',
                    $baseDir,
                    $nodeExecutable,
                    $scriptPath,
                    escapeshellarg($ruc),
                    escapeshellarg($usuarioSol),
                    escapeshellarg($claveSol),
                    escapeshellarg($destino),
                    $logPath
                );
            }

            $this->iniciarProcesoEnSegundoPlano($command);

            Log::info("Automatización SUNAT iniciada en segundo plano para cliente {$cliente->ruc}");

            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                $senal = $this->esperarSenalDePortal($signalFile);

                if (! $senal['ok']) {
                    return response()->json([
                        'success' => false,
                        'message' => $senal['message'] ?? 'Error desconocido al iniciar sesión en SUNAT.',
                    ], 422);
                }

                $browserName = $senal['browser'];
                $mensaje = "🚀 Sesión SUNAT iniciada para {$cliente->razon_social}. {$browserName} abierto en primer plano.";

                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'portal' => 'SUNAT SOL',
                    'browser' => $browserName,
                    'cliente' => [
                        'id' => $cliente->id,
                        'ruc' => $cliente->ruc,
                        'razon_social' => $cliente->razon_social,
                    ],
                ]);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('success', "🚀 Sesión SUNAT iniciada para {$cliente->razon_social}. Chrome se abrirá automáticamente con el portal listo.");

        } catch (Throwable $e) {
            Log::error('Excepción al lanzar proceso SUNAT: '.$e->getMessage(), [
                'cliente_id' => $cliente->id,
                'exception' => $e,
            ]);

            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Excepción al ejecutar el proceso: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('error', 'Excepción al ejecutar el proceso: '.$e->getMessage());
        }
    }

    /**
     * Lanza el script Puppeteer para autenticarse en la Casilla Electrónica de SUNAFIL
     * con las credenciales SOL del cliente.
     *
     * Ejecuta en segundo plano sin ventana en Windows usando WScript/VBScript y popen/pclose,
     * garantizando que no se abra ninguna consola negra y sin bloquear el hilo de PHP.
     */
    public function loginSunafil(Request $request, Cliente $cliente): RedirectResponse|JsonResponse
    {
        $cliente->load('credencial');

        if (! $cliente->credencial || ! $cliente->credencial->usuario_sol || ! $cliente->credencial->clave_sol) {
            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Este cliente no tiene credenciales SOL registradas para ingresar a SUNAFIL.',
                ], 422);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('error', 'Este cliente no tiene credenciales SOL registradas para ingresar a SUNAFIL.');
        }

        $ruc = $cliente->ruc;
        $usuarioSol = $cliente->credencial->usuario_sol;
        $claveSol = $cliente->credencial->clave_sol;

        $baseDir = realpath(base_path()) ?: base_path();
        $scriptPath = realpath(base_path('scripts/sunafil_login.cjs')) ?: base_path('scripts/sunafil_login.cjs');
        $nodeExecutable = realpath($this->getNodeExecutable()) ?: $this->getNodeExecutable();

        // Control de concurrencia: si ya existía una sesión previa colgada para ESTE mismo cliente, limpiarla
        $pidFile = storage_path("framework/login_pid_sunafil_{$ruc}.pid");
        if (file_exists($pidFile)) {
            $oldPid = (int) @file_get_contents($pidFile);
            if ($oldPid > 0) {
                if (DIRECTORY_SEPARATOR === '\\') {
                    @exec("taskkill /F /T /PID {$oldPid} 2>NUL");
                } else {
                    @exec("kill -9 {$oldPid} 2>/dev/null");
                }
            }
            @unlink($pidFile);
        }

        $signalFile = storage_path("framework/login_ready_sunafil_{$ruc}.json");
        if (file_exists($signalFile)) {
            @unlink($signalFile);
        }

        try {
            // Cada ejecución tiene su archivo de log dedicado con uniqid para evitar bloqueos por uso concurrente en Windows
            $uniqueId = uniqid();
            $logPath = $baseDir.DIRECTORY_SEPARATOR.'storage'.DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR."sunafil_login_{$ruc}_{$uniqueId}.log";

            if (DIRECTORY_SEPARATOR === '\\') {
                // En Windows: Ejecución 100% oculta sin consola negra usando WScript/VBScript nativo
                $vbsPath = realpath(base_path('scripts/run_hidden.vbs')) ?: base_path('scripts/run_hidden.vbs');
                $logArg = '--log-file='.$logPath;
                $command = sprintf(
                    'wscript.exe //B "%s" "%s" "%s" "%s" "%s" %s %s %s',
                    $vbsPath,
                    $baseDir,
                    $logArg,
                    $nodeExecutable,
                    $scriptPath,
                    escapeshellarg($ruc),
                    escapeshellarg($usuarioSol),
                    escapeshellarg($claveSol)
                );
            } else {
                // En Linux/macOS: Desvinculación nativa con &
                $command = sprintf(
                    'cd "%s" && "%s" "%s" %s %s %s > "%s" 2>&1 &',
                    $baseDir,
                    $nodeExecutable,
                    $scriptPath,
                    escapeshellarg($ruc),
                    escapeshellarg($usuarioSol),
                    escapeshellarg($claveSol),
                    $logPath
                );
            }

            $this->iniciarProcesoEnSegundoPlano($command);

            Log::info("Automatización SUNAFIL iniciada en segundo plano para cliente {$cliente->ruc}");

            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                $senal = $this->esperarSenalDePortal($signalFile);

                if (! $senal['ok']) {
                    return response()->json([
                        'success' => false,
                        'message' => $senal['message'] ?? 'Error desconocido al iniciar sesión en SUNAFIL.',
                    ], 422);
                }

                $browserName = $senal['browser'];
                $mensaje = "👷 Sesión SUNAFIL iniciada para {$cliente->razon_social}. {$browserName} abierto en la Casilla Electrónica.";

                return response()->json([
                    'success' => true,
                    'message' => $mensaje,
                    'portal' => 'Casilla SUNAFIL',
                    'browser' => $browserName,
                    'cliente' => [
                        'id' => $cliente->id,
                        'ruc' => $cliente->ruc,
                        'razon_social' => $cliente->razon_social,
                    ],
                ]);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('success', "👷 Sesión SUNAFIL iniciada para {$cliente->razon_social}. Chrome se abrirá automáticamente en la Casilla Electrónica.");

        } catch (Throwable $e) {
            Log::error('Excepción al lanzar proceso SUNAFIL: '.$e->getMessage(), [
                'cliente_id' => $cliente->id,
                'exception' => $e,
            ]);

            if ($request->ajax() || $request->wantsJson() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
                return response()->json([
                    'success' => false,
                    'message' => 'Excepción al ejecutar el proceso SUNAFIL: '.$e->getMessage(),
                ], 500);
            }

            return redirect()->back(fallback: route('clientes.show', $cliente))
                ->with('error', 'Excepción al ejecutar el proceso SUNAFIL: '.$e->getMessage());
        }
    }

    /**
     * Sincroniza las notificaciones del Buzón Electrónico de SUNAT para el cliente.
     *
     * Utiliza SunatBuzonSyncService para extraer los mensajes completos con Puppeteer
     * y persistirlos en alertas_buzon y notificaciones, respondiendo en JSON si es AJAX.
     */
    public function syncBuzon(Request $request, Cliente $cliente, SunatBuzonSyncService $syncService, WhatsAppService $whatsAppService): JsonResponse|RedirectResponse
    {
        $resultado = $syncService->syncCliente($cliente);

        $despachadas = 0;
        if ($resultado['success'] && ! empty($resultado['nuevas_alertas_items'])) {
            $despachadas = $whatsAppService->despacharNuevasAlertas($resultado['nuevas_alertas_items']);
        }

        if ($request->expectsJson() || $request->ajax()) {
            $resultado['whatsapp_despachadas'] = $despachadas;

            return response()->json($resultado, $resultado['success'] ? 200 : 422);
        }

        if (! $resultado['success']) {
            return redirect()->route('clientes.show', $cliente)
                ->with('error', $resultado['message']);
        }

        $msg = $resultado['message'];
        if ($despachadas > 0) {
            $msg .= " Se despacharon {$despachadas} alertas a WhatsApp.";
        }

        return redirect()->route('clientes.notificaciones', $cliente)
            ->with('success', $msg);
    }

    /**
     * Sincroniza las notificaciones de la Casilla Electrónica de SUNAFIL para el cliente.
     */
    public function syncSunafilBuzon(Request $request, Cliente $cliente, SunafilBuzonSyncService $syncService, WhatsAppService $whatsAppService): JsonResponse|RedirectResponse
    {
        $resultado = $syncService->syncCliente($cliente);

        $despachadas = 0;
        if ($resultado['success'] && ! empty($resultado['nuevas_alertas_items'])) {
            $despachadas = $whatsAppService->despacharNuevasAlertas($resultado['nuevas_alertas_items']);
        }

        if ($request->expectsJson() || $request->ajax()) {
            $resultado['whatsapp_despachadas'] = $despachadas;

            return response()->json($resultado, $resultado['success'] ? 200 : 422);
        }

        if (! $resultado['success']) {
            return redirect()->route('clientes.show', $cliente)
                ->with('error', $resultado['message']);
        }

        $msg = $resultado['message'];
        if ($despachadas > 0) {
            $msg .= " Se despacharon {$despachadas} alertas a WhatsApp.";
        }

        return redirect()->route('clientes.notificaciones', $cliente)
            ->with('success', $msg);
    }

    /**
     * Actualiza las credenciales de API SUNAT (Client ID y Client Secret) del cliente.
     * El Client Secret se encripta automáticamente al guardar gracias al cast 'encrypted' del modelo.
     */
    public function updateApiCredentials(Request $request, Cliente $cliente): JsonResponse
    {
        $validated = $request->validate([
            'sunat_client_id' => ['nullable', 'string', 'max:255'],
            'sunat_client_secret' => ['nullable', 'string', 'max:500'],
        ]);

        $secret = $request->input('sunat_client_secret');

        // Si viene nulo, vacío, o contiene la palabra 'password' o asteriscos, se hace unset()
        if (
            blank($secret)
            || str_contains((string) $secret, '*')
            || str_contains((string) $secret, '•')
            || stripos((string) $secret, 'password') !== false
        ) {
            unset($validated['sunat_client_secret']);
        }

        $datosActualizar = [];

        // Un Client ID vacío significa "no tocar lo guardado". El formulario
        // se muestra siempre en blanco para que el navegador no lo rellene,
        // así que guardar sin escribir no debe borrar la credencial vigente.
        if (filled($validated['sunat_client_id'] ?? null)) {
            $datosActualizar['sunat_client_id'] = trim($validated['sunat_client_id'], " \t\n\r\0\x0B\"'");
        }

        if (array_key_exists('sunat_client_secret', $validated)) {
            $datosActualizar['sunat_client_secret'] = trim($validated['sunat_client_secret'], " \t\n\r\0\x0B\"'");
        }

        $cliente->update($datosActualizar);

        // Limpieza de caché del token para asegurar que si se modificaron credenciales, se solicite uno nuevo
        Cache::forget("sunat_token_{$cliente->id}");

        return response()->json([
            'success' => true,
            'message' => 'Credenciales API SUNAT actualizadas correctamente.',
        ]);
    }

    /**
     * Detecta dinámicamente la ruta del ejecutable de Node.js en Laragon o en el sistema operativo.
     */
    /**
     * Arranca el script sin heredar el pipe de PHP.
     *
     * pclose(popen()) esperaba a que cerraran el navegador, y el aviso de
     * "cargando" se quedaba en el CRM aunque SUNAT ya estuviera abierta.
     */
    private function iniciarProcesoEnSegundoPlano(string $command): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $handle = popen('cmd.exe /c start "" /B '.$command, 'r');
            if (is_resource($handle)) {
                pclose($handle);
            }

            return;
        }

        exec($command);
    }

    /**
     * Espera la señal "opening" (justo antes de mostrar la ventana) y suelta
     * la sesión para que el resto del CRM no quede bloqueado.
     *
     * @return array{ok: bool, browser: string, message: ?string}
     */
    private function esperarSenalDePortal(string $signalFile): array
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        $limite = microtime(true) + 70;
        $browser = 'Navegador';

        while (microtime(true) < $limite) {
            if (is_file($signalFile)) {
                $data = json_decode((string) @file_get_contents($signalFile), true);
                $estado = is_array($data) ? ($data['status'] ?? null) : null;

                if ($estado === 'opening' || $estado === 'ready') {
                    @unlink($signalFile);

                    return [
                        'ok' => true,
                        'browser' => is_string($data['browser'] ?? null) ? $data['browser'] : $browser,
                        'message' => null,
                    ];
                }

                if ($estado === 'error') {
                    $mensaje = is_string($data['message'] ?? null)
                        ? $data['message']
                        : 'No se pudo abrir el portal.';
                    @unlink($signalFile);

                    return [
                        'ok' => false,
                        'browser' => $browser,
                        'message' => $mensaje,
                    ];
                }
            }

            usleep(120000);
        }

        @unlink($signalFile);

        return [
            'ok' => false,
            'browser' => $browser,
            'message' => 'El portal está tardando más de lo esperado. Cierra la ventana si quedó abierta e inténtalo otra vez.',
        ];
    }

    private function getNodeExecutable(): string
    {
        $laragonNodes = glob(base_path('../../bin/nodejs/*/node.exe'));
        if (! empty($laragonNodes) && file_exists($laragonNodes[0])) {
            return str_replace('/', '\\', $laragonNodes[0]);
        }

        $possiblePaths = [
            'C:\\laragon\\bin\\nodejs\\node-v22\\node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            (getenv('LOCALAPPDATA') ?: '').'\\Programs\\nodejs\\node.exe',
        ];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return 'node';
    }
}
