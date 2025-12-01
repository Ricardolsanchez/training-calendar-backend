<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function () {
            // 👇 Aquí se registran las rutas API (con prefijo /api)
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            // 👇 Aquí se registran las rutas web (sin prefijo)
            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }
}
