<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Propaga `estudio_id` a las tablas que cuelgan de un cliente.
 *
 * Hasta ahora el aislamiento por inquilino solo existia en `clientes` y
 * `ajustes_sistema`. Las tablas hijas dependian de `cliente_id` sin ambito
 * propio, de modo que cualquier consulta que no partiera de `clientes` veia
 * los datos de todos los estudios, y una ruta con enlace de modelo resolvia
 * un `{comprobante}` o `{alerta}` de otro inquilino por su solo identificador.
 *
 * Se denormaliza la columna a proposito: es el patron habitual en multi-tenant
 * porque permite filtrar con un indice y sin unir contra `clientes` en cada
 * consulta.
 */
return new class extends Migration
{
    /**
     * Indices compuestos adicionales por tabla, orientados a las consultas que
     * el sistema ya ejecuta con mas frecuencia.
     *
     * @var array<string, array<int, string>>
     */
    private const INDICES = [
        'alertas_buzon' => ['estado'],
        'comprobantes' => ['periodo'],
        'operaciones' => ['periodo'],
    ];

    private const TABLAS = [
        'credenciales',
        'notificaciones',
        'operaciones',
        'control_balances',
        'alertas_buzon',
        'comprobantes',
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('estudio_id')->nullable()->after('id');
            });

            $this->heredarEstudioDelCliente($tabla);

            // La clave foranea se agrega despues de fijar NOT NULL: en SQLite
            // un cambio de columna reconstruye la tabla y se perderia.
            Schema::table($tabla, function (Blueprint $table) {
                $table->unsignedBigInteger('estudio_id')->nullable(false)->change();
            });

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->foreign('estudio_id', "{$tabla}_estudio_id_foreign")
                    ->references('id')->on('estudios')->cascadeOnDelete();

                foreach (self::INDICES[$tabla] ?? [] as $columna) {
                    $table->index(['estudio_id', $columna], "{$tabla}_estudio_{$columna}_index");
                }
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLAS) as $tabla) {
            // La clave foranea se suelta primero: MySQL exige un indice sobre la
            // columna referenciada y se apoya en el compuesto (estudio_id, ...),
            // por lo que intentar borrar ese indice antes falla con el error 1553.
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropForeign("{$tabla}_estudio_id_foreign");
            });

            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                foreach (self::INDICES[$tabla] ?? [] as $columna) {
                    $table->dropIndex("{$tabla}_estudio_{$columna}_index");
                }

                $table->dropColumn('estudio_id');
            });
        }
    }

    /**
     * Cada fila adopta el estudio del cliente al que pertenece. La integridad
     * referencial existente garantiza que no haya filas huerfanas, de modo que
     * la subconsulta siempre resuelve.
     */
    private function heredarEstudioDelCliente(string $tabla): void
    {
        DB::statement(
            "update {$tabla} set estudio_id = ".
            "(select clientes.estudio_id from clientes where clientes.id = {$tabla}.cliente_id)"
        );
    }
};
