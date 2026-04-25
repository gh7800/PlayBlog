<?php

use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\OrganizationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 组织架构管理路由
|--------------------------------------------------------------------------
*/

// 公司管理路由
Route::prefix('company')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [CompanyController::class, 'index']);
    Route::get('/tree', [CompanyController::class, 'tree']);
    Route::post('/', [CompanyController::class, 'store']);
    Route::get('/{uuid}', [CompanyController::class, 'show']);
    Route::put('/{uuid}', [CompanyController::class, 'update']);
    Route::delete('/{uuid}', [CompanyController::class, 'destroy']);
});

// 部门管理路由
Route::prefix('department')->middleware('auth:sanctum')->group(function () {
    Route::get('/', [DepartmentController::class, 'index']);
    Route::get('/tree', [DepartmentController::class, 'tree']);
    Route::post('/', [DepartmentController::class, 'store']);
    Route::get('/{uuid}', [DepartmentController::class, 'show']);
    Route::put('/{uuid}', [DepartmentController::class, 'update']);
    Route::delete('/{uuid}', [DepartmentController::class, 'destroy']);
});

// 组织架构路由
Route::prefix('organization')->middleware('auth:sanctum')->group(function () {
    Route::get('/tree', [OrganizationController::class, 'tree']);
});