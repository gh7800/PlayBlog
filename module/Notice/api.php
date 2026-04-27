<?php

use Module\Notice\API\NoticeController;

Route::post('/', [NoticeController::class, 'store']);
Route::get('/', [NoticeController::class, 'index']);
Route::get('/{uuid}', [NoticeController::class, 'show']);
Route::delete('/{uuid}', [NoticeController::class, 'destroy']);
