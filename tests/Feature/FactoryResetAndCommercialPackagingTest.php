<?php

namespace Tests\Feature;

use App\Models\AjusteSistema;
use App\Models\Cliente;
use App\Models\CronogramaSunat;
use App\Models\Estudio;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FactoryResetAndCommercialPackagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpiar backups de prueba si existen
        $backupDir = storage_path('backups');
        if (File::exists($backupDir)) {
            $files = File::glob($backupDir.'/*_test_*.sql');
            foreach ($files as $f) {
                File::delete($f);
            }
        }
    }

    public function test_backup_service_creates_and_lists_backups(): void
    {
        $backupService = app(DatabaseBackupService::class);

        $customName = 'crm_backup_test_'.time().'.sql';
        $result = $backupService->createBackup($customName);

        $this->assertTrue($result['success']);
        $this->assertFileExists($result['path']);

        $list = $backupService->getBackupsList();
        $this->assertNotEmpty($list);

        $filenames = array_column($list, 'filename');
        $this->assertContains($customName, $filenames);

        // Limpiar archivo creado
        File::delete($result['path']);
    }

    public function test_factory_reset_wipes_all_private_data_and_seeds_clean_defaults(): void
    {
        // 1. Crear un estudio y datos privados simulados
        $estudio = Estudio::create([
            'nombre' => 'Estudio Privado del Desarrollador',
            'correo' => 'privado@desarrollador.com',
            'telefono' => '999888777',
            'activo' => true,
        ]);

        $admin = User::create([
            'estudio_id' => $estudio->id,
            'name' => 'Usuario Real Privado',
            'email' => 'real@desarrollador.com',
            'password' => Hash::make('secreto123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $cliente = Cliente::create([
            'estudio_id' => $estudio->id,
            'razon_social' => 'CLIENTE PRIVADO CONTABLE SAC',
            'ruc' => '20123456789',
            'regimen_tributario' => 'General',
            'estado' => 'activo',
        ]);

        $cliente->credencial()->create([
            'usuario_sol' => 'CLAVEREAL',
            'clave_sol' => 'SuperSecreta',
        ]);

        $cliente->notificaciones()->create([
            'tipo' => 'SUNAT',
            'emisor' => 'SUNAT',
            'asunto' => 'Alerta Real Privada',
            'mensaje' => 'Contenido privado',
            'fecha' => now(),
            'leido' => false,
        ]);

        // Verificar que los datos existen antes del reset
        $this->assertCount(1, Cliente::all());
        $this->assertDatabaseHas('credenciales', ['usuario_sol' => 'CLAVEREAL']);
        $this->assertDatabaseHas('notificaciones', ['asunto' => 'Alerta Real Privada']);

        // 2. Ejecutar Factory Reset en consola
        $this->artisan('crm:factory-reset', [
            '--force' => true,
            '--no-backup' => true,
            '--clean-install' => true,
        ])->assertExitCode(0);

        // 3. Verificar que las tablas privadas quedaron totalmente vacías (0 registros)
        $this->assertCount(0, Cliente::all());
        $this->assertDatabaseMissing('clientes', ['ruc' => '20123456789']);
        $this->assertDatabaseMissing('credenciales', ['usuario_sol' => 'CLAVEREAL']);
        $this->assertDatabaseMissing('notificaciones', ['asunto' => 'Alerta Real Privada']);
        $this->assertDatabaseMissing('users', ['email' => 'real@desarrollador.com']);

        // 4. Verificar semillas maestras de fábrica ("0 Kilómetros")
        $adminFabrica = User::where('email', 'admin@estudio.pe')->first();
        $this->assertNotNull($adminFabrica);
        $this->assertEquals('Administrador', $adminFabrica->name);
        $this->assertEquals('admin', $adminFabrica->rol);
        $this->assertTrue(Hash::check('admin123', $adminFabrica->password));

        $estudioFabrica = Estudio::find(1);
        $this->assertNotNull($estudioFabrica);
        $this->assertEquals('Mi Estudio Contable', $estudioFabrica->nombre);

        $ajusteFabrica = AjusteSistema::where('estudio_id', 1)->first();
        $this->assertNotNull($ajusteFabrica);
        $this->assertEquals('Mi Estudio Contable S.A.C.', $ajusteFabrica->razon_social);
        $this->assertEquals('20000000001', $ajusteFabrica->ruc);
        $this->assertNull($ajusteFabrica->telefono_whatsapp);

        // 5. Verificar que el cronograma SUNAT oficial fue sembrado
        $this->assertGreaterThan(0, CronogramaSunat::count());
    }

    public function test_onboarding_welcome_banner_is_displayed_when_zero_clients(): void
    {
        $estudio = Estudio::create([
            'nombre' => 'Mi Estudio Contable',
            'correo' => 'admin@estudio.pe',
            'activo' => true,
        ]);

        $admin = User::create([
            'estudio_id' => $estudio->id,
            'name' => 'Administrador',
            'email' => 'admin@estudio.pe',
            'password' => Hash::make('admin123'),
            'rol' => 'admin',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('clientes.index'));

        $response->assertStatus(200);
        $response->assertSee('¡Bienvenido a tu CRM Contable!');
        $response->assertSee('0 Kilómetros');
        $response->assertSee('Configurar Estudio');
        $response->assertSee('Registrar Primer Cliente');
    }

    public function test_restore_backup_command_executes_successfully(): void
    {
        $backupService = app(DatabaseBackupService::class);
        $customName = 'crm_backup_test_restore.sql';
        $backup = $backupService->createBackup($customName);

        $this->artisan('crm:restore-backup', [
            'archivo' => $backup['path'],
            '--force' => true,
        ])->assertExitCode(0);

        File::delete($backup['path']);
    }
}
