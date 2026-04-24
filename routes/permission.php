<?php

use App\Http\Controllers\Api\PermissionGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('group')->group(function () {
    Route::get('/', [PermissionGroupController::class, 'index']);
    Route::post('/', [PermissionGroupController::class, 'store']);
    Route::put('/{uuid}', [PermissionGroupController::class, 'update']);
    Route::delete('/{uuid}', [PermissionGroupController::class, 'destroy']);
    Route::post('/{uuid}/user', [PermissionGroupController::class, 'addUser']);
    Route::delete('/{uuid}/user/{userUuid}', [PermissionGroupController::class, 'removeUser']);
    Route::post('/{uuid}/permission', [PermissionGroupController::class, 'addPermission']);
    Route::delete('/{uuid}/permission/{code}', [PermissionGroupController::class, 'removePermission']);
});
