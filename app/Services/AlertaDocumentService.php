<?php

namespace App\Services;

use App\Models\AlertaBuzon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AlertaDocumentService
{
    /**
     * Asegura la existencia física del archivo PDF auténtico de la notificación en disco
     * (sea el documento administrativo oficial o la constancia de notificación) y retorna su ruta absoluta.
     * No genera cédulas ficticias.
     *
     * @param  string  $tipo  'documento'|'constancia'
     */
    public function asegurarDocumento(AlertaBuzon $alerta, string $tipo = 'documento'): ?string
    {
        $alerta->loadMissing('cliente.estudio');

        $esConstancia = ($tipo === 'constancia');
        $rutaRelativa = $esConstancia ? $alerta->ruta_constancia_pdf : $alerta->ruta_pdf;

        // Si se pide documento principal pero ruta_pdf es una constancia, no usarla directamente como documento principal si hay alternativa
        if (! $esConstancia && ! empty($rutaRelativa) && str_starts_with(basename($rutaRelativa), 'constancia_')) {
            $rutaRelativa = null;
        }

        if (! empty($rutaRelativa)) {
            $candidatas = [
                storage_path('app/'.ltrim($rutaRelativa, '/\\')),
                storage_path('app/private/'.ltrim($rutaRelativa, '/\\')),
                storage_path('app/public/'.ltrim($rutaRelativa, '/\\')),
                base_path(ltrim($rutaRelativa, '/\\')),
            ];

            foreach ($candidatas as $ruta) {
                if (file_exists($ruta) && is_file($ruta) && filesize($ruta) > 0) {
                    return $ruta;
                }
            }
        }

        // Búsqueda en el directorio oficial por RUC de cliente y nombre de archivo o notificación
        $rucCliente = $alerta->cliente?->ruc;
        if ($rucCliente) {
            $dirCliente = storage_path("app/alertas/{$alerta->plataforma}/{$rucCliente}");
            if (is_dir($dirCliente)) {
                if ($esConstancia) {
                    // 1. Archivo exacto de constancia por nombre
                    if (! empty($alerta->nombre_archivo_constancia_pdf)) {
                        $rutaPorNombre = $dirCliente.'/'.$alerta->nombre_archivo_constancia_pdf;
                        if (file_exists($rutaPorNombre) && is_file($rutaPorNombre) && filesize($rutaPorNombre) > 0) {
                            return $rutaPorNombre;
                        }
                    }

                    // 2. Archivo que comience con constancia_ y contenga el número de notificación
                    if (! empty($alerta->numero_notificacion)) {
                        $archivos = glob($dirCliente.'/constancia_*'.$alerta->numero_notificacion.'*.pdf');
                        if (! empty($archivos) && file_exists($archivos[0])) {
                            return $archivos[0];
                        }
                    }

                    // 3. Si ruta_pdf original apuntaba a una constancia existente
                    if (! empty($alerta->ruta_pdf) && str_starts_with(basename($alerta->ruta_pdf), 'constancia_')) {
                        $rutaFallback = storage_path('app/'.ltrim($alerta->ruta_pdf, '/\\'));
                        if (file_exists($rutaFallback) && is_file($rutaFallback) && filesize($rutaFallback) > 0) {
                            return $rutaFallback;
                        }
                    }
                } else {
                    // Documento Oficial (Carta, Resolución, OP, Valor):
                    // 1. Archivo exacto si no es constancia
                    if (! empty($alerta->nombre_archivo_pdf) && ! str_starts_with(basename($alerta->nombre_archivo_pdf), 'constancia_')) {
                        $rutaPorNombre = $dirCliente.'/'.$alerta->nombre_archivo_pdf;
                        if (file_exists($rutaPorNombre) && is_file($rutaPorNombre) && filesize($rutaPorNombre) > 0) {
                            return $rutaPorNombre;
                        }
                    }

                    // 2. Archivos en el directorio que coincidan con el número pero NO sean constancia
                    if (! empty($alerta->numero_notificacion)) {
                        $archivos = glob($dirCliente.'/*'.$alerta->numero_notificacion.'*.pdf') ?: [];
                        foreach ($archivos as $arch) {
                            $base = basename($arch);
                            if (! str_starts_with($base, 'constancia_') && file_exists($arch) && filesize($arch) > 0) {
                                return $arch;
                            }
                        }
                    }

                    // 3. Buscar archivos tipo rvalores_* en el directorio
                    if (! empty($alerta->numero_notificacion)) {
                        $archivosRvalores = glob($dirCliente.'/rvalores_*'.$alerta->numero_notificacion.'*.pdf') ?: [];
                        if (! empty($archivosRvalores) && file_exists($archivosRvalores[0])) {
                            return $archivosRvalores[0];
                        }
                    }

                    // 4. Fallback de última instancia: si solo existe un PDF en ruta_pdf (incluso constancia)
                    if (! empty($alerta->ruta_pdf)) {
                        $candidatas = [
                            storage_path('app/'.ltrim($alerta->ruta_pdf, '/\\')),
                            storage_path('app/private/'.ltrim($alerta->ruta_pdf, '/\\')),
                            storage_path('app/public/'.ltrim($alerta->ruta_pdf, '/\\')),
                        ];
                        foreach ($candidatas as $ruta) {
                            if (file_exists($ruta) && is_file($ruta) && filesize($ruta) > 0) {
                                return $ruta;
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /**
     * Entrega una respuesta HTTP para visualizar o descargar el PDF oficial de la alerta.
     *
     * @param  string  $tipo  'documento'|'constancia'
     */
    public function descargar(AlertaBuzon $alerta, bool $inline = false, string $tipo = 'documento'): BinaryFileResponse
    {
        $rutaAbsoluta = $this->asegurarDocumento($alerta, $tipo);

        if (! $rutaAbsoluta || ! file_exists($rutaAbsoluta)) {
            $msg = ($tipo === 'constancia')
                ? 'Esta comunicación oficial no contiene una constancia de notificación adjunta en el Buzón SOL.'
                : 'Esta comunicación oficial no contiene un archivo PDF adjunto emitido por SUNAT en el Buzón SOL.';
            throw new NotFoundHttpException($msg);
        }

        $nombreDescarga = ($tipo === 'constancia')
            ? $alerta->nombreDescargaConstanciaPdf()
            : $alerta->nombreDescargaPdf();

        return response()->file($rutaAbsoluta, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$nombreDescarga.'"',
        ]);
    }
}
