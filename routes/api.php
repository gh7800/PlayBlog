<?php

use App\Http\Controllers\LoginController;
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

$api->version('v1',function ($api){
    $api->post('','LoginController@login');
});

Route::any('auth/login',[LoginController::class,'login']);

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






