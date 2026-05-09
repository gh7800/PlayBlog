<?php

use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionGroupController;
use Illuminate\Support\Facades\Route;

// 角色路由
Route::prefix('role')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{uuid}', [RoleController::class, 'update']);
    Route::delete('/{uuid}', [RoleController::class, 'destroy']);
    Route::post('/{uuid}/user', [RoleController::class, 'addUser']);
    Route::delete('/{uuid}/user/{userUuid}', [RoleController::class, 'removeUser']);
    Route::post('/{uuid}/permission', [RoleController::class, 'addPermission']);
    Route::delete('/{uuid}/permission/{code}', [RoleController::class, 'removePermission']);
});

// 权限组路由
Route::prefix('group')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [PermissionGroupController::class, 'index']);
    Route::post('/', [PermissionGroupController::class, 'store']);
    Route::put('/{uuid}', [PermissionGroupController::class, 'update']);
    Route::delete('/{uuid}', [PermissionGroupController::class, 'destroy']);
    Route::post('/{uuid}/user', [PermissionGroupController::class, 'addUser']);
    Route::delete('/{uuid}/user/{userUuid}', [PermissionGroupController::class, 'removeUser']);
    Route::post('/{uuid}/permission', [PermissionGroupController::class, 'addPermission']);
    Route::delete('/{uuid}/permission/{code}', [PermissionGroupController::class, 'removePermission']);
});
