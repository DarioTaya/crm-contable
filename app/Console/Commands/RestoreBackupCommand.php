<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class RestoreBackupCommand extends Command
{
    /**
     * Nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'crm:restore-backup
                            {archivo? : Nombre o ruta del archivo .sql a restaurar}
                            {--force : Omitir confirmación interactiva}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Restaura una copia de seguridad previa de la base de datos desde storage/backups/.';

    /**
     * Ejecuta el comando en consola.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        $this->line('');
        $this->info('========================================================================');
        $this->info('        📦 CRM CONTABLE — RESTAURADOR DE BASE DE DATOS');
        $this->info('========================================================================');
        $this->line('');

        $archivo = $this->argument('archivo');

        if (empty($archivo)) {
            $backups = $backupService->getBackupsList();

            if (empty($backups)) {
                $this->warn('⚠️ No se encontraron archivos de respaldo (.sql) en storage/backups/.');

                return self::FAILURE;
            }

            $this->info('Copias de seguridad disponibles:');
            $rows = [];
            foreach ($backups as $index => $b) {
                $rows[] = [
                    $index + 1,
                    $b['filename'],
                    $b['size_formatted'],
                    $b['created_at'],
                ];
            }

            $this->table(['#', 'Archivo', 'Tamaño', 'Fecha de Creación'], $rows);

            $choice = $this->ask('Ingresa el número (#) del respaldo que deseas restaurar [Por defecto: 1]', '1');
            $selectedIndex = ((int) $choice) - 1;

            if (! isset($backups[$selectedIndex])) {
                $this->error("Selección inválida: {$choice}");

                return self::FAILURE;
            }

            $archivo = $backups[$selectedIndex]['path'];
        }

        $force = (bool) $this->option('force');
        if (! $force) {
            $this->warn("⚠️  Vas a restaurar la base de datos desde: {$archivo}");
            $this->warn('   Esta acción reemplazará la información actual por el contenido del respaldo.');
            $this->line('');

            if (! $this->confirm('¿Deseas proceder con la restauración?', false)) {
                $this->info('Restauración cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        $this->comment('🔄 Restaurando base de datos...');
        $result = $backupService->restoreBackup($archivo);

        if (! $result['success']) {
            $this->error("❌ Error en la restauración: {$result['message']}");

            return self::FAILURE;
        }

        $this->info("✅ {$result['message']}");

        // Limpiar cachés tras restaurar
        $this->comment('⚡ Limpiando cachés de la aplicación...');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        $this->line('');
        $this->info('🎉 ¡Base de datos restaurada y sincronizada exitosamente!');
        $this->line('');

        return self::SUCCESS;
    }
}
