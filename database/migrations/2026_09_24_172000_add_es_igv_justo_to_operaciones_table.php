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
        Schema::table('operaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('operaciones', 'es_igv_justo')) {
                $table->boolean('es_igv_justo')->default(false)->after('tributo_codigo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            if (Schema::hasColumn('operaciones', 'es_igv_justo')) {
                $table->dropColumn('es_igv_justo');
            }
        });
    }
};
