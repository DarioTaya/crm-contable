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
        Schema::create('control_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Relación con el cliente (MYPE o General)');
            $table->integer('anio')->comment('Año del ejercicio fiscal, ej. 2026');
            $table->json('meses')->nullable()->comment('Estado de balances de los 12 meses');
            $table->string('dj_anual', 20)->default('Pendiente')->comment('Estado de DJ Anual del año anterior (Pendiente, Procesando, Declarado)');
            $table->json('notas')->nullable()->comment('Notas mensuales del balance');
            $table->timestamps();

            // Garantiza un único registro de balance por cliente y año
            $table->unique(['cliente_id', 'anio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('control_balances');
    }
};
