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
        Schema::table('credenciales', function (Blueprint $table) {
            $table->string('url_afp', 500)->nullable()->after('clave_afp');
            $table->string('url_bn', 500)->nullable()->after('clave_detracciones');
            $table->string('banco_1_url', 500)->nullable()->after('banco_1_notas');
            $table->string('banco_2_url', 500)->nullable()->after('banco_2_notas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credenciales', function (Blueprint $table) {
            $table->dropColumn([
                'url_afp',
                'url_bn',
                'banco_1_url',
                'banco_2_url',
            ]);
        });
    }
};
