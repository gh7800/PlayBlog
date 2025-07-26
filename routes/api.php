<?php

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


//$api = app(Router::class);

//$api->version('v1',function ($api){
//    $api->post('user/add', [UserController::class, 'addUser']);
//    $api->post('user/delete', [UserController::class, 'deleteUser']);
//});

Route::prefix('user')
    ->middleware('auth:sanctum')
    ->group(function () {
    Route::post('/add', [UserController::class, 'addUser']);      // 对应 /api/user/add
    Route::post('/delete', [UserController::class, 'deleteUser']); // 对应 /api/user/delete
    Route::put('/update/{uuid}', [UserController::class, 'updateUser']); // 对应 /api/user/delete
});

Route::get('home',function (){
    return [
        'message'=>'sds'
    ];
});



//在 api 命名空间下的路由
//Route::namespace('admin')->group(function (){});

//为组中所有路由的 URI 加上 admin 前缀
//Route::prefix('api')->group(function (){});





