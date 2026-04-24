<?php

use Module\Car\API\CarApplyController;
use Module\Car\API\CarApproveController;
use Module\Car\API\CarEndController;
use Module\Car\API\CarPlateController;
use Module\Car\API\PermissionGroupController;

// 用车申请
Route::post('apply', [CarApplyController::class, 'store']);
Route::get('apply', [CarApplyController::class, 'index']);
Route::get('apply/{uuid}', [CarApplyController::class, 'show']);
Route::delete('apply/{uuid}', [CarApplyController::class, 'destroy']);

// 用车审批
Route::post('approve', [CarApproveController::class, 'approve']);
Route::get('approve/todo', [CarApproveController::class, 'todo']);
Route::get('approve/done', [CarApproveController::class, 'processed']);
Route::get('approve/plates', [CarApproveController::class, 'plates']);

// 结束用车
Route::post('end/{uuid}', [CarEndController::class, 'end']);

// 车牌管理
Route::get('plate', [CarPlateController::class, 'index']);
Route::post('plate', [CarPlateController::class, 'store']);
Route::put('plate/{uuid}', [CarPlateController::class, 'update']);
Route::delete('plate/{uuid}', [CarPlateController::class, 'destroy']);

// 权限组管理
Route::prefix('permission')->group(function () {
    Route::get('group', [PermissionGroupController::class, 'index']);
    Route::post('group', [PermissionGroupController::class, 'store']);
    Route::put('group/{uuid}', [PermissionGroupController::class, 'update']);
    Route::delete('group/{uuid}', [PermissionGroupController::class, 'destroy']);
    Route::post('group/{uuid}/user', [PermissionGroupController::class, 'addUser']);
    Route::delete('group/{uuid}/user/{userUuid}', [PermissionGroupController::class, 'removeUser']);
    Route::post('group/{uuid}/permission', [PermissionGroupController::class, 'addPermission']);
    Route::delete('group/{uuid}/permission/{code}', [PermissionGroupController::class, 'removePermission']);
});
