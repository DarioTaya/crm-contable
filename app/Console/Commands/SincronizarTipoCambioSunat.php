<?php

namespace App\Console\Commands;

use App\Services\TipoCambioSunatService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sunat:sincronizar-tipo-cambio')]
#[Description('Descarga el tipo de cambio del día publicado por SUNAT y completa el mes en curso')]
class SincronizarTipoCambioSunat extends Command
{
    /**
     * Ejecuta la consulta al archivo oficial de SUNAT y completa el mes en curso.
     */
    public function handle(TipoCambioSunatService $tipoCambioSunat): int
    {
        $vigente = $tipoCambioSunat->sincronizar();
        $tipoCambioSunat->asegurarMesEnCurso();

        if ($vigente === null) {
            $this->error('SUNAT no publicó el tipo de cambio.');

            return self::FAILURE;
        }

        $this->info('Tipo de cambio '.$vigente['fecha_corta'].': compra '.$vigente['compra_texto'].', venta '.$vigente['venta_texto']);

        return self::SUCCESS;
    }
}
