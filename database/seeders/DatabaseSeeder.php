<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Asegurar la existencia del Estudio Contable Principal
        $estudio = Estudio::firstOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Estudio Contable Principal',
                'correo' => 'decontador.pe@gmail.com',
                'telefono' => '925147601',
                'activo' => true,
            ]
        );

        // 2. Crear o actualizar Usuario Administrador Principal
        User::firstOrCreate(
            ['email' => 'decontador.pe@gmail.com'],
            [
                'estudio_id' => $estudio->id,
                'name' => 'Administrador Principal',
                'password' => Hash::make('admin123'),
                'rol' => 'admin',
                'activo' => true,
                'telefono' => '925147601',
            ]
        );

        // 3. Crear Usuario Asistente Contable de demostración
        $asistente = User::firstOrCreate(
            ['email' => 'asistente@estudio.pe'],
            [
                'estudio_id' => $estudio->id,
                'name' => 'Asistente Contable',
                'password' => Hash::make('asistente123'),
                'rol' => 'asistente',
                'activo' => true,
                'telefono' => '987654321',
            ]
        );

        // 4. Asignar los primeros clientes a la cartera del asistente
        $primerosClientes = Cliente::take(3)->pluck('id');
        if ($primerosClientes->isNotEmpty()) {
            $asistente->clientes()->syncWithoutDetaching($primerosClientes);
        }

        // 5. Semillas de ajustes del sistema y cronogramas tributarios
        $this->call([
            AjusteSistemaSeeder::class,
            CronogramaSunatSeeder::class,
        ]);
    }
}
