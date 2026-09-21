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
            $table->string('cuenta_detracciones', 50)
                ->nullable()
                ->after('dni_titular')
                ->comment('Número de cuenta de detracciones Banco de la Nación');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credenciales', function (Blueprint $table) {
            $table->dropColumn('cuenta_detracciones');
        });
    }
};
