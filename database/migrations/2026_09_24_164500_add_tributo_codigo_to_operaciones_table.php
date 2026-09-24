<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega el código oficial de tributo SUNAT a las operaciones con deuda.
     */
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('operaciones', 'tributo_codigo')) {
                $table->string('tributo_codigo', 20)->nullable()->after('tiene_deuda')
                    ->comment('Código oficial de tributo SUNAT (1011, 1016, 3121, etc.)');
            }
        });
    }

    /**
     * Revierte las migraciones.
     */
    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            if (Schema::hasColumn('operaciones', 'tributo_codigo')) {
                $table->dropColumn('tributo_codigo');
            }
        });
    }
};
