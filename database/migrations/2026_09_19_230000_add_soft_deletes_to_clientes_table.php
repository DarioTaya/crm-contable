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
        if (! Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->softDeletes()->after('updated_at');
                $table->index(['estudio_id', 'deleted_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'deleted_at')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex(['estudio_id', 'deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
