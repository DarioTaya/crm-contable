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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('estado', 30)->default('activo')->change();

            if (! Schema::hasColumn('clientes', 'fecha_baja')) {
                $table->date('fecha_baja')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('clientes', 'mes_baja')) {
                $table->string('mes_baja', 7)->nullable()->after('fecha_baja')->comment('Periodo YYYY-MM desde el cual cesó en el estudio');
            }
            if (! Schema::hasColumn('clientes', 'motivo_baja')) {
                $table->string('motivo_baja', 255)->nullable()->after('mes_baja')->comment('Motivo del cese, cambio de contador o suspensión');
            }
            if (! Schema::hasColumn('clientes', 'notas_estado')) {
                $table->text('notas_estado')->nullable()->after('motivo_baja')->comment('Observaciones de entrega de libros o notas internas');
            }
        });

        // Asegurar que cualquier valor legacy quede normalizado
        DB::statement("UPDATE clientes SET estado = 'activo' WHERE estado IN ('1', 'true') OR estado IS NULL");
        DB::statement("UPDATE clientes SET estado = 'retirado' WHERE estado IN ('0', 'false')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE clientes SET estado = '1' WHERE estado = 'activo'");
        DB::statement("UPDATE clientes SET estado = '0' WHERE estado != '1'");

        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('estado')->default(true)->change();
            $table->dropColumn(['fecha_baja', 'mes_baja', 'motivo_baja', 'notas_estado']);
        });
    }
};
