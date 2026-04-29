<?php

use App\Http\Controllers\FileController;
use App\Http\Controllers\UserController;
use App\Services\JPush\PushController;
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
        Route::get('/info', [UserController::class, 'getUserInfo']); // 获取个人信息
        Route::get('/list', [UserController::class, 'getUserList']); // 获取用户列表
        Route::get('/list-by-department', [UserController::class, 'getUserListByDepartment']); // 按部门获取用户列表
        Route::post('/add', [UserController::class, 'addUser']);      // 对应 /api/user/add
        Route::post('/delete', [UserController::class, 'deleteUser']); // 对应 /api/user/delete
        Route::put('/update/{uuid}', [UserController::class, 'updateUser']); // 对应 /api/user/delete
        Route::post('/push-id', [UserController::class, 'setPushId']); // 设置推送Id
    });

Route::post('/upload', [FileController::class, 'upload']);

Route::get('home', function () {
    return [
        'message' => 'sds'
    ];
});

Route::post('/push', [PushController::class, 'sendPush']);

require __DIR__ . '/organization.php';
require __DIR__ . '/permission.php';
//Route::namespace('admin')->group(function (){});

//为组中所有路由的 URI 加上 admin 前缀
//Route::prefix('api')->group(function (){});





