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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->char('ruc', 11)->unique()->comment('RUC peruano de 11 dígitos');
            $table->enum('regimen_tributario', ['MYPE', 'RER', 'RUS', 'General'])->comment('Régimen tributario SUNAT');
            $table->string('correo')->nullable();
            $table->string('telefono', 20)->nullable();
            $table->boolean('estado')->default(true)->comment('true: Activo, false: Inactivo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
