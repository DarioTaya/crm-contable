<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega y normaliza los campos de deuda en la tabla operaciones.
     *
     * Soporta tanto ejecuciones limpias (SQLite en pruebas / migraciones frescas)
     * como bases de datos donde parte de las columnas ya existían.
     */
    public function up(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('operaciones', 'tiene_deuda')) {
                $table->boolean('tiene_deuda')->default(false);
            }

            if (Schema::hasColumn('operaciones', 'monto_deuda')) {
                $table->renameColumn('monto_deuda', 'monto_deuda_inicial');
            } elseif (! Schema::hasColumn('operaciones', 'monto_deuda_inicial')) {
                $table->decimal('monto_deuda_inicial', 10, 2)->nullable();
            }

            if (! Schema::hasColumn('operaciones', 'vencimiento_deuda')) {
                $table->date('vencimiento_deuda')->nullable();
            }

            if (Schema::hasColumn('operaciones', 'notas_deuda')) {
                $table->dropColumn('notas_deuda');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operaciones', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('operaciones', 'tiene_deuda')) {
                $columnsToDrop[] = 'tiene_deuda';
            }
            if (Schema::hasColumn('operaciones', 'monto_deuda_inicial')) {
                $columnsToDrop[] = 'monto_deuda_inicial';
            }
            if (Schema::hasColumn('operaciones', 'vencimiento_deuda')) {
                $columnsToDrop[] = 'vencimiento_deuda';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
