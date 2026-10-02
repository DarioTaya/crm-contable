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
        Schema::table('estudios', function (Blueprint $table) {
            if (! Schema::hasColumn('estudios', 'logo_reportes_path')) {
                $table->string('logo_reportes_path')->nullable()->after('isotipo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            if (Schema::hasColumn('estudios', 'logo_reportes_path')) {
                $table->dropColumn('logo_reportes_path');
            }
        });
    }
};
