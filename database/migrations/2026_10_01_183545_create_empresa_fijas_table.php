<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Empresas que cada usuario deja fijas en el acceso rápido.
     * El cupo es de cinco; el orden guarda cuál se fijó al último.
     */
    public function up(): void
    {
        Schema::create('empresa_fijas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->timestamps();

            $table->unique(['user_id', 'cliente_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresa_fijas');
    }
};
