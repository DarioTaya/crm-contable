<?php

namespace App\Providers;

use App\Models\AlertaBuzon;
use App\Models\Estudio;
use App\Models\QuickLink;
use App\Services\Catalogos\CatalogoSunat;
use App\Services\CronogramaSunatService;
use App\Services\Navegacion\SelectorEmpresa;
use App\Services\SunatBuzonSyncService;
use App\Services\TipoCambioSunatService;
use App\Support\IdentidadMarca;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
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
        CatalogoSunat::olvidar();

        if (request()->isSecure() || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

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
                $this->obtenerQuickLinks(),
            );
            $view->with(
                'cronogramaBarra',
                $this->obtenerCronogramaBarra(),
            );
            $view->with(
                'tipoCambioBarra',
                $this->obtenerTipoCambioBarra(),
            );
        });
    }

    /**
     * Entrega los datos básicos del tipo de cambio para la barra superior sin sobrecargar el HTML.
     *
     * @return array{anio: int, mes: int, vigente: ?array}
     */
    private function obtenerTipoCambioBarra(): array
    {
        try {
            $hoy = now('America/Lima');

            return [
                'anio' => $hoy->year,
                'mes' => $hoy->month,
                'vigente' => app(TipoCambioSunatService::class)->vigenteAlmacenado(),
            ];
        } catch (\Throwable) {
            $hoy = now('America/Lima');

            return [
                'anio' => (int) $hoy->year,
                'mes' => (int) $hoy->month,
                'vigente' => null,
            ];
        }
    }

    /**
     * Entrega los enlaces rápidos oficiales activos con fallback seguro.
     */
    private function obtenerQuickLinks(): Collection
    {
        try {
            return QuickLink::query()->where('activo', true)->orderBy('orden')->orderBy('id')->get();
        } catch (\Throwable) {
            return collect();
        }
    }

    /**
     * Entrega el cronograma activo para la barra superior con fallback seguro si no hay tablas migradas.
     */
    private function obtenerCronogramaBarra(): array
    {
        try {
            return app(CronogramaSunatService::class)->paraBarraSuperior();
        } catch (\Throwable) {
            return [
                'anio' => (int) now()->year,
                'sire' => collect(),
                'pdt_621' => collect(),
                'balance_mensual' => collect(),
                'balance_anual' => collect(),
                'periodo_sire' => now()->format('m-Y'),
                'periodo_pdt' => now()->format('m-Y'),
                'periodo_balance_mensual' => now()->format('m-Y'),
                'periodo_balance_anual' => now()->format('m-Y'),
            ];
        }
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

        if ($estudio === null) {
            try {
                $estudio = Estudio::query()->orderBy('id')->first();
            } catch (\Throwable) {
                $estudio = null;
            }
        }

        $view->with('identidadMarca', IdentidadMarca::desdeEstudio($estudio));
    }
}
