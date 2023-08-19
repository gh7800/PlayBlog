<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Illuminate\Http\Request;

/**
 *User控制器
 */
class UserController extends Controller
{
    public function addUser(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();

        $username = $data['username'];
        $password = $data['password'];

        $result = BlogUser::insert([
            'username' => $username,
            'password' =>$password,
            'real_name' =>$username
        ]);

        //echo $request;

        return response() -> json([
            'success' => true,
            'message' => '添加成功',
            'data' => $data
        ]);
    }


}
