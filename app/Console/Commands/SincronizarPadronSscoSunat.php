<?php

namespace App\Console\Commands;

use App\Services\SunatSscoPadronService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sunat:sincronizar-ssco {--sin-coactiva : Omitir la sincronización del padrón masivo de deuda coactiva}')]
#[Description('Sincroniza automáticamente los padrones oficiales de Sujetos sin Capacidad Operativa (SSCO), Vinculados (Art. 12 D.Leg. 1532) y Mayor Deuda Coactiva desde SUNAT')]
class SincronizarPadronSscoSunat extends Command
{
    public function handle(SunatSscoPadronService $service): int
    {
        $incluirCoactiva = ! $this->option('sin-coactiva');

        $this->info('Sincronizando padrones oficiales de riesgo fiscal desde los servidores de SUNAT...');

        $resultado = $service->sincronizarPadronesOficiales($incluirCoactiva);

        $this->info("  ✓ SSCO con Resolución Firme (Art. 7 D.Leg. 1532): {$resultado['ssco_firme_count']} registros ({$resultado['corte_ssco']})");
        $this->info("  ✓ EIRL / Sociedades Vinculadas SSCO (Art. 12 D.Leg. 1532): {$resultado['ssco_vinculado_count']} registros");

        if ($incluirCoactiva) {
            $this->info("  ✓ Principales Deudores en Cobranza Coactiva (> 5 UIT): {$resultado['deuda_coactiva_count']} registros ({$resultado['corte_coactiva']})");
        }

        if (! empty($resultado['errores'])) {
            foreach ($resultado['errores'] as $err) {
                $this->warn("  ⚠️ Advertencia: {$err}");
            }
        }

        return $resultado['success'] ? self::SUCCESS : self::FAILURE;
    }
}
