<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('clientes', 'estudio_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->foreignId('estudio_id')->nullable()->after('id')->constrained('estudios')->cascadeOnDelete();
                $table->index(['estudio_id', 'ruc']);
                $table->index(['estudio_id', 'estado']);
            });
        }

        if (Schema::hasTable('ajustes_sistema') && ! Schema::hasColumn('ajustes_sistema', 'estudio_id')) {
            Schema::table('ajustes_sistema', function (Blueprint $table) {
                $table->foreignId('estudio_id')->nullable()->after('id')->constrained('estudios')->cascadeOnDelete();
                $table->index(['estudio_id']);
            });
        }

        // Asegurar la existencia del Estudio Principal por defecto para datos existentes
        $estudioExiste = DB::table('estudios')->where('id', 1)->exists();
        if (! $estudioExiste) {
            DB::table('estudios')->insert([
                'id' => 1,
                'nombre' => 'Mi Estudio Contable',
                'correo' => 'admin@estudio.pe',
                'telefono' => '900000000',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Asociar datos históricos existentes al Estudio Principal #1
        DB::table('clientes')->whereNull('estudio_id')->update(['estudio_id' => 1]);
        if (Schema::hasTable('ajustes_sistema')) {
            DB::table('ajustes_sistema')->whereNull('estudio_id')->update(['estudio_id' => 1]);
        }
        DB::table('users')->whereNull('estudio_id')->update(['estudio_id' => 1, 'rol' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'estudio_id')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropForeign(['estudio_id']);
                $table->dropIndex(['estudio_id', 'ruc']);
                $table->dropIndex(['estudio_id', 'estado']);
                $table->dropColumn('estudio_id');
            });
        }

        if (Schema::hasTable('ajustes_sistema') && Schema::hasColumn('ajustes_sistema', 'estudio_id')) {
            Schema::table('ajustes_sistema', function (Blueprint $table) {
                $table->dropForeign(['estudio_id']);
                $table->dropIndex(['estudio_id']);
                $table->dropColumn('estudio_id');
            });
        }
    }
};
