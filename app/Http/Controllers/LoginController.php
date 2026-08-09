<?php

namespace App\Http\Controllers;

use App\Helpers\DeviceHelper;
use App\Models\BlogUser;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Login 控制器
 */
class LoginController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'bail|required|between:4,16|alpha_num',
            'password' => 'bail|required|between:4,16|alpha_num',
        ], [
            'username.between' => '用户名长度4-16位',
            'username.required' => '请输入用户名',
            'username.alpha_num' => '只能输入字母、数字',
            'password.between' => '密码长度4-16位',
            'password.required' => '请输入密码',
            'password.alpha_num' => '只能输入字母、数字',
        ]);

        $blogUser = BlogUser::where('username', $validated['username'])->first();

        if (!$blogUser) {
            return $this->error('用户不存在');
        }

        if (!Hash::check($validated['password'], $blogUser->password)) {
            return $this->error('密码错误');
        }

        // 判断设备类型
        $deviceType = DeviceHelper::getDeviceType($request->userAgent());

        // 撤销同一设备的旧 token（可选，防止同一设备重复登录）
        $blogUser->tokens()->where('name', $deviceType)->delete();

        // 创建新 token，名称为设备类型
        $token = $blogUser->createToken($deviceType)->plainTextToken;

        return $this->success(array_merge($blogUser->toArray(), [
            'token' => $token,
            'device' => $deviceType,
            'permissions' => PermissionService::getUserPermissionCodes($blogUser->uuid),
        ]));
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'bail|required|between:4,16|alpha_num',
            'password' => 'bail|required|between:4,16|alpha_num',
        ], [
            'username.between' => '用户名长度4-16位',
            'username.required' => '请输入用户名',
            'username.alpha_num' => '只能输入字母、数字',
            'password.between' => '密码长度4-16位',
            'password.required' => '请输入密码',
            'password.alpha_num' => '只能输入字母、数字',
        ]);

        $username = $validated['username'];

        // 用户名唯一性（含软删除，与 addUser 一致）
        if (BlogUser::withTrashed()->where('username', $username)->exists()) {
            return $this->error('用户名已存在', ['username' => $username]);
        }

        // 创建用户，组织归属留空由管理员后台分配
        $user = BlogUser::create([
            'username' => $username,
            'password' => Hash::make($validated['password']),
            'real_name' => $request->input('real_name', $username),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'status' => 1,
        ]);

        // 注册即自动登录：复用 login 的设备判断 + token 生成
        $deviceType = DeviceHelper::getDeviceType($request->userAgent());
        $token = $user->createToken($deviceType)->plainTextToken;

        return $this->success(array_merge($user->fresh()->toArray(), [
            'token' => $token,
            'device' => $deviceType,
            'permissions' => PermissionService::getUserPermissionCodes($user->uuid),
        ]));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, '登出成功');
    }
}
