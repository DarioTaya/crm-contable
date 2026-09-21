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
            $table->timestamp('notificado_whatsapp_at')
                ->nullable()
                ->after('prioridad')
                ->index()
                ->comment('Fecha y hora en que se envió la alerta por WhatsApp para evitar duplicados');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alertas_buzon', function (Blueprint $table) {
            $table->dropColumn('notificado_whatsapp_at');
        });
    }
};
