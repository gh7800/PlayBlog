<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\Request;

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

        BlogUser::insert([
            'username' => $username,
            'password' => $password,
            'real_name' => $username
        ]);

        return response()->json([
            'success' => true,
            'message' => '添加成功',
            'data' => $data
        ]);
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
