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
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Relación 1 a muchos con el cliente');
            $table->date('fecha')->comment('Fecha de emisión o notificación');
            $table->string('asunto')->comment('Asunto o descripción del buzón electrónico');
            $table->enum('emisor', ['SUNAT', 'SUNAFIL'])->comment('Entidad emisora de la notificación');
            $table->boolean('leido')->default(false)->comment('Estado de lectura: false (Pendiente), true (Leído)');
            $table->timestamps();

            $table->index(['cliente_id', 'fecha']);
            $table->index('leido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};
