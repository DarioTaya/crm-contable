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
            $table->unsignedInteger('numero')->nullable()->unique()->after('id');
        });

        // Asignar numeración correlativa inicial a clientes existentes ordenados por fecha/id de creación
        $clientes = DB::table('clientes')->orderBy('id')->get();
        $num = 1;
        foreach ($clientes as $c) {
            DB::table('clientes')->where('id', $c->id)->update(['numero' => $num++]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('numero');
        });
    }
};
