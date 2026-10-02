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
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            // Retenciones y Percepciones del IGV con saldos no aplicados de arrastre (PDT 621)
            $table->decimal('igv_retenciones_saldo_anterior', 12, 2)->default(0)->after('igv_retenciones');
            $table->decimal('igv_retenciones_saldo_siguiente', 12, 2)->default(0)->after('igv_retenciones_saldo_anterior');
            $table->decimal('igv_percepciones_saldo_anterior', 12, 2)->default(0)->after('igv_percepciones');
            $table->decimal('igv_percepciones_saldo_siguiente', 12, 2)->default(0)->after('igv_percepciones_saldo_anterior');

            // Saldo a favor de Renta remanente para meses siguientes (Casilla 109 remanente)
            $table->decimal('renta_saldo_favor_siguiente', 12, 2)->default(0)->after('renta_resultante_pagar');

            // Hoja de determinación de Planilla / PLAME (PDT 601)
            $table->decimal('plame_essalud_base', 12, 2)->default(0)->after('renta_saldo_favor_siguiente');
            $table->decimal('plame_essalud_a_pagar', 12, 2)->default(0)->after('plame_essalud_base');
            $table->decimal('plame_onp_a_pagar', 12, 2)->default(0)->after('plame_essalud_a_pagar');
            $table->decimal('plame_renta_4ta_base', 12, 2)->default(0)->after('plame_onp_a_pagar');
            $table->decimal('plame_renta_4ta_a_pagar', 12, 2)->default(0)->after('plame_renta_4ta_base');
            $table->decimal('plame_renta_5ta_a_pagar', 12, 2)->default(0)->after('plame_renta_4ta_a_pagar');
            $table->decimal('plame_afp_a_pagar', 12, 2)->default(0)->after('plame_renta_5ta_a_pagar');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liquidaciones_periodo', function (Blueprint $table) {
            $table->dropColumn([
                'igv_retenciones_saldo_anterior',
                'igv_retenciones_saldo_siguiente',
                'igv_percepciones_saldo_anterior',
                'igv_percepciones_saldo_siguiente',
                'renta_saldo_favor_siguiente',
                'plame_essalud_base',
                'plame_essalud_a_pagar',
                'plame_onp_a_pagar',
                'plame_renta_4ta_base',
                'plame_renta_4ta_a_pagar',
                'plame_renta_5ta_a_pagar',
                'plame_afp_a_pagar',
            ]);
        });
    }
};
