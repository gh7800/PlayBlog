<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request){
        $name = $request->all();

        return Response()->json([
            'success'=>true,
            'message'=>'',
            'data'=>null
        ]);
    }
}
