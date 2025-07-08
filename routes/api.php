<?php

use App\Http\Controllers\LoginController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

$api = app('Dingo\Api\Routing\Router');

//Route::post('auth/login',[LoginController::class,'login']);

Route::post('user/addUser',[UserController::class,'addUser']);

Route::post('user/deleteUser',[UserController::class,'deleteUser']);

Route::get('home',function (){
    return [
        'message'=>'sds'
    ];
});

//在 api 命名空间下的路由
Route::namespace('admin')->group(function (){

});

//为组中所有路由的 URI 加上 admin 前缀
Route::prefix('api')->group(function (){

});





