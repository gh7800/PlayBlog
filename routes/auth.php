<?php

use Module\Car\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

//不需要登录验证的

//Route::post('api/login','LoginController@login');

Route::post('login',[LoginController::class,'login']);
