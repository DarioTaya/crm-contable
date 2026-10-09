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
        Schema::table('alertas_buzon', function (Blueprint $table) {
            $table->string('ruta_constancia_pdf')->nullable()->after('nombre_archivo_pdf')->comment('Ruta relativa de la constancia de notificación PDF en storage/app');
            $table->string('nombre_archivo_constancia_pdf')->nullable()->after('ruta_constancia_pdf')->comment('Nombre original del archivo de constancia de notificación');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alertas_buzon', function (Blueprint $table) {
            $table->dropColumn(['ruta_constancia_pdf', 'nombre_archivo_constancia_pdf']);
        });
    }
};
