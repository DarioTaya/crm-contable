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
            $table->foreignId('estudio_id')->nullable()->after('id')->constrained('estudios')->nullOnDelete();
            $table->string('rol', 30)->default('asistente')->after('password')->index();
            $table->boolean('activo')->default(true)->after('rol')->index();
            $table->string('telefono', 30)->nullable()->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['estudio_id']);
            $table->dropColumn(['estudio_id', 'rol', 'activo', 'telefono']);
        });
    }
};
