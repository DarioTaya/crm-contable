<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('clientes')
            ->where('regimen_tributario', 'MYPE')
            ->update(['regimen_tributario' => 'RMT']);

        DB::table('clientes')
            ->where('regimen_tributario', 'General')
            ->update(['regimen_tributario' => 'RG']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('clientes')
            ->where('regimen_tributario', 'RMT')
            ->update(['regimen_tributario' => 'MYPE']);

        DB::table('clientes')
            ->where('regimen_tributario', 'RG')
            ->update(['regimen_tributario' => 'General']);
    }
};
