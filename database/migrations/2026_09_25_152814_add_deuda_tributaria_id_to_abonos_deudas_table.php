<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada abono queda ligado al tributo que amortiza.
     * Las deudas ya guardadas en la operación se copian como primer tributo.
     */
    public function up(): void
    {
        Schema::table('abonos_deudas', function (Blueprint $table) {
            $table->foreignId('deuda_tributaria_id')
                ->nullable()
                ->after('operacion_id')
                ->constrained('deuda_tributarias')
                ->nullOnDelete();
        });

        $operaciones = DB::table('operaciones')
            ->where(function ($query) {
                $query->where('tiene_deuda', true)
                    ->orWhere('monto_deuda_inicial', '>', 0);
            })
            ->orderBy('id')
            ->get();

        foreach ($operaciones as $operacion) {
            $codigo = $operacion->tributo_codigo ?: '1011';
            $yaExiste = DB::table('deuda_tributarias')
                ->where('operacion_id', $operacion->id)
                ->where('tributo_codigo', $codigo)
                ->exists();

            if ($yaExiste || empty($operacion->estudio_id)) {
                continue;
            }

            $deudaId = DB::table('deuda_tributarias')->insertGetId([
                'estudio_id' => $operacion->estudio_id,
                'operacion_id' => $operacion->id,
                'tributo_codigo' => $codigo,
                'es_igv_justo' => (bool) ($operacion->es_igv_justo ?? false),
                'monto_deuda_inicial' => $operacion->monto_deuda_inicial ?? 0,
                'vencimiento_deuda' => $operacion->vencimiento_deuda,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('abonos_deudas')
                ->where('operacion_id', $operacion->id)
                ->whereNull('deuda_tributaria_id')
                ->update(['deuda_tributaria_id' => $deudaId]);
        }
    }

    public function down(): void
    {
        Schema::table('abonos_deudas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('deuda_tributaria_id');
        });
    }
};
