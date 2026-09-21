<?php

namespace App\Console\Commands;

use App\Models\AjusteSistema;
use App\Models\AlertaBuzon;
use App\Models\Cliente;
use App\Services\SunafilBuzonSyncService;
use App\Services\WhatsAppService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('buzon:sync-sunafil {ruc? : RUC opcional de un cliente específico} {--force : Forzar la ejecución omitiendo el chequeo de frecuencia horaria}')]
#[Description('Sincroniza las notificaciones de la Casilla Electrónica de SUNAFIL evaluando dinámicamente la frecuencia')]
class SyncSunafilBuzon extends Command
{
    /**
     * Ejecuta el comando de consola.
     */
    public function handle(SunafilBuzonSyncService $syncService, WhatsAppService $whatsAppService): int
    {
        $rucParam = $this->argument('ruc');
        $force = (bool) $this->option('force');
        $ajustes = AjusteSistema::getSettings();

        // Si se especificó un RUC puntual, sincronizamos directamente dicho cliente
        if ($rucParam) {
            $cliente = Cliente::where('ruc', $rucParam)->with('credencial')->first();

            if (! $cliente) {
                $this->error("No se encontró ningún cliente con el RUC {$rucParam}.");

                return self::FAILURE;
            }

            $this->info("Iniciando sincronización de Casilla SUNAFIL para {$cliente->razon_social} ({$cliente->ruc})...");
            $resultado = $syncService->syncCliente($cliente);

            if ($resultado['success']) {
                $this->info("✅ {$resultado['message']}");
                $ajustes->update(['ultima_sincronizacion_sunafil_at' => now()]);

                $this->despacharAlertasWhatsApp($resultado['nuevas_alertas_items'] ?? [], $whatsAppService);

                return self::SUCCESS;
            }

            $this->error("❌ Error: {$resultado['message']}");

            return self::FAILURE;
        }

        // Si es una ejecución programada automática (sin --force), comprobar la ventana horaria configurada
        if (! $force && $ajustes->ultima_sincronizacion_sunafil_at !== null) {
            $horasTranscurridas = (int) abs($ajustes->ultima_sincronizacion_sunafil_at->diffInHours(now()));

            if ($horasTranscurridas < $ajustes->frecuencia_cron_horas) {
                $this->info("⏳ Sincronización programada SUNAFIL omitida: La frecuencia configurada es de cada {$ajustes->frecuencia_cron_horas} horas y la última ejecución fue hace {$horasTranscurridas} hora(s) ({$ajustes->ultima_sincronizacion_sunafil_at->format('d/m/Y H:i')}).");

                return self::SUCCESS;
            }
        }

        $this->info('Iniciando sincronización masiva de Casilla SUNAFIL para todos los clientes activos...');
        $resultado = $syncService->syncAll();

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total Clientes con Credenciales', $resultado['total_clientes']],
                ['Clientes Procesados con Éxito', $resultado['clientes_procesados']],
                ['Nuevas Alertas Guardadas', $resultado['nuevas_alertas']],
                ['Total Errores', count($resultado['errores'])],
            ]
        );

        if (! empty($resultado['errores'])) {
            $this->warn('Detalle de incidencias encontradas:');
            foreach ($resultado['errores'] as $error) {
                $this->line("  • {$error}");
            }
        }

        // Registrar la fecha y hora de la sincronización exitosa en los ajustes
        $ajustes->update(['ultima_sincronizacion_sunafil_at' => now()]);

        // Despachar notificaciones vía WhatsApp para las nuevas alertas
        $this->despacharAlertasWhatsApp($resultado['nuevas_alertas_items'] ?? [], $whatsAppService);

        $this->info('Sincronización masiva de SUNAFIL finalizada.');

        return self::SUCCESS;
    }

    /**
     * Despacha las alertas recién registradas vía WhatsApp según la configuración del sistema.
     *
     * @param  array<int, AlertaBuzon>  $nuevasAlertas
     */
    protected function despacharAlertasWhatsApp(array $nuevasAlertas, WhatsAppService $whatsAppService): void
    {
        $despachadas = $whatsAppService->despacharNuevasAlertas($nuevasAlertas);
        if ($despachadas > 0) {
            $this->info("📲 [WhatsApp] {$despachadas} alerta(s) de SUNAFIL despachada(s) exitosamente.");
        }
    }
}
