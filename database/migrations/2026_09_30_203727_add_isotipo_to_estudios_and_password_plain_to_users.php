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
        Schema::table('estudios', function (Blueprint $table) {
            if (! Schema::hasColumn('estudios', 'isotipo_path')) {
                $table->string('isotipo_path')->nullable()->after('logo_path');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'password_plain')) {
                $table->text('password_plain')->nullable()->after('password');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            if (Schema::hasColumn('estudios', 'isotipo_path')) {
                $table->dropColumn('isotipo_path');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'password_plain')) {
                $table->dropColumn('password_plain');
            }
        });
    }
};
