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
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('estado_contribuyente', 50)->nullable()->after('regimen_tributario')->comment('Estado SUNAT: ACTIVO, BAJA, etc.');
            $table->string('condicion_domicilio', 50)->nullable()->after('estado_contribuyente')->comment('Condición SUNAT: HABIDO, NO HABIDO, etc.');
            $table->string('tipo_contribuyente', 100)->nullable()->after('condicion_domicilio')->comment('Persona Jurídica o Persona Natural');
            $table->text('direccion_fiscal')->nullable()->after('tipo_contribuyente')->comment('Dirección fiscal SUNAT');
            $table->string('ubigeo', 10)->nullable()->after('direccion_fiscal');
            $table->string('distrito', 100)->nullable()->after('ubigeo');
            $table->string('provincia', 100)->nullable()->after('distrito');
            $table->string('departamento', 100)->nullable()->after('provincia');
            $table->timestamp('fecha_sincronizacion_sunat')->nullable()->after('departamento')->comment('Última sincronización con padrón SUNAT');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn([
                'estado_contribuyente',
                'condicion_domicilio',
                'tipo_contribuyente',
                'direccion_fiscal',
                'ubigeo',
                'distrito',
                'provincia',
                'departamento',
                'fecha_sincronizacion_sunat',
            ]);
        });
    }
};
