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
        Schema::table('users', function (Blueprint $table) {
            $table->string('dni', 15)->nullable()->after('email');
            $table->string('cargo', 100)->nullable()->after('rol');
            $table->date('fecha_nacimiento')->nullable()->after('telefono');
            $table->date('fecha_ingreso')->nullable()->after('fecha_nacimiento');
            $table->date('fecha_cese')->nullable()->after('fecha_ingreso');
            $table->string('direccion', 255)->nullable()->after('fecha_cese');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'dni',
                'cargo',
                'fecha_nacimiento',
                'fecha_ingreso',
                'fecha_cese',
                'direccion',
            ]);
        });
    }
};
