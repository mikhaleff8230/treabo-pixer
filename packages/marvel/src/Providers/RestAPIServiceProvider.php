<?php

namespace Marvel\Providers;

use Illuminate\Support\ServiceProvider;
use Marvel\Console\Commands\FillGeoFromShop;

class RestApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutes();
        $this->registerCommands();
    }

    public function loadRoutes(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../Rest/Routes.php');
    }

    protected function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                FillGeoFromShop::class,
            ]);
        }
    }
}
