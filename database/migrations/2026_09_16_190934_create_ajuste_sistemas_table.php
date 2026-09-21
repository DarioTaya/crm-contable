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
        Schema::create('ajustes_sistema', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('ruc', 11);
            $table->string('representante');
            $table->string('correo_corporativo')->nullable();
            $table->string('telefono_whatsapp', 50)->nullable();
            $table->unsignedSmallInteger('frecuencia_cron_horas')->default(8);
            $table->boolean('notificar_whatsapp')->default(false);
            $table->string('nivel_alerta_whatsapp', 20)->default('Solo Altas');
            $table->timestamp('ultima_sincronizacion_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ajustes_sistema');
    }
};
