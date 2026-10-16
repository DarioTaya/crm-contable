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
        Schema::create('padrones_riesgo_sunat', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_padron', 30)->index(); // ssco_firme, ssco_vinculado, deuda_coactiva
            $table->string('ruc', 11)->index();
            $table->string('razon_social', 350)->nullable();
            $table->string('nombre_comercial', 250)->nullable();
            $table->string('domicilio_fiscal', 500)->nullable();
            $table->string('resolucion_atribucion', 150)->nullable();
            $table->string('fecha_emision_resolucion', 20)->nullable();
            $table->string('fecha_firme_resolucion', 20)->nullable();
            $table->string('fecha_publicacion', 20)->nullable();
            $table->string('fecha_limite_restriccion', 20)->nullable();
            $table->string('doc_representante', 25)->nullable()->index();
            $table->string('nombre_representante', 250)->nullable();
            $table->string('ruc_ssco_origen', 11)->nullable()->index();
            $table->string('razon_social_ssco_origen', 350)->nullable();
            $table->string('supuesto_inclusion', 500)->nullable();
            $table->string('dependencia_sunat', 100)->nullable();
            $table->decimal('monto_deuda', 16, 2)->nullable();
            $table->unsignedInteger('cantidad_representantes')->nullable();
            $table->string('corte_sunat', 120)->nullable();
            $table->timestamps();

            $table->index(['tipo_padron', 'ruc']);
            $table->index(['tipo_padron', 'doc_representante']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('padrones_riesgo_sunat');
    }
};
