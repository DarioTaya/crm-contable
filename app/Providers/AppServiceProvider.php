<?php

namespace App\Providers;

use App\Models\AlertaBuzon;
use App\Services\SunatBuzonSyncService;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SunatBuzonSyncService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $alertasPendientesCount = Schema::hasTable('alertas_buzon')
                ? AlertaBuzon::where('estado', 'Pendiente')->count()
                : 0;

            $view->with('alertasPendientesCount', $alertasPendientesCount);
        });
    }
}
