<?php

use App\Models\Estudio;
use App\Services\Catalogos\CatalogoSunat;
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
        Schema::create('catalogo_tributos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->string('codigo', 10);
            $table->string('denominacion', 180);
            $table->string('nombre_corto', 80);
            $table->string('formulario', 20);
            $table->string('rol', 40);
            $table->json('regimenes')->nullable();
            $table->boolean('sistema')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['estudio_id', 'codigo']);
        });

        Schema::create('catalogo_medios_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->string('codigo', 40);
            $table->string('denominacion', 120);
            $table->boolean('sistema')->default(false);
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['estudio_id', 'codigo']);
        });

        $catalogo = new CatalogoSunat;
        foreach (Estudio::query()->pluck('id') as $estudioId) {
            $catalogo->sembrar((int) $estudioId);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalogo_medios_pago');
        Schema::dropIfExists('catalogo_tributos');
    }
};
