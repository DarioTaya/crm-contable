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
            $table->boolean('es_zona_selva')->default(false)->after('periodo');
            $table->decimal('ventas_no_gravadas', 12, 2)->default(0)->after('igv_ventas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn(['es_zona_selva', 'ventas_no_gravadas']);
        });
    }
};
