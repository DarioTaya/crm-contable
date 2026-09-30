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
            if (! Schema::hasColumn('estudios', 'nombre_comercial')) {
                $table->string('nombre_comercial')->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('estudios', 'representante')) {
                $table->string('representante')->nullable()->after('ruc');
            }
            if (! Schema::hasColumn('estudios', 'colegiatura')) {
                $table->string('colegiatura', 50)->nullable()->after('representante');
            }
            if (! Schema::hasColumn('estudios', 'slogan')) {
                $table->string('slogan')->nullable()->after('colegiatura');
            }
            if (! Schema::hasColumn('estudios', 'ciudad')) {
                $table->string('ciudad', 100)->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('estudios', 'sitio_web')) {
                $table->string('sitio_web')->nullable()->after('telefono');
            }
            if (! Schema::hasColumn('estudios', 'sidebar_display')) {
                $table->string('sidebar_display', 20)->default('logo_nombre')->after('logo_path');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $columns = [
                'nombre_comercial',
                'representante',
                'colegiatura',
                'slogan',
                'ciudad',
                'sitio_web',
                'sidebar_display',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('estudios', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
