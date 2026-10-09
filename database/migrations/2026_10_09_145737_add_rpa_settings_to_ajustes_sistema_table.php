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
        Schema::table('ajustes_sistema', function (Blueprint $table) {
            $table->boolean('descargar_adjuntos_pdf')->default(true)->after('frecuencia_cron_horas');
            $table->unsignedTinyInteger('pausa_anti_bloqueo_segundos')->default(3)->after('descargar_adjuntos_pdf');
            $table->unsignedSmallInteger('limite_mensajes_buzon')->default(15)->after('pausa_anti_bloqueo_segundos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table) {
            $table->dropColumn([
                'descargar_adjuntos_pdf',
                'pausa_anti_bloqueo_segundos',
                'limite_mensajes_buzon',
            ]);
        });
    }
};
