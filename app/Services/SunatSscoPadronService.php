<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\PadronRiesgoSunat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SunatSscoPadronService
{
    public const URL_HTML_SSCO_FIRME = 'https://www.sunat.gob.pe/padronesnotificaciones/sujeSinCapacidadOperativa.html';

    public const URL_XLSX_SSCO_FIRME = 'https://www.sunat.gob.pe/padronesnotificaciones/ssco/sujesincapacidadOperativa.xlsx';

    public const URL_HTML_SSCO_VINCULADO = 'https://www.sunat.gob.pe/padronesnotificaciones/relacion-ssco.html';

    public const URL_XLSX_SSCO_VINCULADO = 'https://www.sunat.gob.pe/padronesnotificaciones/relacion-ssco/descarga-relacion-ssco.xlsx';

    public const URL_HTML_DEUDA_COACTIVA = 'https://www.sunat.gob.pe/padronesnotificaciones/listaContrib-mayorDeudaExigible-CC.html';

    public const URL_XLSX_DEUDA_COACTIVA = 'https://www.sunat.gob.pe/padronesnotificaciones/deudaExigibleControlDeuda/pgdt-deudaPadron.xlsx';

    public const CACHE_METADATA_KEY = 'crm.sunat.padrones_riesgo.meta.v1';

    public function __construct(
        private CpeExcelParserService $excelParser
    ) {}

    /**
     * Sincroniza desde los servidores oficiales de SUNAT los 3 padrones públicos de riesgo:
     * 1. SSCO con Resolución de Atribución Firme (Art. 7 D.Leg. 1532)
     * 2. EIRL y Sociedades cuyos titulares/socios son SSCO (Art. 12 D.Leg. 1532)
     * 3. Principales y medianos deudores en Cobranza Coactiva (> 5 UIT)
     *
     * @param  bool  $incluirDeudaCoactiva  Si es true, sincroniza también el padrón de >9,000 deudores coactivos
     * @return array{
     *     success: bool,
     *     ssco_firme_count: int,
     *     ssco_vinculado_count: int,
     *     deuda_coactiva_count: int,
     *     corte_ssco: string,
     *     corte_vinculado: string,
     *     corte_coactiva: string,
     *     sincronizado_at: string,
     *     errores: list<string>
     * }
     */
    public function sincronizarPadronesOficiales(bool $incluirDeudaCoactiva = true): array
    {
        $errores = [];
        $corteSsco = $this->extraerFechaCorteHtml(self::URL_HTML_SSCO_FIRME) ?? 'Información actualizada al 30 de setiembre de 2026';
        $corteVinculado = $this->extraerFechaCorteHtml(self::URL_HTML_SSCO_VINCULADO) ?? $corteSsco;
        $corteCoactiva = $incluirDeudaCoactiva
            ? ($this->extraerFechaCorteHtml(self::URL_HTML_DEUDA_COACTIVA) ?? 'Información actualizada al 12 de Octubre del 2026')
            : 'No sincronizado';

        $sscoCount = 0;
        $vinculadoCount = 0;
        $coactivaCount = 0;

        // 1. Sincronizar Padrón SSCO Firme
        try {
            $sscoCount = $this->descargarYGuardarSscoFirme($corteSsco);
        } catch (Throwable $e) {
            Log::warning("Error sincronizando SSCO Firme: {$e->getMessage()}");
            $errores[] = 'SSCO Firme: '.$e->getMessage();
        }

        // 2. Sincronizar Padrón SSCO Vinculados (Art. 12 D.Leg. 1532)
        try {
            $vinculadoCount = $this->descargarYGuardarSscoVinculados($corteVinculado);
        } catch (Throwable $e) {
            Log::warning("Error sincronizando SSCO Vinculados: {$e->getMessage()}");
            $errores[] = 'SSCO Vinculados: '.$e->getMessage();
        }

        // 3. Sincronizar Padrón Mayor Deuda Coactiva
        if ($incluirDeudaCoactiva) {
            try {
                $coactivaCount = $this->descargarYGuardarDeudaCoactiva($corteCoactiva);
            } catch (Throwable $e) {
                Log::warning("Error sincronizando Deuda Coactiva: {$e->getMessage()}");
                $errores[] = 'Deuda Coactiva: '.$e->getMessage();
            }
        } else {
            $coactivaCount = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)->count();
        }

        $meta = [
            'success' => empty($errores) || ($sscoCount > 0),
            'ssco_firme_count' => $sscoCount ?: PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)->count(),
            'ssco_vinculado_count' => $vinculadoCount ?: PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_VINCULADO)->count(),
            'deuda_coactiva_count' => $coactivaCount ?: PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)->count(),
            'corte_ssco' => $corteSsco,
            'corte_vinculado' => $corteVinculado,
            'corte_coactiva' => $corteCoactiva,
            'sincronizado_at' => now()->format('d/m/Y H:i:s'),
            'errores' => $errores,
        ];

        Cache::put(self::CACHE_METADATA_KEY, $meta, now()->addDays(30));

        return $meta;
    }

    /**
     * Obtiene las estadísticas y metadatos actuales de los padrones en la base de datos.
     */
    public function obtenerEstadisticas(): array
    {
        $cached = Cache::get(self::CACHE_METADATA_KEY);

        $sscoFirmeCount = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)->count();
        $sscoVinculadoCount = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_VINCULADO)->count();
        $deudaCoactivaCount = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)->count();

        $ultimoRegistro = PadronRiesgoSunat::query()->latest('updated_at')->first(['corte_sunat', 'updated_at']);
        $corteSsco = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)->value('corte_sunat')
            ?? ($cached['corte_ssco'] ?? 'Información actualizada al 30 de setiembre de 2026');
        $corteCoactiva = PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)->value('corte_sunat')
            ?? ($cached['corte_coactiva'] ?? 'Información actualizada al 12 de Octubre del 2026');

        // Cruce automático contra la cartera de clientes del estudio activo
        $clientesRucs = Cliente::query()->paraUsuario()->pluck('ruc')->filter()->values()->all();
        $alertasCartera = [];
        if (! empty($clientesRucs)) {
            $alertasCartera = PadronRiesgoSunat::whereIn('ruc', $clientesRucs)->get()->toArray();
        }

        return [
            'ssco_firme_count' => $sscoFirmeCount,
            'ssco_vinculado_count' => $sscoVinculadoCount,
            'deuda_coactiva_count' => $deudaCoactivaCount,
            'total_registros' => $sscoFirmeCount + $sscoVinculadoCount + $deudaCoactivaCount,
            'corte_ssco' => $corteSsco,
            'corte_coactiva' => $corteCoactiva,
            'sincronizado_at' => $ultimoRegistro?->updated_at?->format('d/m/Y H:i:s') ?? ($cached['sincronizado_at'] ?? null),
            'alertas_cartera' => $alertasCartera,
        ];
    }

    /**
     * Descarga y almacena la lista oficial de Sujetos sin Capacidad Operativa (Resolución Firme).
     */
    public function descargarYGuardarSscoFirme(string $corteSunat): int
    {
        $filas = $this->descargarFilasExcel(self::URL_XLSX_SSCO_FIRME, 'ssco_firme');
        if (count($filas) <= 1) {
            return 0;
        }

        $now = now();
        $registros = [];

        foreach (array_slice($filas, 1) as $fila) {
            $ruc = preg_replace('/\D/', '', (string) ($fila[0] ?? ''));
            if (strlen($ruc) !== 11) {
                continue;
            }

            $registros[] = [
                'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_FIRME,
                'ruc' => $ruc,
                'razon_social' => $this->limpiarTexto($fila[1] ?? null, 350),
                'nombre_comercial' => null,
                'domicilio_fiscal' => $this->limpiarTexto($fila[2] ?? null, 500),
                'resolucion_atribucion' => $this->limpiarTexto($fila[3] ?? null, 150),
                'fecha_emision_resolucion' => $this->normalizarFechaExcel($fila[4] ?? null),
                'fecha_firme_resolucion' => $this->normalizarFechaExcel($fila[5] ?? null),
                'doc_representante' => $this->limpiarDocumento($fila[6] ?? null),
                'nombre_representante' => $this->limpiarTexto($fila[7] ?? null, 250),
                'fecha_publicacion' => $this->normalizarFechaExcel($fila[8] ?? null),
                'fecha_limite_restriccion' => null,
                'ruc_ssco_origen' => null,
                'razon_social_ssco_origen' => null,
                'supuesto_inclusion' => 'Resolución de Atribución de Condición SSCO Firme (Art. 7 D.Leg. 1532)',
                'dependencia_sunat' => null,
                'monto_deuda' => null,
                'cantidad_representantes' => 1,
                'corte_sunat' => $corteSunat,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($registros)) {
            return 0;
        }

        DB::transaction(function () use ($registros) {
            PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_FIRME)->delete();
            foreach (array_chunk($registros, 250) as $lote) {
                PadronRiesgoSunat::insert($lote);
            }
        });

        return count($registros);
    }

    /**
     * Descarga y almacena las EIRL y sociedades vinculadas cuyos socios/titulares son SSCO (Art. 12 D.Leg. 1532).
     */
    public function descargarYGuardarSscoVinculados(string $corteSunat): int
    {
        $filas = $this->descargarFilasExcel(self::URL_XLSX_SSCO_VINCULADO, 'ssco_vinculado');
        if (count($filas) <= 1) {
            return 0;
        }

        $now = now();
        $registros = [];

        foreach (array_slice($filas, 1) as $fila) {
            $ruc = preg_replace('/\D/', '', (string) ($fila[1] ?? ''));
            if (strlen($ruc) !== 11) {
                continue;
            }

            $nombreTitular = $this->limpiarTexto($fila[3] ?? null, 250);
            $docTitular = $this->limpiarDocumento($fila[5] ?? null);
            $nombreRep = $this->limpiarTexto($fila[6] ?? null, 250);
            $docRep = $this->limpiarDocumento($fila[8] ?? null);

            $registros[] = [
                'tipo_padron' => PadronRiesgoSunat::TIPO_SSCO_VINCULADO,
                'ruc' => $ruc,
                'razon_social' => $this->limpiarTexto($fila[2] ?? null, 350),
                'nombre_comercial' => null,
                'domicilio_fiscal' => null,
                'resolucion_atribucion' => 'Art. 12 D.Leg. 1532 (Vinculación Societaria SSCO)',
                'fecha_emision_resolucion' => null,
                'fecha_firme_resolucion' => null,
                'doc_representante' => $docRep ?: $docTitular,
                'nombre_representante' => $nombreRep ?: $nombreTitular,
                'fecha_publicacion' => $this->normalizarFechaExcel($fila[14] ?? null),
                'fecha_limite_restriccion' => $this->normalizarFechaExcel($fila[12] ?? null),
                'ruc_ssco_origen' => $this->limpiarDocumento($fila[11] ?? null),
                'razon_social_ssco_origen' => $this->limpiarTexto($fila[10] ?? null, 350),
                'supuesto_inclusion' => $this->limpiarTexto($fila[9] ?? null, 500),
                'dependencia_sunat' => null,
                'monto_deuda' => null,
                'cantidad_representantes' => 1,
                'corte_sunat' => $corteSunat,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($registros)) {
            return 0;
        }

        DB::transaction(function () use ($registros) {
            PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_SSCO_VINCULADO)->delete();
            foreach (array_chunk($registros, 250) as $lote) {
                PadronRiesgoSunat::insert($lote);
            }
        });

        return count($registros);
    }

    /**
     * Descarga y almacena el padrón de contribuyentes con mayor deuda exigible en cobranza coactiva.
     */
    public function descargarYGuardarDeudaCoactiva(string $corteSunat): int
    {
        $filas = $this->descargarFilasExcel(self::URL_XLSX_DEUDA_COACTIVA, 'deuda_coactiva');
        if (count($filas) <= 1) {
            return 0;
        }

        $now = now();
        $registros = [];

        foreach (array_slice($filas, 1) as $fila) {
            $ruc = preg_replace('/\D/', '', (string) ($fila[1] ?? ''));
            if (strlen($ruc) !== 11) {
                continue;
            }

            $montoBruto = str_replace([',', ' '], '', (string) ($fila[5] ?? '0'));
            $monto = is_numeric($montoBruto) ? round((float) $montoBruto, 2) : 0.0;
            $cantRep = (int) preg_replace('/\D/', '', (string) ($fila[6] ?? '0'));

            $registros[] = [
                'tipo_padron' => PadronRiesgoSunat::TIPO_DEUDA_COACTIVA,
                'ruc' => $ruc,
                'razon_social' => $this->limpiarTexto($fila[2] ?? null, 350),
                'nombre_comercial' => $this->limpiarTexto($fila[3] ?? null, 250),
                'domicilio_fiscal' => null,
                'resolucion_atribucion' => 'Cobranza Coactiva Exigible (> 5 UIT)',
                'fecha_emision_resolucion' => null,
                'fecha_firme_resolucion' => null,
                'doc_representante' => null,
                'nombre_representante' => null,
                'fecha_publicacion' => null,
                'fecha_limite_restriccion' => null,
                'ruc_ssco_origen' => null,
                'razon_social_ssco_origen' => null,
                'supuesto_inclusion' => 'Padrón de Principales y Medianos Deudores en Cobranza Coactiva (Art. 85 Código Tributario)',
                'dependencia_sunat' => $this->limpiarTexto($fila[4] ?? null, 100),
                'monto_deuda' => $monto,
                'cantidad_representantes' => $cantRep,
                'corte_sunat' => $corteSunat,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (empty($registros)) {
            return 0;
        }

        DB::transaction(function () use ($registros) {
            PadronRiesgoSunat::where('tipo_padron', PadronRiesgoSunat::TIPO_DEUDA_COACTIVA)->delete();
            foreach (array_chunk($registros, 350) as $lote) {
                PadronRiesgoSunat::insert($lote);
            }
        });

        return count($registros);
    }

    /**
     * Descarga un archivo Excel remoto y retorna sus filas crudas usando CpeExcelParserService.
     *
     * @return list<list<string>>
     */
    protected function descargarFilasExcel(string $url, string $prefijo): array
    {
        $response = Http::withoutVerifying()
            ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0 Safari/537.36')
            ->timeout(25)
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException("Error HTTP {$response->status()} al descargar {$url}");
        }

        $tmpFile = tempnam(sys_get_temp_dir(), "sunat_{$prefijo}_").'.xlsx';
        file_put_contents($tmpFile, $response->body());

        try {
            return $this->excelParser->extraerFilasXlsx($tmpFile);
        } finally {
            @unlink($tmpFile);
        }
    }

    /**
     * Extrae el texto oficial de corte (ej. "Información actualizada al 30 de setiembre de 2026") de la página de SUNAT.
     */
    public function extraerFechaCorteHtml(string $urlHtml): ?string
    {
        try {
            $res = Http::withoutVerifying()
                ->withUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0.0.0 Safari/537.36')
                ->timeout(10)
                ->get($urlHtml);

            if (! $res->successful()) {
                return null;
            }

            $html = $res->body();
            if (preg_match('/<p[^>]*id=["\']textc["\'][^>]*>(.*?)<\/p>/is', $html, $matches)) {
                $texto = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if ($texto !== '') {
                    return $texto;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * Convierte números de serie de fecha de Excel (ej. 46295 -> 30/09/2026) o cadenas a DD/MM/YYYY.
     */
    public function normalizarFechaExcel(mixed $valor): ?string
    {
        if ($valor === null || trim((string) $valor) === '') {
            return null;
        }

        $str = trim((string) $valor);

        if (is_numeric($str) && (float) $str > 20000 && (float) $str < 70000) {
            $timestamp = ((int) round((float) $str) - 25569) * 86400;

            return gmdate('d/m/Y', $timestamp);
        }

        return (new SunatValidezCpeService)->normalizarFechaSunat($str);
    }

    private function limpiarTexto(mixed $valor, int $maxLength = 250): ?string
    {
        if ($valor === null) {
            return null;
        }

        $limpio = preg_replace('/\s+/u', ' ', trim((string) $valor)) ?? trim((string) $valor);
        if ($limpio === '' || $limpio === '-') {
            return null;
        }

        return mb_substr($limpio, 0, $maxLength);
    }

    private function limpiarDocumento(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $doc = preg_replace('/[^A-Za-z0-9]/', '', trim((string) $valor));

        return ($doc !== '' && $doc !== null) ? substr($doc, 0, 25) : null;
    }
}
