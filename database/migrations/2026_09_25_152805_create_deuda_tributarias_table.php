<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una operación mensual puede adeudar varios tributos SUNAT a la vez.
     */
    public function up(): void
    {
        Schema::create('deuda_tributarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('operacion_id')->constrained('operaciones')->cascadeOnDelete();
            $table->string('tributo_codigo', 20);
            $table->boolean('es_igv_justo')->default(false);
            $table->decimal('monto_deuda_inicial', 10, 2)->default(0);
            $table->date('vencimiento_deuda')->nullable();
            $table->timestamps();

            $table->unique(['operacion_id', 'tributo_codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deuda_tributarias');
    }
};
