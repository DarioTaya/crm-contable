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
        Schema::create('liquidaciones_periodo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('operacion_id')->constrained('operaciones')->cascadeOnDelete();
            $table->string('periodo', 7); // MM-AAAA

            // Liquidación IGV (PDT 621)
            $table->decimal('ventas_base', 12, 2)->default(0);
            $table->decimal('igv_ventas', 12, 2)->default(0);
            $table->decimal('compras_base', 12, 2)->default(0);
            $table->decimal('igv_compras', 12, 2)->default(0);
            $table->decimal('igv_saldo_favor_anterior', 12, 2)->default(0); // Casilla 145 PDT 621
            $table->decimal('igv_retenciones', 12, 2)->default(0); // Casilla 179 PDT 621
            $table->decimal('igv_percepciones', 12, 2)->default(0); // Casilla 171 PDT 621
            $table->decimal('igv_saldo_favor_siguiente', 12, 2)->default(0); // Arrastre para mes siguiente
            $table->decimal('igv_resultante_pagar', 12, 2)->default(0); // Casilla 180 PDT 621

            // Liquidación Renta Mensual (PDT 621)
            $table->decimal('renta_base_ingresos', 12, 2)->default(0);
            $table->decimal('renta_tasa', 6, 4)->default(0); // Ej. 1.0000 o 1.5000 o coeficiente
            $table->decimal('renta_calculada', 12, 2)->default(0);
            $table->decimal('renta_saldo_favor_anterior', 12, 2)->default(0); // Casilla 109 PDT 621
            $table->decimal('renta_compensacion_itan', 12, 2)->default(0); // Casilla 111 PDT 621
            $table->decimal('renta_retenciones', 12, 2)->default(0); // Retenciones 3ra categoría
            $table->decimal('renta_resultante_pagar', 12, 2)->default(0); // Casilla 302 PDT 621

            $table->text('notas_liquidacion')->nullable();
            $table->timestamps();

            $table->unique('operacion_id');
            $table->unique(['cliente_id', 'periodo']);
            $table->index(['estudio_id', 'periodo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liquidaciones_periodo');
    }
};
