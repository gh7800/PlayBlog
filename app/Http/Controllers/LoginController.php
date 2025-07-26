<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Login 控制器
 */
class LoginController extends ApiController
{
    public function login(Request $request): JsonResponse
    {
        $input = $request->all();

        $rules = [
            'username' => 'bail|required|between:4,16|alpha_num',//bail 在第一次验证失败后停止运行验证规则
            'password' => 'bail|required|between:4,16|alpha_num',
        ];
        $message = [
            'username.between' => '用户名长度4-16位',
            'username.required' => '请输入用户名',
            'username.alpha_num' => '只能输入字母、数字',
            'password.between' => '密码长度4-16位',
            'password.required' => '请输入密码',
            'password.alpha_num' => '只能输入字母、数字',
        ];

        $validator = Validator::make($input, $rules, $message);
//        $validator = $request->validate($rules, $message);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(),$input);
        } else {
            try {
                $blogUser = BlogUser::where('username', $input['username'])->firstOrFail();
                $token = $blogUser->createToken('OA-token')->plainTextToken;
                $blogUser->token = $token;

                if (Hash::check($input['password'], $blogUser['password'])) { //bcrypt 加密验证
                    return $this->success($blogUser);
                } else {
                    return $this->error('密码错误',$input);
                }

            } catch (\Exception $e) {
                return $this->error($e->getMessage(),$input);
            }

        }

    }
}
