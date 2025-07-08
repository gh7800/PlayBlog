<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 *User控制器
 */
class UserController extends Controller
{
    //添加user
    public function addUser(Request $request): JsonResponse
    {
        //$data = $request->all();

        //dd($data);

        $username = $request->input('username');
       // $password = $data['password'];

        $userExists = BlogUser::where('username',$username)->exists();

        if($userExists) {
            return response()->json([
                'success' => false,
                'message' => 'username已存在1',
                'data' => null
            ]);
        }

        $data = [
            'username' => $username,
            'password' => $request->input('password'),
            'real_name' => $request->input('username'),
        ];

        $posts = BlogUser::create($data);

        //$posts = (new BlogUser($data))->save();

        if($posts) {
            return response()->json([
                'success' => true,
                'message' => '添加成功',
                'data' => $data
            ]);
        }else {
            return response()->json([
                'success' => false,
                'message' => $php_errormsg,
                'data' => $data
            ]);
        }
    }

    //删除单个
    public function deleteUser(Request $request): JsonResponse
    {
        $username = $request['username'];

        $users = BlogUser::where('username', $username);

        if ($users->delete()) {
            return response()->json([
                'success' => true,
                'message' => '删除成功',
                'data' => null
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => '删除失败',
                'data' => null
            ]);
        }
    }


}
