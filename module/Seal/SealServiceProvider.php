<?php

namespace Module\Seal;

use Illuminate\Support\ServiceProvider;

class SealServiceProvider extends ServiceProvider
{
    function boot()
    {
        $this->registerRoutes();
    }

    function registerRoutes()
    {

    }
}
