<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class FactoryResetCommand extends Command
{
    /**
     * Nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'crm:factory-reset
                            {--force : Omitir la confirmación interactiva}
                            {--no-backup : Omitir la creación del respaldo previo de seguridad}
                            {--clean-install : Ejecutar el reinicio limpio para distribución comercial}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Prepara el CRM Contable para venta o distribución comercial ("0 Kilómetros"), realizando un respaldo y limpiando datos privados.';

    /**
     * Ejecuta el comando en consola.
     */
    public function handle(DatabaseBackupService $backupService): int
    {
        $this->line('');
        $this->info('========================================================================');
        $this->info('        🏢 CRM CONTABLE — PREPARADOR DE ENTREGA COMERCIAL');
        $this->info('                  (SISTEMA "0 KILÓMETROS")');
        $this->info('========================================================================');
        $this->line('');

        $force = (bool) $this->option('force');

        if (! $force) {
            $this->warn('⚠️  ADVERTENCIA: Esta operación destruirá las tablas con tus datos actuales');
            $this->warn('   (clientes reales, comprobantes, credenciales SOL y notas bancarias)');
            $this->warn('   para dejar el CRM listo y limpio para un nuevo comprador.');
            $this->line('');

            if (! $this->confirm('¿Deseas continuar con el reinicio de fábrica "0 Kilómetros"?', false)) {
                $this->info('Operación cancelada por el usuario.');

                return self::SUCCESS;
            }
        }

        // 1. Fase de Respaldo de Seguridad Previo
        if (! $this->option('no-backup')) {
            $this->line('');
            $this->comment('📦 [Paso 1/5] Generando respaldo de seguridad previo de tu base de datos...');
            $backupResult = $backupService->createBackup();

            if ($backupResult['success']) {
                $this->info("   ✅ Respaldo creado en: {$backupResult['path']}");
            } else {
                $this->error("   ⚠️ Advertencia en respaldo: {$backupResult['message']}");
                if (! $force && ! $this->confirm('¿Deseas continuar a pesar de que el respaldo no se generó?', false)) {
                    $this->error('Operación abortada por seguridad.');

                    return self::FAILURE;
                }
            }
        } else {
            $this->line('');
            $this->warn('⏩ [Paso 1/5] Respaldo previo omitido por flag (--no-backup).');
        }

        // 2. Fase de Reconstrucción de la Base de Datos
        $this->line('');
        $this->comment('🔄 [Paso 2/5] Reconstruyendo base de datos limpia (migrate:fresh)...');

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->info('   ✅ Estructura de 30 tablas reconstruida desde cero.');

        // 3. Fase de Sembrado con Datos Maestros de Fábrica
        $this->line('');
        $this->comment('🌱 [Paso 3/5] Sembrando catálogo de fábrica ("0 Kilómetros")...');
        Artisan::call('db:seed', [
            '--class' => 'DatabaseCleanSeeder',
            '--force' => true,
        ]);
        $this->info('   ✅ Tenant maestro, administrador de fábrica y cronograma SUNAT sembrados.');

        // 4. Fase de Purga de Almacenamiento, Sesiones y Logs
        $this->line('');
        $this->comment('🧹 [Paso 4/5] Purgando archivos temporales, logs y sesiones privadas...');
        $this->purgePrivateStorageAndLogs();
        $this->info('   ✅ Archivos residuales, trazas de logs y sesiones de WhatsApp eliminadas.');

        // 5. Fase de Limpieza de Caché de Laravel
        $this->line('');
        $this->comment('⚡ [Paso 5/5] Limpiando cachés de aplicación, rutas y vistas...');
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        $this->info('   ✅ Cachés de Laravel restablecidas al estado virgen.');

        // Resumen final de entrega
        $this->line('');
        $this->info('========================================================================');
        $this->info('       🎉 ¡SISTEMA "0 KILÓMETROS" PREPARADO EXITOSAMENTE!');
        $this->info('========================================================================');
        $this->line('');
        $this->table(
            ['Parámetro', 'Valor de Fábrica para el Comprador'],
            [
                ['Estado del CRM', '0 Kilómetros (Sin clientes ni datos privados)'],
                ['Estudio Contable', 'Mi Estudio Contable (ID: 1)'],
                ['Usuario Administrador', 'admin@estudio.pe'],
                ['Contraseña de Fábrica', 'admin123'],
                ['Cronograma Tributario', '2026 SUNAT Oficial (Pre-cargado)'],
                ['WhatsApp Microservicio', 'Desvinculado (Listo para vincular por el comprador)'],
            ]
        );
        $this->line('');
        $this->info('📌 Próximos pasos para el empaquetado:');
        $this->line('   1. Ya puedes comprimir esta carpeta en un archivo ZIP (ej. CRM_Contable_Venta.zip).');
        $this->line('   2. El comprador podrá encenderlo e ingresar con admin@estudio.pe / admin123.');
        $this->line('   3. Para restaurar tus propios datos de trabajo cuando desees, ejecuta:');
        $this->line('      php artisan crm:restore-backup o usa RESTAURAR_MI_ESTUDIO.bat');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Purga archivos temporales, logs y carpetas de autenticación de WhatsApp.
     */
    protected function purgePrivateStorageAndLogs(): void
    {
        // 1. Purgar logs en storage/logs
        $logFiles = File::glob(storage_path('logs/*.log'));
        foreach ($logFiles as $logFile) {
            File::delete($logFile);
        }

        // 2. Purgar sesiones en storage/framework/sessions
        $sessionFiles = File::glob(storage_path('framework/sessions/*'));
        foreach ($sessionFiles as $sessionFile) {
            if (basename($sessionFile) !== '.gitignore') {
                File::delete($sessionFile);
            }
        }

        // 3. Purgar caché de vistas compiladas
        $viewFiles = File::glob(storage_path('framework/views/*'));
        foreach ($viewFiles as $viewFile) {
            if (basename($viewFile) !== '.gitignore') {
                File::delete($viewFile);
            }
        }

        // 4. Purgar carpetas de sesión de WhatsApp Web si existen
        $wwebjsPaths = [
            base_path('.wwebjs_auth'),
            base_path('.wwebjs_cache'),
            base_path('scripts/.wwebjs_auth'),
            base_path('scripts/.wwebjs_cache'),
        ];

        foreach ($wwebjsPaths as $wPath) {
            if (File::isDirectory($wPath)) {
                File::deleteDirectory($wPath);
            }
        }

        // 5. Purgar descargas temporales en storage/app
        $tempXmls = File::glob(storage_path('app/*.xml'));
        $tempZips = File::glob(storage_path('app/*.zip'));
        $tempPdfs = File::glob(storage_path('app/*.pdf'));
        foreach (array_merge($tempXmls, $tempZips, $tempPdfs) as $tempFile) {
            File::delete($tempFile);
        }
    }
}
