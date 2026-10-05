<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Recuerda cuándo el importe a pagar lo fijó la constancia de SUNAT
     * y no el motor interno de la hoja.
     */
    public function up(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->boolean('igv_determinado_por_sunat')->default(false)->after('igv_resultante_pagar');
            $table->boolean('renta_determinado_por_sunat')->default(false)->after('renta_resultante_pagar');
            $table->string('renta_tributo_codigo', 10)->nullable()->after('renta_determinado_por_sunat');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn([
                'igv_determinado_por_sunat',
                'renta_determinado_por_sunat',
                'renta_tributo_codigo',
            ]);
        });
    }
};
