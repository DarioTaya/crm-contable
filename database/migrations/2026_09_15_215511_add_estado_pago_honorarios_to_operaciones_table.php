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
            $table->string('estado_pago_honorarios', 20)->default('Pendiente')->after('notas');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $table->dropColumn('estado_pago_honorarios');
        });
    }
};
