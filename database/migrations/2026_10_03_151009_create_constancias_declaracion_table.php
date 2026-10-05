<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expediente de cada constancia o detalle de PDT 621 / PDT 601 importado.
     */
    public function up(): void
    {
        Schema::create('constancias_declaracion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('operacion_id')->nullable()->constrained('operaciones')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('formulario', 3);
            $table->string('periodo', 7);
            $table->string('ruc', 11);
            $table->string('razon_social')->nullable();
            $table->string('numero_orden', 20)->nullable();
            $table->dateTime('fecha_presentacion')->nullable();
            $table->string('tipo_declaracion', 20);
            $table->string('archivo_path');
            $table->string('archivo_nombre');
            $table->char('archivo_hash', 64);
            $table->json('casillas');
            $table->json('tributos');
            $table->json('alertas');
            $table->boolean('tiene_detalle')->default(false);
            $table->timestamps();

            $table->unique(
                ['cliente_id', 'formulario', 'periodo', 'archivo_hash'],
                'constancia_archivo_unico',
            );
            $table->index(['cliente_id', 'periodo', 'formulario']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('constancias_declaracion');
    }
};
