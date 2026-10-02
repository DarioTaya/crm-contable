<?php

namespace App\Providers;

use App\Models\AlertaBuzon;
use App\Models\QuickLink;
use App\Services\CronogramaSunatService;
use App\Services\Navegacion\SelectorEmpresa;
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
            $view->with(
                'alertasPendientesCount',
                AlertaBuzon::where('estado', 'Pendiente')->count(),
            );
            $view->with(
                'empresaActiva',
                app(SelectorEmpresa::class)->actual(request()),
            );
            $view->with(
                'quickLinks',
                Schema::hasTable('quick_links')
                    ? QuickLink::where('activo', true)->orderBy('orden')->orderBy('id')->get()
                    : collect(),
            );
            $view->with(
                'cronogramaBarra',
                Schema::hasTable('cronogramas_sunat')
                    ? app(CronogramaSunatService::class)->paraBarraSuperior()
                    : [
                        'anio' => (int) now()->year,
                        'pdt_621' => collect(),
                        'sire' => collect(),
                        'periodo_pdt' => now()->format('m-Y'),
                        'periodo_sire' => now()->format('m-Y'),
                    ],
            );
        });
    }
}
