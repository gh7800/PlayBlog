<?php

use Module\Document\api\DocumentController;
use Module\Document\Flow\DocumentService;

Route::post('add',[DocumentController::class, 'store']);
Route::get('list',[DocumentController::class, 'index']);
Route::get('todo',[DocumentController::class, 'todo']);
Route::get('processed',[DocumentController::class, 'processed']);
Route::get('/{id}',[DocumentController::class, 'show']);
Route::put('update/{id}',[DocumentController::class, 'update']);
Route::post('delete/{id}',[DocumentController::class, 'destroy']);

Route::post('approval',[DocumentService::class, 'approval']);

