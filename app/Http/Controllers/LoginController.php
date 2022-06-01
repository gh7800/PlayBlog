<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->all();
        $pwd = $data['password'];

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

        $validator = Validator::make($data, $rules, $message);

        $success = true;
        $error = '';
        $data1 = null;

        if ($validator->fails()) {
            $success = false;
            $error = $validator->errors()->first();
            $data1 = $data;
        } else {
            $blogUser = BlogUser::where('username',$data['username'])->first();
            //echo $blogUser;

            if($blogUser){
                if($blogUser['password'] == $pwd){
                    $error = '登录成功';
                    $data1 = $blogUser;
                }else{
                    $error = '密码错误';
                    $data1 = $data;
                }

            }else{
                $error = '账号不存在';
                $data1 = $data;
            }

        }

        return response()->json([
            'success' => $success,
            'message' => $error,
            'data' => $data1
        ]);
    }
}
