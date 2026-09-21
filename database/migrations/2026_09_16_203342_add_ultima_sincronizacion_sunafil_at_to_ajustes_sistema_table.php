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
        Schema::table('ajustes_sistema', function (Blueprint $table) {
            $table->timestamp('ultima_sincronizacion_sunafil_at')->nullable()->after('ultima_sincronizacion_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ajustes_sistema', function (Blueprint $table) {
            $table->dropColumn('ultima_sincronizacion_sunafil_at');
        });
    }
};
