<?php

use Illuminate\Http\Request;
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

//Route::middleware('auth:api')->post('/login', function (Request $request) {
//    return [
//        'message'=>'ss'
//    ];
//});

Route::any('auth/login',[\App\Http\Controllers\LoginController::class,'login']);

Route::any('login','LoginController@login');

Route::get('home',function (){
    return [
        'message'=>'sds'
    ];
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1',['middleware'=>['cors']],function ($api){
    $api->post('api/login','LoginController@login');
    $api->get('home',function (){
        return [
            'message'=>'sds'
        ];
    });
});



