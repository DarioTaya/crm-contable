<?php

namespace Database\Seeders;

use App\Models\AjusteSistema;
use Illuminate\Database\Seeder;

class AjusteSistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        AjusteSistema::getSettings();
    }
}
