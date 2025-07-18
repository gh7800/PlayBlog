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
class LoginController extends Controller
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
            $success = false;
            $error = $validator->errors()->first();
            $data1 = $input;
        } else {
            $blogUser = BlogUser::where('username',$input['username'])->first();

            //$str = md5(uniqid(md5(microtime(true)),true));
            //$token = sha1($str.$request['username']);
            $token = $blogUser->createToken('OA-token')->plainTextToken;

            if($blogUser){
                if(Hash::check($input['password'],$blogUser['password'])){//bcrypt 加密验证
                    $success = true;
                    $error = '登录成功';
                    $data1 = $blogUser;
                    $blogUser->token = $token; //设置token
                }else{
                    $success = false;
                    $error = '密码错误';
                    $data1 = $input;
                }

            }else{
                $success = false;
                $error = '账号不存在';
                $data1 = $input;
            }

        }

        return response()->json([
            'success' => $success,
            'message' => $error,
            'data' => $data1
        ]);
    }
}
