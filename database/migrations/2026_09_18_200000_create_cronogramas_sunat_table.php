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
        Schema::create('cronogramas_sunat', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio');
            $table->string('tipo', 30); // 'pdt_621', 'sire', 'balance_mensual', 'balance_anual'
            $table->unsignedTinyInteger('mes')->nullable(); // 1 al 12
            $table->string('periodo', 20); // '01-2026', '02-2026', ..., 'anual-2026'
            $table->date('digito_0')->nullable();
            $table->date('digito_1')->nullable();
            $table->date('digito_2_3')->nullable();
            $table->date('digito_4_5')->nullable();
            $table->date('digito_6_7')->nullable();
            $table->date('digito_8_9')->nullable();
            $table->date('buenos_contribuyentes')->nullable();
            $table->date('fecha_vencimiento_general')->nullable(); // para balances o fechas únicas
            $table->string('descripcion')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'tipo', 'periodo']);
            $table->index(['anio', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cronogramas_sunat');
    }
};
