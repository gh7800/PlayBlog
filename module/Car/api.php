<?php

use Module\Car\API\CarApplyController;
use Module\Car\API\CarApproveController;
use Module\Car\API\CarEndController;
use Module\Car\API\CarPlateController;

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
