<?php

namespace Module\Car;

use Illuminate\Support\ServiceProvider;
use Route;

class CarServiceProvider extends ServiceProvider
{
    function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
    }

    function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/api.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'namespace' => 'Module\Car\API',
            'prefix' => 'api/car',
            'middleware' => 'auth:sanctum',
        ];
    }

    protected function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/DB/Migrations');
    }
}
