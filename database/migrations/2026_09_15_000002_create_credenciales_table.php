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
        Schema::create('credenciales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->unique()
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Relación 1 a 1 con el cliente');
            $table->string('usuario_sol')->nullable()->comment('Usuario clave SOL SUNAT');
            $table->text('clave_sol')->nullable()->comment('Clave SOL encriptada (AES-256)');
            $table->string('usuario_afp')->nullable()->comment('Usuario AFPnet');
            $table->text('clave_afp')->nullable()->comment('Clave AFPnet encriptada (AES-256)');
            $table->char('dni_titular', 8)->nullable()->comment('DNI del titular o representante legal (8 dígitos)');
            $table->text('clave_detracciones')->nullable()->comment('Clave cuenta detracciones Banco de la Nación encriptada');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credenciales');
    }
};
