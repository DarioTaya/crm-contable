<?php

namespace App\Services\ControlImpuestos;

use App\Models\Cliente;
use App\Models\ConstanciaDeclaracion;
use App\Models\Operacion;
use App\Models\User;
use App\Services\Catalogos\CatalogoSunat;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Lee una o más constancias, las cruza con el periodo y archiva el PDF en el expediente del cliente.
 */
final class ImportadorConstanciaDeclaracion
{
    public function __construct(
        private ExtractorTextoPdf $extractor,
        private LectorConstanciaSunat $lector,
        private ConciliadorDeclaracionSunat $conciliador,
        private CalculadorLiquidacionService $calculador,
    ) {}

    /**
     * @param  list<UploadedFile>  $archivos
     * @return array<string, mixed>
     */
    public function importar(Cliente $cliente, string $periodo, string $formulario, array $archivos, ?User $usuario): array
    {
        $leidas = [];
        $ilegibles = [];
        $paquetes = [];

        foreach ($archivos as $archivo) {
            $bytes = file_get_contents($archivo->getRealPath() ?: '');
            $texto = is_string($bytes) ? $this->extractor->extraer($bytes) : '';
            if (trim($texto) === '') {
                $ilegibles[] = $archivo->getClientOriginalName();

                continue;
            }

            $leida = $this->lector->leer($texto, app(CatalogoSunat::class)->codigosActivos($cliente->estudio_id));
            $leidas[] = $leida;
            $paquetes[] = ['archivo' => $archivo, 'bytes' => $bytes, 'leida' => $leida];
        }

        $fusion = $this->lector->fusionar($leidas);
        $fusion->archivosIlegibles = $ilegibles;
        $saldos = $this->calculador->obtenerSaldosAnteriores($cliente, $periodo);
        $resultado = $this->conciliador->conciliar($fusion, $cliente, $periodo, $formulario, $saldos);

        if (! $resultado->bloqueado) {
            $resultado->constanciaIds = $this->archivar($cliente, $periodo, $formulario, $fusion, $resultado, $paquetes, $usuario);
        }

        return $resultado->toArray();
    }

    /**
     * @param  list<array{archivo: UploadedFile, bytes: string, leida: DeclaracionLeida}>  $paquetes
     * @return list<int>
     */
    private function archivar(
        Cliente $cliente,
        string $periodo,
        string $formulario,
        DeclaracionLeida $fusion,
        ResultadoConciliacion $resultado,
        array $paquetes,
        ?User $usuario,
    ): array {
        $operacion = Operacion::query()->firstOrCreate(
            [
                'cliente_id' => $cliente->id,
                'periodo' => $periodo,
            ],
            [
                'estudio_id' => $cliente->estudio_id,
            ],
        );

        $ids = [];
        foreach ($paquetes as $paquete) {
            if ($paquete['leida']->esBoleta && $paquete['leida']->formulario === null) {
                continue;
            }

            $bytes = $paquete['bytes'];
            $hash = hash('sha256', $bytes);
            $existente = ConstanciaDeclaracion::query()
                ->where('cliente_id', $cliente->id)
                ->where('formulario', $formulario)
                ->where('periodo', $periodo)
                ->where('archivo_hash', $hash)
                ->first();

            if ($existente) {
                $ids[] = $existente->id;
                $resultado->alertas[] = [
                    'nivel' => 'info',
                    'codigo' => 'archivo_repetido',
                    'mensaje' => 'Este mismo archivo ya estaba guardado para este periodo. Lo volví a leer.',
                ];

                continue;
            }

            $extension = strtolower($paquete['archivo']->getClientOriginalExtension() ?: 'pdf');
            if (! in_array($extension, ['pdf', 'txt', 'csv'], true)) {
                $extension = 'pdf';
            }

            $ruta = sprintf(
                'constancias/%d/%d/%s/%s/%s.%s',
                $cliente->estudio_id,
                $cliente->id,
                $periodo,
                $formulario,
                $hash,
                $extension,
            );
            Storage::disk('local')->put($ruta, $bytes);

            $constancia = ConstanciaDeclaracion::query()->create([
                'estudio_id' => $cliente->estudio_id,
                'cliente_id' => $cliente->id,
                'operacion_id' => $operacion->id,
                'user_id' => $usuario?->id,
                'formulario' => $formulario,
                'periodo' => $periodo,
                'ruc' => $fusion->ruc ?? preg_replace('/\D/', '', (string) $cliente->ruc),
                'razon_social' => $fusion->razonSocial,
                'numero_orden' => $fusion->numeroOrden,
                'fecha_presentacion' => $fusion->fechaPresentacion,
                'tipo_declaracion' => $fusion->tipo,
                'archivo_path' => $ruta,
                'archivo_nombre' => mb_substr($paquete['archivo']->getClientOriginalName(), 0, 255),
                'archivo_hash' => $hash,
                'casillas' => $fusion->casillas,
                'tributos' => $fusion->tributos,
                'alertas' => $resultado->alertas,
                'tiene_detalle' => $fusion->tieneDetalle,
            ]);
            $ids[] = $constancia->id;
        }

        return $ids;
    }
}
