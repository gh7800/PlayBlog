<?php

use App\Http\Controllers\AiController;
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

// AI 助理：流式对话（SSE）。前端会带 Bearer token；如需强制登录可加 ->middleware('auth:sanctum')
Route::post('/ai/chat', [AiController::class, 'chat']);

// AI 助理：对话记录（需登录，按 user_uuid 隔离）
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/ai/conversations', [AiController::class, 'listConversations']);          // 我的对话列表（分页）
    Route::post('/ai/conversations', [AiController::class, 'storeConversation']);         // 新建对话
    Route::get('/ai/conversations/{id}', [AiController::class, 'showConversation']);      // 对话详情（回载）
    Route::put('/ai/conversations/{id}', [AiController::class, 'updateConversation']);    // 更新对话
});

require __DIR__ . '/organization.php';

//为组中所有路由的 URI 加上 admin 前缀
//Route::prefix('api')->group(function (){});





