<?php

use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionGroupController;
use Illuminate\Support\Facades\Route;

// 权限路由（前缀已在 ServiceProvider 中设为 api/permission）
Route::group([], function () {
    Route::get('/', [PermissionController::class, 'index']);
    Route::post('/', [PermissionController::class, 'store']);
    Route::put('/{uuid}', [PermissionController::class, 'update']);
    Route::delete('/{uuid}', [PermissionController::class, 'destroy']);
});

// 角色路由
Route::prefix('role')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::post('/', [RoleController::class, 'store']);
    Route::put('/{uuid}', [RoleController::class, 'update']);
    Route::delete('/{uuid}', [RoleController::class, 'destroy']);
    Route::post('/{uuid}/user', [RoleController::class, 'addUser']);
    Route::delete('/{uuid}/user/{userUuid}', [RoleController::class, 'removeUser']);
    Route::post('/{uuid}/permission', [RoleController::class, 'addPermission']);
    Route::delete('/{uuid}/permission/{permissionUuid}', [RoleController::class, 'removePermission']);
});

// 权限组路由
Route::prefix('group')->group(function () {
    Route::get('/', [PermissionGroupController::class, 'index']);
    Route::post('/', [PermissionGroupController::class, 'store']);
    Route::put('/{uuid}', [PermissionGroupController::class, 'update']);
    Route::delete('/{uuid}', [PermissionGroupController::class, 'destroy']);
    Route::post('/{uuid}/user', [PermissionGroupController::class, 'addUser']);
    Route::delete('/{uuid}/user/{userUuid}', [PermissionGroupController::class, 'removeUser']);
    Route::post('/{uuid}/permission', [PermissionGroupController::class, 'addPermission']);
    Route::delete('/{uuid}/permission/{permissionUuid}', [PermissionGroupController::class, 'removePermission']);
});
