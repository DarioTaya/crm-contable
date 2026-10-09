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
            $table->string('numero_notificacion', 60)->nullable()->after('asunto')->comment('Código o número de resolución/esquela/notificación de SUNAT');
            $table->boolean('tiene_pdf')->default(false)->after('prioridad')->comment('Indica si la alerta contiene un documento PDF oficial adjunto');
            $table->string('ruta_pdf')->nullable()->after('tiene_pdf')->comment('Ruta relativa del PDF almacenado en storage/app');
            $table->string('nombre_archivo_pdf')->nullable()->after('ruta_pdf')->comment('Nombre original del archivo PDF para la descarga');

            $table->index(['plataforma', 'tiene_pdf']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alertas_buzon', function (Blueprint $table) {
            $table->dropIndex(['plataforma', 'tiene_pdf']);
            $table->dropColumn(['numero_notificacion', 'tiene_pdf', 'ruta_pdf', 'nombre_archivo_pdf']);
        });
    }
};
