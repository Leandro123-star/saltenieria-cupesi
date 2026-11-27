<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // El paginador por defecto de Laravel usa Tailwind (SVG con w-5 h-5);
        // como el layout no carga Tailwind, las flechas crecían sin límite.
        Paginator::defaultView('pagination.cupesi');
        Paginator::defaultSimpleView('pagination.cupesi-simple');

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
