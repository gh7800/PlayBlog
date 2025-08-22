<?php

use Module\Document\api\DocumentController;
use Module\Document\Flow\DocumentService;

Route::post('add',[DocumentController::class, 'store']);
Route::get('list',[DocumentController::class, 'index']);
Route::get('todo',[DocumentController::class, 'todo']);
Route::post('approval',[DocumentService::class, 'approval']);

