<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Route;

class PermissionServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
    }

    public function registerRoutes()
    {
        Route::group([
            'namespace' => 'App\Http\Controllers\Api',
            'prefix' => 'api/permission',
            'middleware' => 'auth:sanctum',
        ], function () {
            $this->loadRoutesFrom(base_path('routes/permission.php'));
        });
    }
}
