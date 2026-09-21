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
        Schema::create('operaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Relación con el cliente');
            $table->string('periodo', 7)->comment('Periodo contable mensual, ej. 08-2026');
            $table->string('estado_compras_ventas')->default('Pendiente')->comment('Estado de Registro de Compras y Ventas (SIRE/Manual)');
            $table->string('estado_pdt621')->default('Pendiente')->comment('Estado de Declaración PDT 621 IGV-Renta');
            $table->string('estado_ple')->default('Pendiente')->comment('Estado de Libros Electrónicos PLE');
            $table->timestamps();

            // Garantiza que no se duplique un periodo contable por cliente
            $table->unique(['cliente_id', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operaciones');
    }
};
