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





