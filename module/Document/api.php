<?php

use Module\Document\api\DocumentController;

Route::post('add',[DocumentController::class, 'store']);
Route::get('list',[DocumentController::class, 'index']);

