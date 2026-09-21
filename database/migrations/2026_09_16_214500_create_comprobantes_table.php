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
        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->cascadeOnDelete()
                ->comment('Cliente dueño del registro SIRE');

            $table->string('periodo', 7)->comment('Periodo tributario en formato YYYY-MM');
            $table->enum('tipo_registro', ['RCE', 'RVIE'])->comment('Tipo de registro: RCE (Compras) o RVIE (Ventas)');
            $table->string('tipo_comprobante', 10)->default('01')->comment('Tipo de comprobante: 01 Factura, 03 Boleta, etc.');
            $table->string('serie', 10)->nullable()->comment('Serie del comprobante');
            $table->string('correlativo', 20)->nullable()->comment('Número correlativo del comprobante');
            $table->string('ruc_tercero', 15)->nullable()->comment('RUC o documento del emisor o receptor tercero');
            $table->string('razon_social_tercero')->nullable()->comment('Razón social o nombre del tercero');
            $table->string('moneda', 10)->default('PEN')->comment('Moneda: PEN, USD, etc.');

            $table->decimal('base_imponible', 12, 2)->default(0)->comment('Monto base imponible o valor neto gravado');
            $table->decimal('igv', 12, 2)->default(0)->comment('Monto de IGV');
            $table->decimal('total', 12, 2)->default(0)->comment('Importe total del comprobante');

            $table->enum('estado_sire', ['Aceptado', 'Rechazado', 'Propuesta'])->default('Propuesta')->comment('Estado del comprobante en SIRE SUNAT');

            $table->string('ruta_xml')->nullable()->comment('Ruta relativa en storage del archivo XML');
            $table->string('ruta_pdf')->nullable()->comment('Ruta relativa en storage del archivo PDF');
            $table->string('ruta_cdr')->nullable()->comment('Ruta relativa en storage de la Constancia de Recepción CDR');

            $table->timestamps();

            // Índices para optimizar búsquedas y filtrados
            $table->index(['cliente_id', 'periodo']);
            $table->index(['cliente_id', 'tipo_registro']);
            $table->index(['periodo', 'tipo_registro']);
            $table->index('estado_sire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
    }
};
