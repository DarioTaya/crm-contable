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
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->decimal('renta_itan_saldo_anterior', 12, 2)->default(0)->after('renta_compensacion_itan');
            $table->decimal('renta_itan_saldo_siguiente', 12, 2)->default(0)->after('renta_itan_saldo_anterior');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn(['renta_itan_saldo_anterior', 'renta_itan_saldo_siguiente']);
        });
    }
};
