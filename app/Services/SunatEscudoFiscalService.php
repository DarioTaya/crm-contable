<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\PadronRiesgoSunat;
use App\Services\Clientes\ConsultaPadronRuc;
use App\Services\Clientes\RucSunat;
use App\Support\NodeBinary;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Throwable;

class SunatEscudoFiscalService
{
    /**
     * Hook opcional para inyectar un inspector RUC simulado en pruebas automatizadas (PHPUnit).
     *
     * @var (Closure(string): array<string, mixed>)|null
     */
    protected static ?Closure $customScraper = null;

    public static function setCustomScraper(?Closure $scraper): void
    {
        self::$customScraper = $scraper;
    }

    public function __construct(
        private ConsultaPadronRuc $consultaPadronRuc,
        private SunatValidezCpeService $validezCpeService
    ) {}

    /**
     * Ejecuta una Auditoría Forense Integral (Escudo Anti-Fraude 360°) sobre un RUC proveedor
     * y opcionalmente valida un comprobante de pago y su congruencia de giro económico y bancarización.
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function auditarProveedor(array $params, ?Cliente $clienteConsultante = null): array
    {
        $ruc = preg_replace('/\D/', '', (string) ($params['ruc'] ?? ''));
        $forzarRefresh = (bool) ($params['forzar_refresh'] ?? false);

        $errorRuc = RucSunat::mensajeSiInvalido($ruc);
        if ($errorRuc !== null) {
            return [
                'success' => false,
                'message' => $errorRuc,
            ];
        }

        // 1. Cruce contra Padrones Negros Oficiales en Base de Datos Local (Instantáneo)
        $sscoFirme = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)
            ->where('ruc', $ruc)
            ->first();

        $sscoVinculados = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_VINCULADO)
            ->where(function ($q) use ($ruc) {
                $q->where('ruc', $ruc)->orWhere('ruc_ssco_origen', $ruc);
            })
            ->get();

        $deudaPadronMasivo = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)
            ->where('ruc', $ruc)
            ->first();

        // 2. Obtener Ficha Extendida de SUNAT (Scraper Forense o Fallback API Padrón)
        $fichaExtendida = $this->obtenerFichaExtendidaRuc($ruc, $forzarRefresh);
        $padronBasico = $this->consultaPadronRuc->consultar($ruc, true);

        $ficha = $this->consolidarFichaContribuyente($ruc, $fichaExtendida, $padronBasico);

        // 3. Cruce Forense de Representantes Legales contra la Lista Negra de Gerentes/Socios SSCO
        $alertasRepresentantes = $this->cruzarRepresentantesContraSsco($ruc, $ficha['representantes_legales']);

        // 4. Validación opcional de Comprobante de Pago (API SUNAT OAuth2)
        $resultadoCpe = null;
        $tieneDatosCpe = filled($params['numero_serie'] ?? null) && filled($params['numero'] ?? null);
        if ($tieneDatosCpe && $clienteConsultante && $clienteConsultante->tieneCredencialesCpe()) {
            $resultadoCpe = $this->validezCpeService->validarComprobante($clienteConsultante, [
                'numRuc' => $ruc,
                'codComp' => $params['cod_comp'] ?? '01',
                'numeroSerie' => $params['numero_serie'],
                'numero' => $params['numero'],
                'fechaEmision' => $params['fecha_emision'] ?? now()->format('d/m/Y'),
                'monto' => $params['monto'] ?? 0,
            ]);
        }

        // 5. Análisis de Congruencia de Giro / Actividad Económica (CIIU vs Concepto Facturado)
        $conceptoOperacion = trim((string) ($params['concepto_operacion'] ?? ''));
        $analisisCongruencia = $this->evaluarCongruenciaGiro($ficha['actividades_economicas'], $conceptoOperacion);

        // 6. Análisis de Bancarización, Detracción y CCI (Ley N.º 28194 / D.Leg. 1529 - S/ 2,000 o US$ 500)
        $montoOperacion = isset($params['monto']) && is_numeric($params['monto']) ? (float) $params['monto'] : null;
        $monedaOperacion = strtoupper(trim((string) ($params['moneda'] ?? 'PEN')));
        $cciProveedor = trim((string) ($params['cci_proveedor'] ?? ''));
        $analisisBancarizacion = $this->evaluarBancarizacionYCci($montoOperacion, $monedaOperacion, $cciProveedor, $ficha['actividades_economicas']);

        // 7. Motor Heurístico de Scoring Forense Anti-Fraude (0 a 100 puntos de riesgo)
        $evaluacionRiesgo = $this->calcularScoreForense(
            $ficha,
            $sscoFirme,
            $sscoVinculados,
            $deudaPadronMasivo,
            $alertasRepresentantes,
            $resultadoCpe,
            $analisisCongruencia,
            $analisisBancarizacion,
            $params
        );

        return [
            'success' => true,
            'ruc' => $ruc,
            'auditado_at' => now()->format('d/m/Y H:i:s'),
            'ficha' => $ficha,
            'padrones_negros' => [
                'es_ssco_firme' => $sscoFirme !== null,
                'ssco_firme' => $sscoFirme?->toArray(),
                'es_ssco_vinculado' => $sscoVinculados->isNotEmpty(),
                'ssco_vinculados' => $sscoVinculados->toArray(),
                'en_padron_coactiva_masiva' => $deudaPadronMasivo !== null,
                'deuda_coactiva_masiva' => $deudaPadronMasivo?->toArray(),
                'alertas_representantes_ssco' => $alertasRepresentantes,
            ],
            'validacion_cpe' => $resultadoCpe,
            'congruencia_giro' => $analisisCongruencia,
            'bancarizacion' => $analisisBancarizacion,
            'scoring' => $evaluacionRiesgo,
        ];
    }

    /**
     * Obtiene la ficha extendida desde el portal de SUNAT mediante el scraper headless o caché de 2 horas.
     *
     * @return array<string, mixed>
     */
    public function obtenerFichaExtendidaRuc(string $ruc, bool $forzarRefresh = false): array
    {
        $cacheKey = "crm.escudo_fiscal.ficha_ruc.v1.{$ruc}";

        if (! $forzarRefresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && ($cached['success'] ?? false) === true) {
                return $cached;
            }
        }

