<?php

namespace Database\Seeders;

use App\Models\AjusteSistema;
use App\Models\Estudio;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseCleanSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Semilla maestra de fábrica ("0 Kilómetros") para empaquetado comercial.
     * Solo crea el tenant genérico, el administrador de fábrica y catálogos maestros.
     * CERO clientes, CERO comprobantes, CERO datos privados.
     */
    public function run(): void
    {
        // 1. Crear o actualizar Estudio Contable de fábrica (Tenant genérico)
        $estudio = Estudio::updateOrCreate(
            ['id' => 1],
            [
                'nombre' => 'Mi Estudio Contable',
                'correo' => 'admin@estudio.pe',
                'telefono' => '900000000',
                'activo' => true,
            ]
        );

        // 2. Crear Usuario Administrador Principal de fábrica
        User::firstOrCreate(
            ['email' => 'admin@estudio.pe'],
            [
                'estudio_id' => $estudio->id,
                'name' => 'Administrador',
                'password' => Hash::make('admin123'),
                'rol' => 'admin',
                'activo' => true,
                'telefono' => '900000000',
            ]
        );

        // 3. Crear Configuración Inicial del Sistema de fábrica
        AjusteSistema::firstOrCreate(
            ['estudio_id' => $estudio->id],
            [
                'razon_social' => 'Mi Estudio Contable S.A.C.',
                'ruc' => '20000000001',
                'representante' => 'Contador Titular',
                'correo_corporativo' => 'contacto@estudio.pe',
                'telefono_whatsapp' => null,
                'frecuencia_cron_horas' => 8,
                'notificar_whatsapp' => false,
                'nivel_alerta_whatsapp' => 'Solo Altas',
            ]
        );

        // 4. Sembrar catálogo oficial del Cronograma Tributario SUNAT
        $this->call([
            CronogramaSunatSeeder::class,
        ]);
    }
}
