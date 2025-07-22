<?php

use Module\Document\api\DocumentAddController;
use Module\Document\api\DocumentController;

Route::post('add',[DocumentController::class, 'store']);
Route::get('list',[DocumentController::class, 'index']);

/*Route::prefix('document')->group(function(){
    Route::post('/add',[DocumentAddController::class, 'addDocument']);
    Route::get('/list', [DocumentAddController::class, 'list']);
    Route::delete('/delete/{uuid}', [DocumentAddController::class, 'delete']);
});*/
