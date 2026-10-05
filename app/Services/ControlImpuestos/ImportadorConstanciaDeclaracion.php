<?php

namespace App\Services\ControlImpuestos;

use App\Models\Cliente;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Http\UploadedFile;

/**
 * Lee la constancia y el detalle, cruza los montos y no conserva el PDF.
 */
final class ImportadorConstanciaDeclaracion
{
    public function __construct(
        private ExtractorTextoPdf $extractor,
        private LectorConstanciaSunat $lector,
        private ConciliadorDeclaracionSunat $conciliador,
        private CalculadorLiquidacionService $calculador,
        private RegistrarPagoDetraccion $pagos,
    ) {}

    /**
     * @param  list<UploadedFile>  $archivos
     * @return array<string, mixed>
     */
    public function importar(Cliente $cliente, string $periodo, string $formulario, array $archivos): array
    {
        $leidas = [];
        $ilegibles = [];

        foreach ($archivos as $archivo) {
            $bytes = file_get_contents($archivo->getRealPath() ?: '');
            $texto = is_string($bytes) ? $this->extractor->extraer($bytes) : '';
            if (trim($texto) === '') {
                $ilegibles[] = $archivo->getClientOriginalName();

                continue;
            }

            $leidas[] = $this->lector->leer($texto, app(CatalogoSunat::class)->codigosActivos($cliente->estudio_id));
        }

        $fusion = $this->lector->fusionar($leidas);
        $fusion->archivosIlegibles = $ilegibles;
        $saldos = $this->calculador->obtenerSaldosAnteriores($cliente, $periodo);
        $resultado = $this->conciliador->conciliar($fusion, $cliente, $periodo, $formulario, $saldos);
        $respuesta = $resultado->toArray();

        if (! $resultado->bloqueado && $fusion->pagos !== []) {
            $registro = $this->pagos->registrar($cliente, $periodo, $fusion->pagos);
            $respuesta['alertas'] = [...$respuesta['alertas'], ...$registro['alertas']];
            $respuesta['pagos_registrados'] = $registro['cantidad'];
        }

        return $respuesta;
    }
}
