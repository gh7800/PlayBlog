<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use Exception;
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
        $username = $request->input('username');

        $userExists = BlogUser::where('username', $username)->exists();

        if ($userExists) {
            return response()->json([
                'success' => false,
                'message' => 'username已存在1',
                'data' => [
                    'username' => $username
                ]
            ]);
        }

        $data = [
            'username' => $username,
            'password' => $request->input('password'),
            'real_name' => $request->input('username'),
        ];

        try {
            BlogUser::create($data);

            return response()->json([
                'success' => true,
                'message' => '添加成功',
                'data' => $data
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $data
            ]);
        }
    }

    //编辑
    public function updateUser(Request $request){

    }

    //删除单个
    public function deleteUser(Request $request): JsonResponse
    {
        $username = $request->input('username');

        $users = BlogUser::where('username', $username);

        if ($users->exists()) {
            try {
                $users->delete();
                return response()->json([
                    'success' => true,
                    'message' => '删除成功',
                    'data' => [
                        'username' => $username
                    ]
                ]);

            } catch (Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => '删除失败_' . $e->getMessage(),
                    'data' => [
                        'username' => $username
                    ]
                ]);
            }
        } else {
            return response()->json([
                'success' => true,
                'message' => '账号不存在',
                'data' => [
                    'username' => $username
                ]
            ]);
        }
    }

}
