<?php

namespace App\Providers;

use App\Models\AlertaBuzon;
use App\Models\Estudio;
use App\Models\QuickLink;
use App\Services\CronogramaSunatService;
use App\Services\Navegacion\SelectorEmpresa;
use App\Services\SunatBuzonSyncService;
use App\Support\IdentidadMarca;
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
        View::composer('components.marca.variables', function ($view) {
            $this->compartirIdentidadMarca($view);
        });

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
                        'sire' => collect(),
                        'pdt_621' => collect(),
                        'balance_mensual' => collect(),
                        'balance_anual' => collect(),
                        'periodo_sire' => now()->format('m-Y'),
                        'periodo_pdt' => now()->format('m-Y'),
                        'periodo_balance_mensual' => now()->format('m-Y'),
                        'periodo_balance_anual' => now()->format('m-Y'),
                    ],
            );
        });
    }

    /**
     * Publica la paleta del estudio para que el layout pinte la marca sin consultar la base desde Blade.
     */
    private function compartirIdentidadMarca(\Illuminate\View\View $view): void
    {
        if (array_key_exists('identidadMarca', $view->getData())) {
            return;
        }

        $estudio = auth()->user()?->estudio;

        if ($estudio === null && Schema::hasTable('estudios')) {
            $estudio = Estudio::query()->orderBy('id')->first();
        }

        $view->with('identidadMarca', IdentidadMarca::desdeEstudio($estudio));
    }
}
