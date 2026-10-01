<?php

namespace App\Providers;

use App\Models\AlertaBuzon;
use App\Services\Navegacion\SelectorEmpresa;
use App\Services\SunatBuzonSyncService;
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
            $view->with(
                'alertasPendientesCount',
                AlertaBuzon::where('estado', 'Pendiente')->count(),
            );
            $view->with(
                'empresaActiva',
                app(SelectorEmpresa::class)->actual(request()),
            );
        });
    }
}