        if (self::$customScraper !== null) {
            $res = (self::$customScraper)($ruc);
            if (($res['success'] ?? false) === true) {
                Cache::put($cacheKey, $res, now()->addHours(2));
            }

            return $res;
        }

        if (app()->environment('testing')) {
            return ['success' => false, 'error' => 'Modo testing sin scraper configurado.'];
        }

        $scriptPath = base_path('scripts/sunat_auditoria_ruc.cjs');
        if (! file_exists($scriptPath)) {
            return ['success' => false, 'error' => 'Script de auditoría RUC no encontrado.'];
        }

        try {
            $process = new Process([NodeBinary::path(), $scriptPath, $ruc], base_path(), null, null, 22);
            $process->run();

            $output = trim($process->getOutput());
            if ($output !== '') {
                $decoded = json_decode($output, true);
                if (is_array($decoded) && ($decoded['success'] ?? false) === true) {
                    Cache::put($cacheKey, $decoded, now()->addHours(2));

                    return $decoded;
                }
            }
        } catch (Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }

        return ['success' => false, 'error' => 'No se pudo obtener respuesta extendida del portal SUNAT.'];
    }

    /**
     * Consolida y estructura los datos de la ficha del contribuyente desde el scraper y/o API de padrón.
     *
     * @param  array<string, mixed>  $fichaExtendida
     * @param  array<string, mixed>  $padronBasico
     * @return array<string, mixed>
     */
    protected function consolidarFichaContribuyente(string $ruc, array $fichaExtendida, array $padronBasico): array
    {
        $raw = (array) ($fichaExtendida['rawFields'] ?? []);
        $sub = (array) ($fichaExtendida['subResults'] ?? []);

        // Extraer Razón Social de "20609720400 - AMICARGO EXPRESS E.I.R.L."
        $rucYNombre = (string) ($raw['Número de RUC:'] ?? '');
        $razonSocial = $padronBasico['razon_social'] ?? null;
        if (str_contains($rucYNombre, '-')) {
            $partes = explode('-', $rucYNombre, 2);
            $razonSocial = trim($partes[1]) ?: $razonSocial;
        }

        $tipoContribuyente = trim((string) ($raw['Tipo Contribuyente:'] ?? RucSunat::tipoPersona($ruc)));
        $nombreComercial = trim((string) ($raw['Nombre Comercial:'] ?? '-'));
        $fechaInscripcion = $this->extraerFecha($raw['Fecha de Inscripción:'] ?? null);
        $fechaInicioActividades = $this->extraerFecha($raw['Fecha de Inicio de Actividades:'] ?? ($raw['Fecha de Inscripción:'] ?? null), true);

        $estadoRaw = trim((string) ($raw['Estado del Contribuyente:'] ?? ($padronBasico['estado_sunat'] ?? 'NO DISPONIBLE')));
        $fechaBaja = null;
        if (preg_match('/Fecha de Baja:\s*(\d{2}\/\d{2}\/\d{4})/i', $estadoRaw, $mBaja)) {
            $fechaBaja = $mBaja[1];
            $estadoRaw = trim(preg_replace('/Fecha de Baja:.*$/i', '', $estadoRaw));
        } elseif (! empty($raw['Fecha de Baja:']) && preg_match('/(\d{2}\/\d{2}\/\d{4})/', (string) $raw['Fecha de Baja:'], $mBaja2)) {
            $fechaBaja = $mBaja2[1];
        }

        $condicionRaw = trim((string) ($raw['Condición del Contribuyente:'] ?? ($padronBasico['condicion_sunat'] ?? 'NO DISPONIBLE')));
        if (str_starts_with(strtoupper($condicionRaw), 'NO HABIDO')) {
            $condicionLimpia = 'NO HABIDO';
        } elseif (str_starts_with(strtoupper($condicionRaw), 'NO HALLADO')) {
            $condicionLimpia = 'NO HALLADO';
        } elseif (str_starts_with(strtoupper($condicionRaw), 'HABIDO')) {
            $condicionLimpia = 'HABIDO';
        } else {
            $condicionLimpia = strtok($condicionRaw, ' ') ?: $condicionRaw;
        }

        $domicilioFiscal = trim((string) ($raw['Domicilio Fiscal:'] ?? ($padronBasico['direccion_sunat'] ?? '-')));
        $sistemaEmision = trim((string) ($raw['Sistema Emisión de Comprobante:'] ?? '-'));
        $sistemaContabilidad = trim((string) ($raw['Sistema Contabilidad:'] ?? '-'));
        $emisorElectronicoDesde = trim((string) ($raw['Emisor electrónico desde:'] ?? '-'));
        $comprobantesElectronicos = trim((string) ($raw['Comprobantes Electrónicos:'] ?? '-'));
        $afiliadoPleDesde = trim((string) ($raw['Afiliado al PLE desde:'] ?? '-'));

        $actividadesStr = (string) ($raw['Actividad(es) Económica(s):'] ?? '');
        $actividades = array_values(array_filter(array_map('trim', explode(';', $actividadesStr)), fn ($v) => $v !== '' && $v !== '-'));

        $padronesStr = (string) ($raw['Padrones:'] ?? '');
        $padrones = array_values(array_filter(array_map('trim', explode(';', $padronesStr)), fn ($v) => $v !== '' && $v !== '-'));

        // Calcular antigüedad en meses desde inicio de actividades o inscripción (a la fecha actual 2026)
        $antiguedadMeses = null;
        $fechaRef = $fechaInicioActividades ?: $fechaInscripcion;
        if ($fechaRef && preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fechaRef, $mF)) {
            try {
                $dtInicio = Carbon::createFromFormat('d/m/Y', $fechaRef)->startOfDay();
                $antiguedadMeses = (int) max(0, $dtInicio->diffInMonths(now()->startOfDay()));
            } catch (Throwable) {
                $antiguedadMeses = null;
            }
        }

        // Parsear Representantes Legales
        $representantes = [];
        $tablasRep = $sub['formRepLeg']['tables'][0] ?? [];
        foreach (array_slice($tablasRep, 1) as $row) {
            if (count($row) >= 4 && ! str_contains(strtoupper($row[0]), 'NO EXISTE')) {
                $representantes[] = [
                    'tipo_doc' => $row[0] ?? '',
                    'num_doc' => $row[1] ?? '',
                    'nombre' => $row[2] ?? '',
                    'cargo' => $row[3] ?? '',
                    'fecha_desde' => $row[4] ?? '',
                ];
            }
        }

        // Parsear Deuda Coactiva en Consulta RUC
        $deudasCoactivas = [];
        $totalDeudaCoactivaRuc = 0.0;
        $tablasDeuda = $sub['formInfoDeudaCoactiva']['tables'][0] ?? [];
        foreach (array_slice($tablasDeuda, 1) as $row) {
            if (count($row) >= 4 && is_numeric(str_replace(',', '', $row[0]))) {
                $monto = (float) str_replace(',', '', $row[0]);
                $totalDeudaCoactivaRuc += $monto;
                $deudasCoactivas[] = [
                    'monto' => $monto,
                    'periodo' => $row[1] ?? '',
                    'fecha_inicio' => $row[2] ?? '',
                    'entidad' => $row[3] ?? '',
                ];
            }
        }

        // Parsear Omisiones Tributarias
        $omisiones = [];
        $tablasOmi = $sub['formInfoOmisionTributaria']['tables'][0] ?? [];
        foreach (array_slice($tablasOmi, 1) as $row) {
            if (count($row) >= 2 && ! str_contains(strtoupper($row[0]), 'NO SE HA REMITIDO')) {
                $omisiones[] = implode(' — ', $row);
            }
        }

        // Parsear Trabajadores PLAME (últimos 12 períodos)
        $trabajadores = [];
        $tablasTrab = $sub['formNumTrabajd']['tables'][0] ?? [];
        foreach (array_slice($tablasTrab, 1) as $row) {
            if (count($row) >= 4 && preg_match('/^\d{4}-\d{2}$/', $row[0])) {
                $nTrab = is_numeric($row[1]) ? (int) $row[1] : 0;
                $nPrest = is_numeric($row[3]) ? (int) $row[3] : 0;
                $trabajadores[] = [
                    'periodo' => $row[0],
                    'trabajadores' => $nTrab,
                    'pensionistas' => is_numeric($row[2]) ? (int) $row[2] : 0,
                    'prestadores' => $nPrest,
                ];
            }
        }

        $ultimoPeriodoPlanilla = ! empty($trabajadores) ? end($trabajadores) : null;
        $maxTrabajadores12m = ! empty($trabajadores) ? max(array_column($trabajadores, 'trabajadores')) : 0;

        // Parsear Establecimientos Anexos
        $anexos = [];
        foreach (($sub['formLocAnex']['tables'] ?? []) as $tAnexo) {
            if (! empty($tAnexo[0]) && count($tAnexo[0]) >= 3 && str_contains(strtoupper($tAnexo[0][0]), 'CÓDIGO')) {
                foreach (array_slice($tAnexo, 1) as $rAnexo) {
                    if (count($rAnexo) >= 3) {
                        $anexos[] = [
                            'codigo' => $rAnexo[0] ?? '',
                            'tipo' => $rAnexo[1] ?? '',
                            'direccion' => $rAnexo[2] ?? '',
                            'actividad' => $rAnexo[3] ?? '-',
                        ];
                    }
                }
            }
        }

        // Parsear Historial de Condición (Si tuvo NO HALLADO / NO HABIDO previo)
        $historialCondiciones = [];
        $tablasHist = $sub['forminfoHist']['tables'][1] ?? [];
        foreach (array_slice($tablasHist, 1) as $rHist) {
            if (count($rHist) >= 3 && $rHist[0] !== 'No hay Información' && $rHist[0] !== '-') {
                $historialCondiciones[] = [
                    'condicion' => $rHist[0],
                    'desde' => $rHist[1] ?? '-',
                    'hasta' => $rHist[2] ?? '-',
                ];
            }
        }

        return [
            'fuente_completa' => ($fichaExtendida['success'] ?? false) === true,
            'latencia_ms' => $fichaExtendida['latencia_ms'] ?? null,
            'ruc' => $ruc,
            'razon_social' => $razonSocial ?: 'CONTRIBUYENTE NO IDENTIFICADO',
            'tipo_contribuyente' => $tipoContribuyente,
            'es_eirl_o_sacs' => str_contains(strtoupper($tipoContribuyente), 'INDIVIDUAL')
                || str_contains(strtoupper((string) $razonSocial), 'E.I.R.L')
                || str_contains(strtoupper((string) $razonSocial), 'EIRL')
                || str_contains(strtoupper((string) $razonSocial), 'S.A.C.S'),
            'nombre_comercial' => $nombreComercial,
            'fecha_inscripcion' => $fechaInscripcion,
            'fecha_inicio_actividades' => $fechaInicioActividades,
            'antiguedad_meses' => $antiguedadMeses,
            'estado' => strtoupper($estadoRaw),
            'es_activo' => strtoupper($estadoRaw) === 'ACTIVO',
            'fecha_baja' => $fechaBaja,
            'condicion' => strtoupper($condicionLimpia),
            'condicion_detalle' => $condicionRaw,
            'es_habido' => strtoupper($condicionLimpia) === 'HABIDO',
            'domicilio_fiscal' => $domicilioFiscal,
            'sistema_emision' => $sistemaEmision,
            'sistema_contabilidad' => $sistemaContabilidad,
            'emisor_electronico_desde' => $emisorElectronicoDesde,
            'comprobantes_electronicos' => $comprobantesElectronicos,
            'afiliado_ple_desde' => $afiliadoPleDesde,
            'actividades_economicas' => $actividades,
            'padrones' => $padrones,
            'representantes_legales' => $representantes,
            'deudas_coactivas' => $deudasCoactivas,
            'total_deuda_coactiva_ruc' => round($totalDeudaCoactivaRuc, 2),
            'omisiones_tributarias' => $omisiones,
            'trabajadores_planilla' => $trabajadores,
            'tiene_planilla_declarada' => ! empty($trabajadores),
            'ultimo_periodo_planilla' => $ultimoPeriodoPlanilla,
            'max_trabajadores_12m' => $maxTrabajadores12m,
            'establecimientos_anexos' => $anexos,
            'historial_condiciones' => $historialCondiciones,
        ];
    }

    /**
     * Verifica si alguno de los representantes legales del RUC auditado aparece como representante
     * o titular de OTRA empresa declarada como SSCO Firme o Vinculada en el Perú.
     *
     * @param  list<array<string, string>>  $representantes
     * @return list<array<string, mixed>>
     */
    protected function cruzarRepresentantesContraSsco(string $rucActual, array $representantes): array
    {
        if (empty($representantes)) {
            return [];
        }

        $documentos = [];
        $nombres = [];

        foreach ($representantes as $rep) {
            $doc = preg_replace('/\D/', '', (string) ($rep['num_doc'] ?? ''));
            if (strlen($doc) >= 8) {
                $documentos[] = $doc;
                // También buscar como RUC 10 si es un DNI de 8 dígitos
                $nombres[$doc] = $rep['nombre'] ?? '';
            }
        }

        if (empty($documentos)) {
            return [];
        }

        $coincidencias = PadronRiesgoSunat::query()
            ->whereIn('tipo_padron', [PadronRiesgoSunat::TIPO_SSCO_FIRME, PadronRiesgoSunat::TIPO_SSCO_VINCULADO])
            ->where('ruc', '!=', $rucActual)
            ->where(function ($q) use ($documentos) {
                foreach ($documentos as $doc) {
                    $q->orWhere('doc_representante', 'like', "%{$doc}%")
                        ->orWhere('ruc', 'like', "10{$doc}%");
                }
            })
            ->limit(15)
            ->get();

        return $coincidencias->map(fn (PadronRiesgoSunat $item) => [
            'ruc_vinculado' => $item->ruc,
            'razon_social_vinculada' => $item->razon_social,
            'tipo_padron' => $item->tipo_padron,
            'resolucion' => $item->resolucion_atribucion,
            'doc_representante' => $item->doc_representante,
            'nombre_representante' => $item->nombre_representante,
            'fecha_publicacion' => $item->fecha_publicacion,
        ])->all();
    }

    /**
     * Evalúa si el concepto o descripción de la compra guarda relación lógica con los CIIU del proveedor.
     *
     * @param  list<string>  $actividadesCiiu
     * @return array<string, mixed>
     */
    public function evaluarCongruenciaGiro(array $actividadesCiiu, string $conceptoOperacion): array
    {
        if ($conceptoOperacion === '' || empty($actividadesCiiu)) {
            return [
                'evaluado' => false,
                'congruente' => null,
                'nivel' => 'no_evaluado',
                'mensaje' => empty($actividadesCiiu)
                    ? 'No se dispone del detalle de actividades CIIU para contrastar el giro.'
                    : 'Ingrese el concepto o descripción de la factura para evaluar congruencia con el CIIU del proveedor.',
            ];
        }

        $textoCiiu = Str::lower(Str::ascii(implode(' ', $actividadesCiiu)));
        $textoCompra = Str::lower(Str::ascii($conceptoOperacion));

        // Diccionario de familias económicas frecuentes en auditoría tributaria peruana
        $familias = [
            'construccion_ferreteria' => ['construccion', 'ferreteria', 'cemento', 'fierro', 'ladrillo', 'arena', 'piedra', 'pintura', 'fontaneria', 'obra', 'edificacion', 'arquitectura', 'ingenieria', 'concreto', 'tuberia', 'acero', 'agregados', 'drywall'],
            'transporte_carga_vehiculos' => ['transporte', 'carga', 'flete', 'mudanza', 'vehiculo', 'automotor', 'repuesto', 'llanta', 'neumatico', 'lubricante', 'combustible', 'grifo', 'mantenimiento', 'taller', 'mecanica', 'autoparte', 'acarreo'],
            'tecnologia_sistemas' => ['informatica', 'computo', 'software', 'sistema', 'redes', 'hosting', 'programacion', 'laptop', 'impresora', 'tecnologia', 'telecomunicacion', 'licencia', 'soporte'],
            'publicidad_marketing' => ['publicidad', 'marketing', 'imprenta', 'impresion', 'diseno', 'evento', 'merchandising', 'agencia', 'fotografia', 'audiovisual', 'banner', 'letrero'],
            'asesoria_consultoria' => ['juridica', 'contabilidad', 'contable', 'auditoria', 'consultoria', 'asesoria', 'legal', 'abogado', 'administracion', 'empresarial', 'gestion'],
            'alimentos_restaurante' => ['alimento', 'bebida', 'restaurante', 'comida', 'catering', 'abarrote', 'supermercado', 'bodega', 'fruta', 'verdura', 'carne', 'panaderia'],
            'textil_confecciones' => ['textil', 'prenda', 'vestir', 'ropa', 'calzado', 'uniforme', 'tela', 'confeccion', 'epp', 'seguridad'],
            'mineria_maquinaria' => ['mineria', 'maquinaria', 'equipo', 'alquiler', 'excavadora', 'retroexcavadora', 'volquete', 'perforacion', 'industrial', 'herramienta', 'generador'],
        ];

        $familiasProveedor = [];
        $familiasCompra = [];

        foreach ($familias as $nombreFamilia => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($textoCiiu, $palabra)) {
                    $familiasProveedor[$nombreFamilia] = true;
                }
                if (str_contains($textoCompra, $palabra)) {
                    $familiasCompra[$nombreFamilia] = true;
                }
            }
        }

        // Coincidencia directa de palabras significativas (> 4 letras)
        $palabrasCompra = array_filter(preg_split('/[^a-z0-9]+/', $textoCompra) ?: [], fn ($w) => strlen($w) >= 4);
        $coincidenciaDirecta = false;
        foreach ($palabrasCompra as $w) {
            if (str_contains($textoCiiu, $w)) {
                $coincidenciaDirecta = true;
                break;
            }
        }

        $interseccion = array_intersect_key($familiasProveedor, $familiasCompra);
        $esComercioNoEspecializado = str_contains($textoCiiu, 'no especializados') || str_contains($textoCiiu, 'otros tipos');

        if ($coincidenciaDirecta || ! empty($interseccion)) {
            return [
                'evaluado' => true,
                'congruente' => true,
                'nivel' => 'congruente',
                'mensaje' => 'CONGRUENTE: El concepto facturado guarda relación directa con la Actividad Económica (CIIU) declarada por el proveedor ante SUNAT.',
            ];
        }

        if ($esComercioNoEspecializado && empty($familiasCompra['asesoria_consultoria']) && empty($familiasCompra['construccion_ferreteria'])) {
            return [
                'evaluado' => true,
                'congruente' => false,
                'nivel' => 'advertencia',
                'mensaje' => 'GIRO GENÉRICO: El proveedor declara comercio no especializado. Verifique guías de remisión y existencia física de la mercadería.',
            ];
        }

        return [
            'evaluado' => true,
            'congruente' => false,
            'nivel' => 'incongruente',
            'mensaje' => 'ALERTA DE GIRO DISCORDANTE: El concepto de la compra ("'.$conceptoOperacion.'") NO coincide con los rubros CIIU del proveedor ('.implode(' / ', $actividadesCiiu).'). Las empresas que venden facturas al 1% suelen facturar bienes o servicios ajenos a su giro real.',
        ];
    }

    /**
     * Evalúa las obligaciones de Bancarización (Ley 28194 / D.Leg. 1529), Detracción SPOT y estructura CCI de 20 dígitos.
     *
     * @param  list<string>  $actividadesCiiu
     * @return array<string, mixed>
     */
    public function evaluarBancarizacionYCci(?float $monto, string $moneda, string $cci, array $actividadesCiiu): array
    {
        $umbralSoles = 2000.00;
        $umbralDolares = 500.00;

        $exigeBancarizacion = false;
        if ($monto !== null && $monto > 0) {
            $exigeBancarizacion = ($moneda === 'USD') ? ($monto >= $umbralDolares) : ($monto >= $umbralSoles);
        }

        $exigeDetraccion = false;
        $textoCiiu = strtoupper(implode(' ', $actividadesCiiu));
        $esServicioOSujetoSpot = str_contains($textoCiiu, 'TRANSPORTE')
            || str_contains($textoCiiu, 'CONSTRUCCIÓN')
            || str_contains($textoCiiu, 'MANTENIMIENTO')
            || str_contains($textoCiiu, 'ALQUILER')
            || str_contains($textoCiiu, 'CONSULTORÍA')
            || str_contains($textoCiiu, 'ASESORÍA')
            || str_contains($textoCiiu, 'PUBLICIDAD');

        if ($monto !== null && $monto > 700 && $moneda === 'PEN') {
            $exigeDetraccion = true;
        } elseif ($monto !== null && $monto > 400 && str_contains($textoCiiu, 'TRANSPORTE')) {
            $exigeDetraccion = true;
        }

        // Validación de Código de Cuenta Interbancario (CCI) Peruano (20 dígitos)
        $cciInfo = null;
        if ($cci !== '') {
            $cciLimpio = preg_replace('/\D/', '', $cci);
            $bancos = [
                '002' => 'Banco de Crédito del Perú (BCP)',
                '003' => 'Interbank',
                '009' => 'Scotiabank Perú',
                '011' => 'BBVA Perú',
                '018' => 'Banco de la Nación',
                '023' => 'Banco de Comercio',
                '035' => 'Banco Pichincha',
                '038' => 'BanBif',
                '043' => 'CrediScotia',
                '049' => 'MiBanco',
                '053' => 'Banco GNB',
                '054' => 'Banco Falabella',
                '055' => 'Banco Ripley',
                '056' => 'Banco Santander',
                '801' => 'Caja Metropolitana de Lima',
                '802' => 'Caja Piura',
                '803' => 'Caja Arequipa',
                '805' => 'Caja Sullana',
                '806' => 'Caja Trujillo',
                '808' => 'Caja Huancayo',
                '809' => 'Caja Cusco',
                '810' => 'Caja Ica',
            ];

            $codBanco = substr($cciLimpio, 0, 3);
            $esLongitudValida = strlen($cciLimpio) === 20;
            $nombreBanco = $bancos[$codBanco] ?? null;
            $esRepetitivoFalso = (bool) preg_match('/^(\d)\1{10,}|0000000000/', substr($cciLimpio, 6));

            $cciValido = $esLongitudValida && $nombreBanco !== null && ! $esRepetitivoFalso;

            $cciInfo = [
                'cci_ingresado' => $cciLimpio,
                'valido' => $cciValido,
                'banco' => $nombreBanco ?? 'Entidad Financiera No Reconocida (Código '.$codBanco.')',
                'plaza' => substr($cciLimpio, 3, 3),
                'mensaje' => $cciValido
                    ? "Estructura CCI válida ({$nombreBanco}). IMPORTANTE: Antes de contabilizar el voucher, simule una transferencia interbancaria por S/ 1.00 en su app bancaria para verificar que el titular real de la cuenta coincida con el RUC del proveedor y no sea un voucher editado."
                    : 'ALERTA DE CUENTA BANCARIA SOSPECHOSA: El CCI ingresado no cumple el estándar interbancario peruano de 20 dígitos o pertenece a un código bancario inexistente. Posible voucher de bancarización adulterado.',
            ];
        }

        return [
            'monto_evaluado' => $monto,
            'moneda' => $moneda,
            'exige_bancarizacion' => $exigeBancarizacion,
            'umbral_aplicado' => $moneda === 'USD' ? 'US$ 500.00' : 'S/ 2,000.00',
            'exige_detraccion_probable' => $exigeDetraccion,
            'es_rubro_spot' => $esServicioOSujetoSpot,
            'cci_validacion' => $cciInfo,
            'recomendacion_auditor' => $exigeBancarizacion
                ? 'Operación afecta a Bancarización obligatoria (Art. 3° y 4° Ley N.º 28194, modif. D.Leg. 1529). Sin medio de pago bancario fehaciente a nombre del emisor se pierde el 100% del Crédito Fiscal (IGV) y Costo/Gasto (IR).'
                : 'Operación menor a S/ 2,000 / US$ 500; aun así, en auditoría preventiva se recomienda pago bancarizado para acreditar fehaciencia.',
        ];
    }

    /**
     * Motor Heurístico Forense: Calcula el puntaje de riesgo (0 a 100), el nivel de peligro
     * y la lista detallada de banderas rojas (Red Flags) de compra de facturas / proveedor fantasma.
     *
     * @param  array<string, mixed>  $ficha
     * @param  Collection<int, PadronRiesgoSunat>  $sscoVinculados
     * @param  list<array<string, mixed>>  $alertasRepresentantes
     * @param  array<string, mixed>|null  $resultadoCpe
     * @param  array<string, mixed>  $analisisCongruencia
     * @param  array<string, mixed>  $analisisBancarizacion
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function calcularScoreForense(
        array $ficha,
        ?PadronRiesgoSunat $sscoFirme,
        $sscoVinculados,
        ?PadronRiesgoSunat $deudaPadronMasivo,
        array $alertasRepresentantes,
        ?array $resultadoCpe,
        array $analisisCongruencia,
        array $analisisBancarizacion,
        array $params
    ): array {
        $score = 0;
        $hallazgos = [];

        // 1. PADRONES NEGROS SSCO (D.Leg. 1532) -> RIESGO CRÍTICO INMEDIATO
        if ($sscoFirme !== null) {
            $score = 100;
            $hallazgos[] = [
                'severidad' => 'critico',
                'puntos' => 100,
                'titulo' => 'SUJETO SIN CAPACIDAD OPERATIVA (SSCO CON RESOLUCIÓN FIRME)',
                'detalle' => "El RUC figura en el Padrón Oficial de SSCO de SUNAT (Resolución {$sscoFirme->resolucion_atribucion}, firme desde {$sscoFirme->fecha_firme_resolucion} y publicada el {$sscoFirme->fecha_publicacion}). Conforme al Art. 8° del D.Leg. 1532, sus comprobantes NO otorgan crédito fiscal de IGV ni sustentan costo o gasto para Impuesto a la Renta.",
                'normativa' => 'Art. 7° y 8° D.Leg. N.º 1532 / DS N.º 319-2023-EF',
            ];
        }

        if ($sscoVinculados->isNotEmpty()) {
            $score += 85;
            $primerVinc = $sscoVinculados->first();
            $hallazgos[] = [
                'severidad' => 'critico',
                'puntos' => 85,
                'titulo' => 'EMPRESA VINCULADA SOCIETARIAMENTE A UN SSCO (ART. 12° D.LEG. 1532)',
                'detalle' => "Esta empresa comparte titulares, socios o representantes legales con el SSCO '{$primerVinc->razon_social_ssco_origen}' (RUC {$primerVinc->ruc_ssco_origen}). Causal: {$primerVinc->supuesto_inclusion}. Típico esquema de reciclaje de empresas tras sanción de SUNAT.",
                'normativa' => 'Art. 12° D.Leg. N.º 1532',
            ];
        }

        if (! empty($alertasRepresentantes)) {
            $score += 75;
            $repAlerta = $alertasRepresentantes[0];
            $hallazgos[] = [
                'severidad' => 'critico',
                'puntos' => 75,
                'titulo' => 'REPRESENTANTE LEGAL VINCULADO A EMPRESAS SSCO ("CLONACIÓN DE EIRL/SAC")',
                'detalle' => "El representante legal ({$repAlerta['nombre_representante']} - Doc. {$repAlerta['doc_representante']}) figura como titular o gerente en la empresa sancionada '{$repAlerta['razon_social_vinculada']}' (RUC {$repAlerta['ruc_vinculado']}). Altísima probabilidad de red comercializadora de facturas falsas.",
                'normativa' => 'Cruce Forense de Representantes Legales CRM',
            ];
        }

        // 2. ESTADO DEL CONTRIBUYENTE Y CONDICIÓN DE DOMICILIO FISCAL
        if (! $ficha['es_activo']) {
            $score += 60;
            $bajaTxt = $ficha['fecha_baja'] ? " (Fecha de Baja: {$ficha['fecha_baja']})" : '';
            $hallazgos[] = [
                'severidad' => 'critico',
                'puntos' => 60,
                'titulo' => "CONTRIBUYENTE NO ACTIVO EN SUNAT ({$ficha['estado']}{$bajaTxt})",
                'detalle' => "El proveedor se encuentra en estado {$ficha['estado']}{$bajaTxt}. Si el comprobante fue emitido con posterioridad a la fecha de baja, carece de validez tributaria.",
                'normativa' => 'Art. 18° y 19° TUO Ley del IGV',
            ];
        }

        if (! $ficha['es_habido']) {
            $score += 55;
            $hallazgos[] = [
                'severidad' => 'critico',
                'puntos' => 55,
                'titulo' => "DOMICILIO FISCAL OBSERVADO: {$ficha['condicion']}",
                'detalle' => "El proveedor tiene condición de {$ficha['condicion']}. Las operaciones con sujetos No Habidos no otorgan derecho a crédito fiscal ni deducción de gasto salvo que regularice su condición al 31 de diciembre del ejercicio.",
                'normativa' => 'Art. 19° Ley del IGV / Art. 37° Ley del Impuesto a la Renta',
            ];
        }

        // 3. ANTIGÜEDAD Y PERFIL SOCIETARIO (EIRL / SACS RECIÉN CREADAS)
        if ($ficha['antiguedad_meses'] !== null) {
            if ($ficha['antiguedad_meses'] <= 6) {
                $pts = $ficha['es_eirl_o_sacs'] ? 30 : 20;
                $score += $pts;
                $hallazgos[] = [
                    'severidad' => 'alto',
                    'puntos' => $pts,
                    'titulo' => "CREACIÓN RECIENTE ({$ficha['antiguedad_meses']} MESES DE ANTIGÜEDAD)",
                    'detalle' => "Inicio de actividades reciente ({$ficha['fecha_inicio_actividades']}). Las redes que comercializan facturas al 1% suelen constituir EIRL o SACS nuevas cada pocos meses para facturar masivamente antes de ser fiscalizadas por SUNAT.",
                    'normativa' => 'Indicador Forense de Empresas de Papel / Cascarón',
                ];
            } elseif ($ficha['antiguedad_meses'] <= 12 && $ficha['es_eirl_o_sacs']) {
                $score += 15;
                $hallazgos[] = [
                    'severidad' => 'medio',
                    'puntos' => 15,
                    'titulo' => "EIRL / SACS CON MENOS DE 1 AÑO DE OPERACIÓN ({$ficha['antiguedad_meses']} MESES)",
                    'detalle' => "Empresa constituida hace {$ficha['antiguedad_meses']} meses. Se recomienda verificar capacidad operativa real (personal, local e inventario).",
                    'normativa' => 'Auditoría Preventiva de Capacidad Operativa',
                ];
            }
        }

        // 4. CAPACIDAD OPERATIVA LABORAL (TRABAJADORES EN PLANILLA PLAME)
        if ($ficha['fuente_completa']) {
            $montoOp = (float) ($params['monto'] ?? 0);
            if ($ficha['max_trabajadores_12m'] === 0) {
                $ptsPlanilla = ($montoOp >= 3500 || str_starts_with($ficha['ruc'], '20')) ? 25 : 15;
                $score += $ptsPlanilla;
                $hallazgos[] = [
                    'severidad' => $ptsPlanilla >= 25 ? 'alto' : 'medio',
                    'puntos' => $ptsPlanilla,
                    'titulo' => 'SIN TRABAJADORES EN PLANILLA (0 TRABAJADORES DECLARADOS EN PLAME)',
                    'detalle' => 'El contribuyente no registra trabajadores ni prestadores de servicios en los últimos 12 períodos declarados ante SUNAT. En una fiscalización por Operaciones No Reales (Art. 44° LIGV), SUNAT cuestiona cómo una persona jurídica ejecutó el servicio o movió mercadería sin personal en planilla.',
                    'normativa' => 'Art. 44° Ley del IGV / D.Leg. 1532 (Infraestructura y Recursos Humanos)',
                ];
            } else {
                $hallazgos[] = [
                    'severidad' => 'positivo',
                    'puntos' => 0,
                    'titulo' => "CAPACIDAD LABORAL VERIFICADA ({$ficha['max_trabajadores_12m']} TRABAJADORES EN PLAME)",
                    'detalle' => "El proveedor declara planilla electrónica activa (último período {$ficha['ultimo_periodo_planilla']['periodo']}: {$ficha['ultimo_periodo_planilla']['trabajadores']} trabajador(es) y {$ficha['ultimo_periodo_planilla']['prestadores']} prestador(es)).",
                    'normativa' => 'Verificación de Recursos Humanos en PLAME',
                ];
            }
        }

        // 5. DEUDA COACTIVA Y OMISIONES TRIBUTARIAS
        if ($deudaPadronMasivo !== null || $ficha['total_deuda_coactiva_ruc'] > 0) {
            $montoTotalDeuda = max((float) ($deudaPadronMasivo?->monto_deuda ?? 0), (float) $ficha['total_deuda_coactiva_ruc']);
            $ptsDeuda = $montoTotalDeuda > 25000 ? 30 : 20;
            $score += $ptsDeuda;
            $hallazgos[] = [
                'severidad' => 'alto',
                'puntos' => $ptsDeuda,
                'titulo' => 'REGISTRA DEUDA EN COBRANZA COACTIVA (S/ '.number_format($montoTotalDeuda, 2).')',
                'detalle' => 'El proveedor mantiene deudas exigibles en cobranza coactiva ante SUNAT. Muchos emisores de facturas compradas acumulan IGV impago hasta ser dados de baja de oficio o declarados SSCO.',
                'normativa' => 'Art. 85° y 115° del Código Tributario',
            ];
        }

        if (! empty($ficha['omisiones_tributarias'])) {
            $score += 20;
            $hallazgos[] = [
                'severidad' => 'alto',
                'puntos' => 20,
                'titulo' => 'OMISIONES TRIBUTARIAS REPORTADAS A CENTRALES DE RIESGO',
                'detalle' => 'El proveedor registra períodos tributarios omisos a la presentación de declaraciones juradas en los últimos 5 años.',
                'normativa' => 'Cumplimiento Tributario SUNAT',
            ];
        }

        // 6. HISTORIAL DE NO HALLADO / NO HABIDO
        if (! empty($ficha['historial_condiciones'])) {
            $tuvoNoHallado = collect($ficha['historial_condiciones'])->contains(fn ($h) => str_contains(strtoupper($h['condicion']), 'NO HA'));
            if ($tuvoNoHallado && $ficha['es_habido']) {
                $score += 12;
                $hallazgos[] = [
                    'severidad' => 'medio',
                    'puntos' => 12,
                    'titulo' => 'ANTECEDENTES HISTÓRICOS DE DOMICILIO NO HALLADO / NO HABIDO',
                    'detalle' => 'Aunque hoy figura Habido, el proveedor registra períodos previos como No Hallado o No Habido en su historial tributario.',
                    'normativa' => 'Historial de Domicilio Fiscal SUNAT',
                ];
            }
        }

        // 7. CONGRUENCIA DE GIRO ECONÓMICO (CIIU vs FACTURA)
        if (($analisisCongruencia['evaluado'] ?? false) === true) {
            if ($analisisCongruencia['nivel'] === 'incongruente') {
                $score += 30;
                $hallazgos[] = [
                    'severidad' => 'alto',
                    'puntos' => 30,
                    'titulo' => 'INCONGRUENCIA ENTRE CONCEPTO DE LA FACTURA Y ACTIVIDAD ECONÓMICA (CIIU)',
                    'detalle' => $analisisCongruencia['mensaje'],
                    'normativa' => 'Causal de Operación No Real / Factura de Favor (Art. 44° LIGV)',
                ];
            } elseif ($analisisCongruencia['nivel'] === 'congruente') {
                $hallazgos[] = [
                    'severidad' => 'positivo',
                    'puntos' => 0,
                    'titulo' => 'GIRO ECONÓMICO (CIIU) CONGRUENTE CON LA OPERACIÓN',
                    'detalle' => $analisisCongruencia['mensaje'],
                    'normativa' => 'Principio de Causalidad y Fehaciencia (Art. 37° LIR)',
                ];
            }
        }

        // 8. VALIDACIÓN DE COMPROBANTE DE PAGO (SI SE PROPORCIONÓ)
        if ($resultadoCpe !== null && ($resultadoCpe['success'] ?? false) === true) {
            $codCp = $resultadoCpe['estado_cp']['codigo'] ?? '';
            if ($codCp === '0' || $codCp === '2' || $codCp === '4') {
                $score += 80;
                $hallazgos[] = [
                    'severidad' => 'critico',
                    'puntos' => 80,
                    'titulo' => "COMPROBANTE DE PAGO {$resultadoCpe['estado_cp']['label']} EN SUNAT",
                    'detalle' => $resultadoCpe['diagnostico'],
                    'normativa' => 'Consulta Integrada de Validez CPE SUNAT',
                ];
            } elseif ($codCp === '1' || $codCp === '3') {
                $hallazgos[] = [
                    'severidad' => 'positivo',
                    'puntos' => 0,
                    'titulo' => 'COMPROBANTE DE PAGO EXISTE Y ESTÁ ACEPTADO EN SUNAT',
                    'detalle' => 'El comprobante electrónico existe en la base de datos de SUNAT. Recuerde que la validez informática del XML no exime de acreditar la fehaciencia material de la operación (guías, bancarización, entregables).',
                    'normativa' => 'RS N.º 188-2010/SUNAT',
                ];
            }
        }

        // 9. VALIDACIÓN DE BANCARIZACIÓN / CCI (SI SE INGRESÓ CCI)
        if (! empty($analisisBancarizacion['cci_validacion'])) {
            $cciVal = $analisisBancarizacion['cci_validacion'];
            if (! $cciVal['valido']) {
                $score += 35;
                $hallazgos[] = [
                    'severidad' => 'critico',
                    'puntos' => 35,
                    'titulo' => 'ESTRUCTURA DE CUENTA BANCARIA (CCI) INVÁLIDA O FALSIFICADA',
                    'detalle' => $cciVal['mensaje'],
                    'normativa' => 'Ley N.º 28194 de Bancarización / Detección de Vouchers Falsos',
                ];
            } else {
                $hallazgos[] = [
                    'severidad' => 'positivo',
                    'puntos' => 0,
                    'titulo' => "CCI BANCARIO VÁLIDO ({$cciVal['banco']})",
                    'detalle' => $cciVal['mensaje'],
                    'normativa' => 'Estándar BCRP CCI 20 Dígitos',
                ];
            }
        }

        $scoreFinal = (int) min(100, max(0, $score));

        if ($scoreFinal >= 70) {
            $nivel = 'CRITICO';
            $etiqueta = 'RIESGO CRÍTICO — ALTA PROBABILIDAD DE FACTURA COMPRADA / SSCO';
            $color = 'danger';
            $dictamen = 'RECHAZAR CONTABILIZACIÓN DEL CRÉDITO FISCAL Y GASTO. El proveedor presenta indicadores críticos de Sujeto Sin Capacidad Operativa (SSCO), empresa cascarón o incumplimiento grave. Contabilizar esta factura expone al cliente a reparo del 100% del IGV e Impuesto a la Renta más multa del 50% del tributo omitido (Art. 178° num. 1 CT).';
        } elseif ($scoreFinal >= 35) {
            $nivel = 'OBSERVADO';
            $etiqueta = 'RIESGO MEDIO / ALTO — PROVEEDOR OBSERVADO (EXIGIR FEHACIENCIA)';
            $color = 'warning';
            $dictamen = 'OBSERVADO PARA AUDITORÍA PREVENTIVA. Antes de declarar el comprobante en el RCE (SIRE), exija al cliente el expediente completo de fehaciencia: (1) Voucher bancario original y verificación de titularidad de cuenta, (2) Constancia de depósito de Detracción SPOT o Retención, (3) Guía de Remisión Remitente y Transportista con placa y brevete, y (4) Cotización, orden de compra, conformidad o kardex de ingreso a almacén.';
        } else {
            $nivel = 'CONFIABLE';
            $etiqueta = 'RIESGO BAJO — PROVEEDOR OPERATIVO Y SIN ALERTAS SSCO';
            $color = 'success';
            $dictamen = 'PROVEEDOR APTO EN PADRONES SUNAT. No figura en las listas negras de Sujetos Sin Capacidad Operativa (SSCO) ni registra alertas críticas. Conserve los medios de pago bancarizados (operaciones >= S/ 2,000 o US$ 500) y el sustento documentario habitual.';
        }

        return [
            'score' => $scoreFinal,
            'nivel' => $nivel,
            'etiqueta' => $etiqueta,
            'color' => $color,
            'dictamen' => $dictamen,
            'hallazgos' => $hallazgos,
            'checklist_fehaciencia' => $this->generarChecklistFehaciencia($analisisBancarizacion, $ficha),
        ];
    }

    /**
     * Genera el checklist interactivo de fehaciencia tributaria para que el contador marque o audite.
     *
     * @param  array<string, mixed>  $bancarizacion
     * @param  array<string, mixed>  $ficha
     * @return list<array<string, mixed>>
     */
    protected function generarChecklistFehaciencia(array $bancarizacion, array $ficha): array
    {
        return [
            [
                'codigo' => 'bancarizacion',
                'titulo' => 'Bancarización Fehaciente y Simulación de Cuenta Destino (Ley N.º 28194)',
                'descripcion' => 'Verificar que la transferencia, depósito en cuenta o cheque "No Negociable" haya salido de la cuenta de la empresa hacia una cuenta cuyo titular real sea el RUC emisor (simular transferencia en banca móvil para descartar vouchers editados).',
                'obligatorio' => $bancarizacion['exige_bancarizacion'] ?? true,
            ],
            [
                'codigo' => 'detraccion',
                'titulo' => 'Constancia de Depósito de Detracción SPOT (Banco de la Nación)',
                'descripcion' => 'Si la operación supera S/ 700.00 (o S/ 400.00 en transporte de carga), validar el número de constancia SPOT y que el depósito se haya efectuado dentro del 5.º día hábil del mes siguiente para ejercer el crédito fiscal.',
                'obligatorio' => $bancarizacion['exige_detraccion_probable'] ?? false,
            ],
            [
                'codigo' => 'guias_remision',
                'titulo' => 'Guías de Remisión Electrónicas (Remitente y Transportista) + Kardex',
                'descripcion' => 'En compra de bienes o materiales: exigir Guía de Remisión Electrónica (GRE) con punto de partida congruente con los establecimientos anexos del proveedor ('.count($ficha['establecimientos_anexos'] ?? []).' anexos declarados), placa del vehículo, licencia del conductor e ingreso a almacén.',
                'obligatorio' => true,
            ],
            [
                'codigo' => 'sustento_comercial',
                'titulo' => 'Trazabilidad Comercial (Proforma, Orden de Compra / Servicio y Conformidad)',
                'descripcion' => 'Correos electrónicos, cotizaciones previas, contrato, informe técnico de entregables o fotografías del servicio/obra que demuestren la realidad material de la operación ante una eventual Esquela o Fiscalización de SUNAT.',
                'obligatorio' => true,
            ],
        ];
    }

    private function extraerFecha(?string $texto, bool $segundaFecha = false): ?string
    {
        if ($texto === null || trim($texto) === '') {
            return null;
        }

        if (preg_match_all('/(\d{2}\/\d{2}\/\d{4})/', $texto, $matches)) {
            if ($segundaFecha && isset($matches[1][1])) {
                return $matches[1][1];
            }

            return $matches[1][0] ?? null;
        }

        return null;
    }
}
