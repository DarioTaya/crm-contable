<?php

namespace App\Console\Commands;

use App\Models\Cliente;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

#[Signature('sunat:actualizar-estados {ruc? : RUC opcional de un cliente específico}')]
#[Description('Actualiza el Estado, Condición de Domicilio y Dirección Fiscal de los clientes desde la API oficial de SUNAT')]
class ActualizarEstadoClientesSunat extends Command
{
    /**
     * Ejecuta el comando de consola.
     */
    public function handle(): int
    {
        $rucParam = $this->argument('ruc');

        $query = $rucParam
            ? Cliente::where('ruc', $rucParam)
            : Cliente::activos();

        $clientes = $query->get();

        if ($clientes->isEmpty()) {
            $this->warn('No se encontraron clientes para sincronizar.');

            return self::SUCCESS;
        }

        $this->info("Iniciando actualización de padrón SUNAT para {$clientes->count()} cliente(s)...");

        $actualizados = 0;
        $fallidos = 0;

        foreach ($clientes as $cliente) {
            $this->line("Procesando {$cliente->ruc} - {$cliente->razon_social}...");

            try {
                $response = Http::timeout(5)->get("https://api.apis.net.pe/v1/ruc?numero={$cliente->ruc}");

                if ($response->successful()) {
                    $datos = $response->json();

                    if (! empty($datos['nombre'])) {
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

                        $this->info("  ✓ Actualizado: {$cliente->estado_contribuyente} / {$cliente->condicion_domicilio}");
                        $actualizados++;
                    } else {
                        $this->warn("  ⚠️ SUNAT no devolvió datos para {$cliente->ruc}.");
                        $fallidos++;
                    }
                } else {
                    $this->error("  ❌ Error HTTP {$response->status()} de SUNAT para {$cliente->ruc}.");
                    $fallidos++;
                }
            } catch (Throwable $e) {
                $this->error("  ❌ Excepción para {$cliente->ruc}: {$e->getMessage()}");
                $fallidos++;
            }

            // Pequeña pausa para respetar el uso razonable del servicio gratuito
            usleep(250000); // 250ms
        }

        $this->newLine();
        $this->info("Resumen: {$actualizados} cliente(s) actualizados correctamente, {$fallidos} fallidos.");

        return self::SUCCESS;
    }
}
