<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cimienta la tabla `clientes` sobre el estandar multi-estudio.
 *
 * Tres defectos estructurales impedian que el sistema alojara mas de un estudio
 * contable, lo que bloqueaba la migracion a SaaS prevista para la Fase 2:
 *
 *  1. `ruc` y `numero` eran unicos de forma GLOBAL. Dos estudios distintos no
 *     podian registrar al mismo contribuyente ni compartir una correlativa.
 *  2. `estudio_id` admitia NULL, de modo que una fila podia quedar huerfana y
 *     escapar al ambito del inquilino.
 *  3. `regimen_tributario` era un ENUM nativo de MySQL: agregar un regimen
 *     exigia un ALTER TABLE y no era portable a SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->asegurarQueTodoClienteTengaEstudio();

        Schema::table('clientes', function (Blueprint $table) {
            // La unicidad pasa a estar acotada al inquilino. Se conserva un
            // indice simple sobre `ruc` porque la sincronizacion con el padron
            // SUNAT consulta por RUC sin conocer el estudio.
            $table->dropUnique('clientes_ruc_unique');
            $table->dropUnique('clientes_numero_unique');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->unique(['estudio_id', 'ruc'], 'clientes_estudio_ruc_unique');
            $table->unique(['estudio_id', 'numero'], 'clientes_estudio_numero_unique');
            $table->index('ruc', 'clientes_ruc_index');
        });

        // El indice compuesto (estudio_id, ruc) queda cubierto por el nuevo
        // unique del mismo par de columnas: mantenerlo solo penaliza escrituras.
        if (Schema::hasIndex('clientes', 'clientes_estudio_id_ruc_index')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropIndex('clientes_estudio_id_ruc_index');
            });
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('estudio_id')->nullable(false)->change();

            // De ENUM nativo a cadena: el catalogo de regimenes pasa a vivir en
            // App\Enums\RegimenTributario, que es la unica fuente de verdad.
            $table->string('regimen_tributario', 20)->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('clientes', 'clientes_estudio_id_ruc_index')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->index(['estudio_id', 'ruc'], 'clientes_estudio_id_ruc_index');
            });
        }

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique('clientes_estudio_ruc_unique');
            $table->dropUnique('clientes_estudio_numero_unique');
            $table->dropIndex('clientes_ruc_index');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('estudio_id')->nullable()->change();
            $table->enum('regimen_tributario', ['MYPE', 'RER', 'RUS', 'General'])->change();
            $table->unique('ruc', 'clientes_ruc_unique');
            $table->unique('numero', 'clientes_numero_unique');
        });
    }

    /**
     * Adopta las filas sin inquilino antes de imponer la restriccion NOT NULL.
     */
    private function asegurarQueTodoClienteTengaEstudio(): void
    {
        if (! DB::table('clientes')->whereNull('estudio_id')->exists()) {
            return;
        }

        $estudioId = DB::table('estudios')->min('id')
            ?? DB::table('estudios')->insertGetId([
                'nombre' => 'Estudio Contable',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('clientes')->whereNull('estudio_id')->update(['estudio_id' => $estudioId]);
    }
};
