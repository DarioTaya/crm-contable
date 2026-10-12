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
        Schema::create('movimientos_detracciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->string('tipo', 10)->comment('deposito o retiro');
            $table->date('fecha')->index();
            $table->string('periodo', 7)->nullable()->index()->comment('Formato MM-YYYY');
            $table->decimal('monto', 12, 2);
            $table->string('descripcion', 255);
            $table->string('numero_operacion', 50)->nullable()->index();
            $table->string('numero_constancia', 50)->nullable()->index();
            $table->string('comprobante_referencia', 50)->nullable()->index();
            $table->string('adquiriente_ruc', 11)->nullable()->index();
            $table->string('adquiriente_razon_social', 255)->nullable();
            $table->string('tipo_bien', 150)->nullable();
            $table->string('origen', 30)->default('manual')->comment('constancia_pdf, control_impuestos, manual');
            $table->foreignId('abono_deuda_id')->nullable()->constrained('abonos_deudas')->cascadeOnDelete();
            $table->json('metadatos')->nullable();
            $table->timestamps();

            $table->index(['cliente_id', 'fecha']);
            $table->index(['cliente_id', 'tipo']);
        });

        Schema::create('saldos_detracciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudio_id')->constrained('estudios')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->decimal('saldo_inicial', 12, 2)->default(0.00);
            $table->string('observacion', 255)->nullable();
            $table->timestamps();

            $table->unique(['cliente_id', 'anio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saldos_detracciones');
        Schema::dropIfExists('movimientos_detracciones');
    }
};
