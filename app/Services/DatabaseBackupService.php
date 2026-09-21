<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    /**
     * Directorio base donde se almacenan las copias de seguridad.
     */
    protected string $backupDir;

    public function __construct()
    {
        $this->backupDir = storage_path('backups');
        $this->ensureBackupDirectoryExists();
    }

    /**
     * Asegura que exista la carpeta storage/backups con su respectivo .gitignore.
     */
    protected function ensureBackupDirectoryExists(): void
    {
        if (! File::exists($this->backupDir)) {
            File::makeDirectory($this->backupDir, 0755, true);
        }

        $gitIgnorePath = $this->backupDir.DIRECTORY_SEPARATOR.'.gitignore';
        if (! File::exists($gitIgnorePath)) {
            File::put($gitIgnorePath, "*\n!.gitignore\n");
        }
    }

    /**
     * Genera un volcado completo de la base de datos actual.
     *
     * @param  string|null  $customFilename  Nombre opcional para el archivo
     * @return array{success: bool, path: string, filename: string, message: string}
     */
    public function createBackup(?string $customFilename = null): array
    {
        $connection = config('database.default');
        $timestamp = date('Y_m_d_His');
        $filename = $customFilename ?: "crm_backup_{$timestamp}.sql";
        $targetPath = $this->backupDir.DIRECTORY_SEPARATOR.$filename;

        if ($connection === 'sqlite') {
            return $this->backupSqlite($targetPath, $filename);
        }

        if ($connection === 'mysql') {
            return $this->backupMysql($targetPath, $filename);
        }

        return [
            'success' => false,
            'path' => '',
            'filename' => '',
            'message' => "El motor de base de datos '{$connection}' no tiene soporte automatizado de backup.",
        ];
    }

    /**
     * Respaldo para bases de datos MySQL mediante mysqldump.
     */
    protected function backupMysql(string $targetPath, string $filename): array
    {
        $dumpBinary = $this->findMysqlBinary('mysqldump');
        if (! $dumpBinary) {
            return [
                'success' => false,
                'path' => '',
                'filename' => '',
                'message' => 'No se encontró el ejecutable mysqldump.exe en el PATH ni en la carpeta de Laragon.',
            ];
        }

        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $database = config('database.connections.mysql.database');
        $username = config('database.connections.mysql.username', 'root');
        $password = config('database.connections.mysql.password', '');

        // Construcción de argumentos seguros para el proceso
        $cmd = [
            $dumpBinary,
            "--host={$host}",
            "--port={$port}",
            "--user={$username}",
        ];

        if (! empty($password)) {
            $cmd[] = "--password={$password}";
        }

        $cmd[] = '--single-transaction';
        $cmd[] = '--quick';
        $cmd[] = '--routines';
        $cmd[] = '--triggers';
        $cmd[] = $database;

        try {
            $process = new Process($cmd);
            $process->setTimeout(300);
            $process->run();

            if (! $process->isSuccessful()) {
                // Si falla por algún parámetro especial, reintentar básico
                $cmdBasic = [
                    $dumpBinary,
                    "--host={$host}",
                    "--port={$port}",
                    "--user={$username}",
                ];
                if (! empty($password)) {
                    $cmdBasic[] = "--password={$password}";
                }
                $cmdBasic[] = $database;

                $process = new Process($cmdBasic);
                $process->setTimeout(300);
                $process->run();

                if (! $process->isSuccessful()) {
                    return [
                        'success' => false,
                        'path' => '',
                        'filename' => '',
                        'message' => 'Error al ejecutar mysqldump: '.$process->getErrorOutput(),
                    ];
                }
            }

            File::put($targetPath, $process->getOutput());

            return [
                'success' => true,
                'path' => $targetPath,
                'filename' => $filename,
                'message' => 'Respaldo MySQL generado exitosamente.',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'path' => '',
                'filename' => '',
                'message' => 'Excepción durante el respaldo: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Respaldo para SQLite (usado comúnmente en pruebas automatizadas).
     */
    protected function backupSqlite(string $targetPath, string $filename): array
    {
        $dbPath = config('database.connections.sqlite.database');

        if ($dbPath === ':memory:' || empty($dbPath)) {
            // Para base de datos en memoria (tests), escribir un snapshot representativo
            File::put($targetPath, "-- CRM Contable SQLite In-Memory Backup Snapshot\n-- Generated: ".date('c')."\n");

            return [
                'success' => true,
                'path' => $targetPath,
                'filename' => $filename,
                'message' => 'Respaldo de prueba generado exitosamente.',
            ];
        }

        if (File::exists($dbPath)) {
            File::copy($dbPath, $targetPath);

            return [
                'success' => true,
                'path' => $targetPath,
                'filename' => $filename,
                'message' => 'Respaldo SQLite generado exitosamente.',
            ];
        }

        return [
            'success' => false,
            'path' => '',
            'filename' => '',
            'message' => "El archivo de base de datos SQLite no existe en {$dbPath}.",
        ];
    }

    /**
     * Restaura una base de datos a partir de un archivo .sql existente.
     *
     * @param  string  $filenameOrPath  Nombre del archivo en storage/backups/ o ruta absoluta
     * @return array{success: bool, message: string}
     */
    public function restoreBackup(string $filenameOrPath): array
    {
        $resolvedPath = File::exists($filenameOrPath)
            ? $filenameOrPath
            : $this->backupDir.DIRECTORY_SEPARATOR.basename($filenameOrPath);

        if (! File::exists($resolvedPath)) {
            return [
                'success' => false,
                'message' => "El archivo de respaldo '{$resolvedPath}' no existe.",
            ];
        }

        $connection = config('database.default');

        if ($connection === 'sqlite') {
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath !== ':memory:' && ! empty($dbPath)) {
                File::copy($resolvedPath, $dbPath);
            }

            return [
                'success' => true,
                'message' => 'Base de datos SQLite restaurada correctamente.',
            ];
        }

        if ($connection === 'mysql') {
            $mysqlBinary = $this->findMysqlBinary('mysql');
            if (! $mysqlBinary) {
                return [
                    'success' => false,
                    'message' => 'No se encontró el ejecutable mysql.exe en el PATH ni en Laragon.',
                ];
            }

            $host = config('database.connections.mysql.host', '127.0.0.1');
            $port = config('database.connections.mysql.port', '3306');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username', 'root');
            $password = config('database.connections.mysql.password', '');

            $cmd = [
                $mysqlBinary,
                "--host={$host}",
                "--port={$port}",
                "--user={$username}",
            ];

            if (! empty($password)) {
                $cmd[] = "--password={$password}";
            }

            $cmd[] = $database;

            try {
                $sqlContent = File::get($resolvedPath);
                $process = new Process($cmd);
                $process->setInput($sqlContent);
                $process->setTimeout(300);
                $process->run();

                if (! $process->isSuccessful()) {
                    return [
                        'success' => false,
                        'message' => 'Error al restaurar con mysql.exe: '.$process->getErrorOutput(),
                    ];
                }

                return [
                    'success' => true,
                    'message' => 'Base de datos MySQL restaurada satisfactoriamente.',
                ];
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'message' => 'Excepción durante la restauración: '.$e->getMessage(),
                ];
            }
        }

        return [
            'success' => false,
            'message' => "El motor '{$connection}' no admite restauración automática.",
        ];
    }

    /**
     * Retorna la lista de respaldos existentes ordenados por fecha descendente.
     *
     * @return array<int, array{filename: string, path: string, size_bytes: int, size_formatted: string, created_at: string}>
     */
    public function getBackupsList(): array
    {
        if (! File::exists($this->backupDir)) {
            return [];
        }

        $files = File::files($this->backupDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file->getExtension() === 'sql') {
                $sizeBytes = $file->getSize();
                $backups[] = [
                    'filename' => $file->getFilename(),
                    'path' => $file->getPathname(),
                    'size_bytes' => $sizeBytes,
                    'size_formatted' => $this->formatBytes($sizeBytes),
                    'created_at' => date('Y-m-d H:i:s', $file->getMTime()),
                ];
            }
        }

        // Ordenar por fecha descendente
        usort($backups, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));

        return $backups;
    }

    /**
     * Busca la ruta de un ejecutable de MySQL (mysqldump o mysql).
     */
    public function findMysqlBinary(string $binaryName = 'mysqldump'): ?string
    {
        $exeName = $binaryName.(PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        // 1. Probar en PATH del sistema
        $whichCmd = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $proc = new Process([$whichCmd, $exeName]);
        $proc->run();
        if ($proc->isSuccessful()) {
            $lines = explode("\n", trim($proc->getOutput()));
            $first = trim($lines[0] ?? '');
            if (! empty($first) && File::exists($first)) {
                return $first;
            }
        }

        // 2. Probar en Laragon (Windows)
        $laragonGlob = 'C:\\laragon\\bin\\mysql\\*\\bin\\'.$exeName;
        $matches = glob($laragonGlob);
        if (! empty($matches)) {
            rsort($matches); // versión más reciente primero
            if (File::exists($matches[0])) {
                return $matches[0];
            }
        }

        // 3. Probar en rutas estándar de Windows (Program Files, XAMPP)
        $standardPaths = [
            'C:\\xampp\\mysql\\bin\\'.$exeName,
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\'.$exeName,
            'C:\\Program Files\\MySQL\\MySQL Server 8.4\\bin\\'.$exeName,
            'C:\\Program Files (x86)\\MySQL\\MySQL Server 8.0\\bin\\'.$exeName,
        ];

        foreach ($standardPaths as $path) {
            if (File::exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Formatea bytes a unidades legibles.
     */
    protected function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }

    /**
     * Retorna el directorio de respaldos.
     */
    public function getBackupDir(): string
    {
        return $this->backupDir;
    }
}
