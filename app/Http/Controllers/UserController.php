<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\Request;
use function PHPUnit\Framework\isEmpty;

/**
 *User控制器
 */
class UserController extends Controller
{
    //添加user
    public function addUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();

        $username = $data['username'];
        $password = $data['password'];

        $user = BlogUser::where('username',$username)->get();
        if(!$user->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'username已存在',
                'data' => $data
            ]);
        }

        $posts = (new BlogUser)->save([
            'username' => $username,
            'password' => $password,
            'real_name' => $username
        ]);

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
    public function deleteUser(Request $request): \Illuminate\Http\JsonResponse
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
