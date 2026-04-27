<?php

namespace Module\Notice\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Notice\Services\NoticeService;

class NoticeController extends Controller
{
    private NoticeService $noticeService;

    public function __construct(NoticeService $noticeService)
    {
        $this->noticeService = $noticeService;
    }

    /**
     * 创建公告
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'receiver_uuids' => 'array',
            'receiver_uuids.*' => 'string',
            'files' => 'array',
            'files.*.file_url' => 'required|string',
            'files.*.file_name' => 'required|string',
            'files.*.file_size' => 'integer',
        ]);

        $notice = $this->noticeService->create($request);

        return response()->json([
            'success' => true,
            'message' => '发布成功',
            'data' => ['uuid' => $notice->uuid],
        ]);
    }

    /**
     * 公告列表
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->noticeService->list($request);

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * 公告详情
     */
    public function show(string $uuid): JsonResponse
    {
        $notice = $this->noticeService->detail($uuid);

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $notice,
        ]);
    }

    /**
     * 删除公告
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->noticeService->delete($uuid);

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }
}
