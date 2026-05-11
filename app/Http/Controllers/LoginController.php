<?php

namespace App\Http\Controllers;

use App\Helpers\DeviceHelper;
use App\Models\BlogUser;
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
        ]));
    }
}
