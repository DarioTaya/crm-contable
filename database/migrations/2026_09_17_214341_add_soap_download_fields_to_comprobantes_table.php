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
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->enum('estado_descarga', ['pendiente', 'descargando', 'disponible', 'error'])
                ->default('pendiente')
                ->after('estado_sire')
                ->comment('Estado general de descarga asíncrona del comprobante');

            $table->enum('estado_xml', ['pendiente', 'descargando', 'disponible', 'error'])
                ->default('pendiente')
                ->after('estado_descarga')
                ->comment('Estado del archivo XML');

            $table->enum('estado_cdr', ['pendiente', 'descargando', 'disponible', 'error'])
                ->default('pendiente')
                ->after('estado_xml')
                ->comment('Estado de la constancia CDR');

            $table->enum('estado_pdf', ['pendiente', 'descargando', 'disponible', 'error'])
                ->default('pendiente')
                ->after('estado_cdr')
                ->comment('Estado de la representación impresa PDF');

            $table->text('mensaje_soap')->nullable()->after('ruta_cdr')->comment('Respuesta oficial o diagnóstico del servicio SOAP SUNAT');
            $table->unsignedTinyInteger('intentos_soap')->default(0)->after('mensaje_soap')->comment('Contador de intentos de consulta SOAP');

            $table->index(['cliente_id', 'estado_descarga']);
            $table->index(['periodo', 'estado_descarga']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropIndex(['cliente_id', 'estado_descarga']);
            $table->dropIndex(['periodo', 'estado_descarga']);
            $table->dropColumn([
                'estado_descarga',
                'estado_xml',
                'estado_cdr',
                'estado_pdf',
                'mensaje_soap',
                'intentos_soap',
            ]);
        });
    }
};
