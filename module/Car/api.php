<?php

use Module\Car\API\CarModelController;

Route::post('add',[CarModelController::class, 'store']);
