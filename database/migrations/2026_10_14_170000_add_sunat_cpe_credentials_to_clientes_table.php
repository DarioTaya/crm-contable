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
            $table->string('sunat_cpe_client_id')->nullable()->after('sunat_client_secret');
            $table->text('sunat_cpe_client_secret')->nullable()->after('sunat_cpe_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['sunat_cpe_client_id', 'sunat_cpe_client_secret']);
        });
    }
};
