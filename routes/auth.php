<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

//不需要登录验证的

//Route::post('api/login','LoginController@login');

Route::post('login', [LoginController::class, 'login']);
Route::post('register', [LoginController::class, 'register']);
Route::middleware('auth:sanctum')->post('logout', [LoginController::class, 'logout']);
