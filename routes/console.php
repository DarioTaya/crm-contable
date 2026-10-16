<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Programación de Tareas Automáticas (CRON)
|--------------------------------------------------------------------------
|
| Automatización del Buzón SOL de SUNAT. Se ejecuta cada hora y evalúa
| dinámicamente el intervalo (2h, 4h, 6h, 8h, 12h) configurado en Ajustes.
|
*/
Schedule::command('buzon:sync-sunat')->hourly();
Schedule::command('buzon:sync-sunafil')->hourlyAt(30);
Schedule::command('sunat:actualizar-estados')->weekly()->sundays()->at('03:00');
Schedule::command('sunat:sincronizar-tipo-cambio')
    ->everyFifteenMinutes()
    ->timezone('America/Lima')
    ->between('00:00', '09:00')
    ->withoutOverlapping();
Schedule::command('sunat:sincronizar-ssco')
    ->dailyAt('06:15')
    ->timezone('America/Lima')
    ->withoutOverlapping();
