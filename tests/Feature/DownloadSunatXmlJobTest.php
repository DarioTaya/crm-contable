<?php

namespace Tests\Feature;

use App\Jobs\DownloadSunatXmlJob;
use App\Models\Cliente;
use App\Models\Comprobante;
use App\Services\SunatSoapService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DownloadSunatXmlJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_dispatches_with_delay(): void
    {
        Queue::fake();

        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST SAC',
            'ruc' => '20111111111',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '1',
            'ruc_tercero' => '20222222222',
            'razon_social_tercero' => 'PROV',
            'total' => 100,
            'estado_descarga' => 'pendiente',
        ]);

        DownloadSunatXmlJob::dispatch($comprobante)->delay(now()->addSeconds(4));

        Queue::assertPushed(DownloadSunatXmlJob::class, function ($job) use ($comprobante) {
            return $job->comprobante->id === $comprobante->id && $job->delay !== null;
        });
    }

    public function test_job_executes_soap_service_successfully(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST 2 SAC',
            'ruc' => '20222222222',
            'usuario_sol' => 'MODDATOS',
            'clave_sol' => 'moddatos123',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '2',
            'ruc_tercero' => '20333333333',
            'razon_social_tercero' => 'PROV 2',
            'total' => 200,
            'estado_descarga' => 'pendiente',
        ]);

        $mockSoap = Mockery::mock(SunatSoapService::class);
        $mockSoap->shouldReceive('consultarCpe')
            ->once()
            ->with(Mockery::on(fn ($arg) => $arg->id === $comprobante->id))
            ->andReturn([
                'success' => true,
                'status_code' => '0001',
                'message' => 'Comprobante verificado con éxito.',
                'cdr_descargado' => true,
            ]);

        $this->app->instance(SunatSoapService::class, $mockSoap);

        $job = new DownloadSunatXmlJob($comprobante);
        $job->handle($mockSoap);

        $this->assertTrue(true);
    }

    public function test_job_handles_failure_when_max_attempts_exceeded(): void
    {
        $cliente = Cliente::create([
            'razon_social' => 'CLIENTE TEST 3 SAC',
            'ruc' => '20333333333',
            'regimen_tributario' => 'MYPE',
            'estado' => true,
        ]);

        $comprobante = Comprobante::create([
            'cliente_id' => $cliente->id,
            'periodo' => '2026-08',
            'tipo_registro' => 'RCE',
            'tipo_comprobante' => '01',
            'serie' => 'F001',
            'correlativo' => '3',
            'ruc_tercero' => '20444444444',
            'razon_social_tercero' => 'PROV 3',
            'total' => 300,
            'estado_descarga' => 'pendiente',
        ]);

        $job = new DownloadSunatXmlJob($comprobante);
        $job->failed(new Exception('Error de conexión con SUNAT'));

        $comprobante->refresh();
        $this->assertEquals('error', $comprobante->estado_descarga);
        $this->assertStringContainsString('Error de conexión con SUNAT', $comprobante->mensaje_soap);
    }
}
