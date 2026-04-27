<?php

namespace Module\Notice;

use Illuminate\Support\ServiceProvider;
use Route;

class NoticeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
    }

    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/api.php');
        });
    }

    private function routeConfiguration(): array
    {
        return [
            'namespace' => 'Module\\Notice\\API',
            'prefix' => 'api/notice',
            'middleware' => 'auth:sanctum',
        ];
    }

    private function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/DB/Migrations');
    }
}
