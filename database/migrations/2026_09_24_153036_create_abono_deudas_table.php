<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla de abonos parciales a deudas tributarias.
     *
     * Cada registro representa un pago parcial o total que el cliente realizó
     * para saldar una deuda con SUNAT (IGV Justo, fraccionamiento, etc.)
     * asociada a una operación mensual.
     */
    public function up(): void
    {
        Schema::create('abonos_deudas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('operacion_id')->constrained('operaciones')->cascadeOnDelete();
            $table->decimal('monto', 10, 2);
            $table->date('fecha_pago');
            $table->string('observacion')->nullable();
            $table->timestamps();

            $table->index(['estudio_id', 'operacion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_deudas');
    }
};
