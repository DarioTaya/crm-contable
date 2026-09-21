<?php

namespace App\Jobs;

use App\Models\Comprobante;
use App\Services\SunatSoapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DownloadSunatXmlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número máximo de intentos.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Tiempo de espera entre reintentos en segundos [5s, 15s, 30s].
     *
     * @var array<int, int>
     */
    public $backoff = [5, 15, 30];

    /**
     * Tiempo límite de ejecución del job en segundos.
     *
     * @var int
     */
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Comprobante $comprobante
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SunatSoapService $soapService): void
    {
        $this->comprobante->refresh();

        // Si ya está disponible con sus archivos en disco, no repetir
        if ($this->comprobante->esta_completamente_disponible) {
            return;
        }

        // Marcar estado en progreso
        $this->comprobante->estado_descarga = 'descargando';
        $this->comprobante->estado_xml = 'descargando';
        $this->comprobante->estado_cdr = 'descargando';
        $this->comprobante->estado_pdf = 'descargando';
        $this->comprobante->saveQuietly();

        try {
            // Pausa de cortesía de 1 segundo para amortiguar la tasa de peticiones a SUNAT
            sleep(1);

            $resultado = $soapService->consultarCpe($this->comprobante);

            Log::info("Descarga SOAP completada para comprobante {$this->comprobante->id}: {$resultado['message']}");
        } catch (Throwable $e) {
            $this->comprobante->intentos_soap++;
            $this->comprobante->mensaje_soap = 'Error SOAP: '.$e->getMessage();

            if ($this->attempts() >= $this->tries) {
                $this->comprobante->estado_descarga = 'error';
                $this->comprobante->estado_xml = $this->comprobante->tiene_xml ? 'disponible' : 'error';
                $this->comprobante->estado_cdr = $this->comprobante->tiene_cdr ? 'disponible' : 'error';
                $this->comprobante->estado_pdf = $this->comprobante->tiene_pdf ? 'disponible' : 'error';
                $this->comprobante->saveQuietly();

                Log::error("Job DownloadSunatXmlJob falló definitivamente para comprobante {$this->comprobante->id}: {$e->getMessage()}");
            } else {
                $this->comprobante->saveQuietly();
                throw $e;
            }
        }
    }

    /**
     * Maneja un fallo definitivo del job.
     */
    public function failed(?Throwable $exception): void
    {
        try {
            $this->comprobante->refresh();
            $this->comprobante->estado_descarga = 'error';
            $this->comprobante->mensaje_soap = 'Fallo en cola: '.($exception?->getMessage() ?? 'Error desconocido');
            $this->comprobante->saveQuietly();
        } catch (Throwable) {
            // Silenciar fallos al guardar estado final
        }
    }
}
