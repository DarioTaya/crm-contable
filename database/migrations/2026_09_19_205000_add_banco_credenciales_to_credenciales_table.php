<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credenciales', function (Blueprint $table) {
            // Bloque 1: Credencial / Acceso adicional (ej: BCP)
            $table->string('banco_1_nombre', 100)->nullable()->after('clave_detracciones')->comment('Nombre o etiqueta del acceso 1 (ej. Telecrédito BCP)');
            $table->string('banco_1_usuario', 255)->nullable()->after('banco_1_nombre')->comment('Usuario o número de tarjeta del acceso 1');
            $table->text('banco_1_clave')->nullable()->after('banco_1_usuario')->comment('Clave del acceso 1 encriptada (AES-256)');
            $table->text('banco_1_notas')->nullable()->after('banco_1_clave')->comment('Notas u observaciones del acceso 1');

            // Bloque 2: Credencial / Acceso adicional (ej: BBVA)
            $table->string('banco_2_nombre', 100)->nullable()->after('banco_1_notas')->comment('Nombre o etiqueta del acceso 2 (ej. BBVA Net Cash)');
            $table->string('banco_2_usuario', 255)->nullable()->after('banco_2_nombre')->comment('Usuario o número de tarjeta del acceso 2');
            $table->text('banco_2_clave')->nullable()->after('banco_2_usuario')->comment('Clave del acceso 2 encriptada (AES-256)');
            $table->text('banco_2_notas')->nullable()->after('banco_2_clave')->comment('Notas u observaciones del acceso 2');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credenciales', function (Blueprint $table) {
            $table->dropColumn([
                'banco_1_nombre',
                'banco_1_usuario',
                'banco_1_clave',
                'banco_1_notas',
                'banco_2_nombre',
                'banco_2_usuario',
                'banco_2_clave',
                'banco_2_notas',
            ]);
        });
    }
};
