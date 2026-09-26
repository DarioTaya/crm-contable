<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Origen del pago: detracciones, caja, bancos, compensación de renta o ITAN.
     * Queda nulo en pagos anteriores, que el reporte muestra sin clasificar.
     */
    public function up(): void
    {
        Schema::table('abonos_deudas', function (Blueprint $table) {
            $table->string('medio_pago', 40)->nullable()->after('fecha_pago');
        });
    }

    public function down(): void
    {
        Schema::table('abonos_deudas', function (Blueprint $table) {
            $table->dropColumn('medio_pago');
        });
    }
};
