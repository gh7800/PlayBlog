<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiController
{
    /**
     * 成功返回
     */
    protected function success($data = null, string $message = '操作成功'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data  //empty($data) ? null : $data,
        ]);
    }

    protected function successPaginator($data = null, $paginator = null, string $message = '操作成功'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,  //empty($data) ? null : $data,
            'paginator' => [
                'current' => $paginator->currentPage(),
                'last' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'perPage' => $paginator->perPage(),
            ]
        ]);
    }

    /**
     * 失败返回
     */
    protected function error(string $message = '操作失败', $data = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => empty($data) ? null : $data,
        ], $code);
    }
}
