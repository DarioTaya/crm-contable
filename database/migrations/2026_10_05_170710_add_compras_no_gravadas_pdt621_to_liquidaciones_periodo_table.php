<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Compras no gravadas e importaciones del PDT 621 que no entraban a la hoja.
     */
    public function up(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->decimal('compras_destino_no_gravadas', 12, 2)->default(0)->after('igv_importaciones');
            $table->decimal('igv_importaciones_mixtas_base', 12, 2)->default(0)->after('compras_destino_no_gravadas');
            $table->decimal('igv_importaciones_mixtas', 12, 2)->default(0)->after('igv_importaciones_mixtas_base');
            $table->decimal('importaciones_destino_no_gravadas', 12, 2)->default(0)->after('igv_importaciones_mixtas');
            $table->decimal('compras_internas_no_gravadas', 12, 2)->default(0)->after('importaciones_destino_no_gravadas');
            $table->decimal('compras_importadas_no_gravadas', 12, 2)->default(0)->after('compras_internas_no_gravadas');
        });
    }

    public function down(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn([
                'compras_destino_no_gravadas',
                'igv_importaciones_mixtas_base',
                'igv_importaciones_mixtas',
                'importaciones_destino_no_gravadas',
                'compras_internas_no_gravadas',
                'compras_importadas_no_gravadas',
            ]);
        });
    }
};
