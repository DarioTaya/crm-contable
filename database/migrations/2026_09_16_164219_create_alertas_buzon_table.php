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
        Schema::create('alertas_buzon', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Cliente al que pertenece la notificación');
            $table->string('plataforma', 20)->comment('Plataforma emisora: SUNAT o SUNAFIL');
            $table->string('asunto')->comment('Asunto o título de la notificación oficial');
            $table->text('contenido')->nullable()->comment('Cuerpo o detalle completo de la comunicación');
            $table->dateTime('fecha_recepcion')->comment('Fecha y hora en que fue recibida o emitida');
            $table->string('estado', 20)->default('Pendiente')->comment('Estado de lectura: Pendiente o Leído');
            $table->string('prioridad', 20)->default('Media')->comment('Nivel de prioridad: Alta, Media o Baja');
            $table->timestamps();

            // Índices para consultas y filtros rápidos
            $table->index(['cliente_id', 'estado']);
            $table->index(['plataforma', 'estado']);
            $table->index('fecha_recepcion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alertas_buzon');
    }
};
