<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Casillas del PDT 621 que entran al débito, al crédito o a la base y no estaban en la hoja.
     */
    public function up(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->decimal('igv_descuentos_base', 12, 2)->default(0)->after('igv_ventas');
            $table->decimal('igv_descuentos', 12, 2)->default(0)->after('igv_descuentos_base');
            $table->decimal('igv_ventas_ley_base', 12, 2)->default(0)->after('ventas_no_gravadas');
            $table->decimal('igv_ventas_ley', 12, 2)->default(0)->after('igv_ventas_ley_base');
            $table->decimal('igv_compras_ley_base', 12, 2)->default(0)->after('igv_compras');
            $table->decimal('igv_compras_ley', 12, 2)->default(0)->after('igv_compras_ley_base');
            $table->decimal('igv_compras_mixtas_base', 12, 2)->default(0)->after('igv_compras_ley');
            $table->decimal('igv_compras_mixtas', 12, 2)->default(0)->after('igv_compras_mixtas_base');
            $table->decimal('igv_importaciones_base', 12, 2)->default(0)->after('igv_compras_mixtas');
            $table->decimal('igv_importaciones', 12, 2)->default(0)->after('igv_importaciones_base');
        });

        if (Schema::hasTable('catalogo_tributos')) {
            DB::table('catalogo_tributos')->where('codigo', 'AFP')->update(['codigo' => '6098']);
        }

        if (Schema::hasTable('deuda_tributarias')) {
            DB::table('deuda_tributarias')->where('tributo_codigo', 'AFP')->update(['tributo_codigo' => '6098']);
        }

        if (Schema::hasTable('operaciones') && Schema::hasColumn('operaciones', 'tributo_codigo')) {
            DB::table('operaciones')->where('tributo_codigo', 'AFP')->update(['tributo_codigo' => '6098']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('catalogo_tributos')) {
            DB::table('catalogo_tributos')->where('codigo', '6098')->where('rol', 'afp')->update(['codigo' => 'AFP']);
        }

        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn([
                'igv_descuentos_base',
                'igv_descuentos',
                'igv_ventas_ley_base',
                'igv_ventas_ley',
                'igv_compras_ley_base',
                'igv_compras_ley',
                'igv_compras_mixtas_base',
                'igv_compras_mixtas',
                'igv_importaciones_base',
                'igv_importaciones',
            ]);
        });
    }
};
