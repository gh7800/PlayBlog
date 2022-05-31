<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class LoginController extends Controller
{
    public function login(Request $request): JsonResponse
    {
//        $name = $request->all();

        return response()->json([
            'success' => true,
            'message' => '登录成功',
            'data' => null
        ]);
    }
}
