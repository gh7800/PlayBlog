<?php

namespace Module\Document;

use Illuminate\Support\ServiceProvider;
use Route;

class DocumentServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any package services.
     */
    function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
    }

    /**
     * Register the package routes.
     */
    function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/api.php');
        });

    }

    /**
     * Get the Nova route group configuration array.
     */
    protected function routeConfiguration(): array
    {
        return [
            'namespace' => 'Module\document\api',
            'prefix' => 'api/document',
            'middleware' => 'auth:sanctum',
        ];
    }

    /**
     * Register the package resources.
     */
    protected function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/db/Migrations');
    }

}
