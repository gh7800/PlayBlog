<?php

namespace Module\Notice\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Notice\Models\Notice;
use Module\Notice\Models\NoticeFile;
use Module\Notice\Models\NoticeReceiver;

class NoticeService
{
    /**
     * 创建公告
     */
    public function create(Request $request): Notice
    {
        $user = Auth::user();

        $notice = Notice::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'sender_uuid' => $user->uuid,
            'sender_name' => $user->real_name ?? '',
        ]);

        // 保存接收人员
        $receiverUuids = $request->input('receiver_uuids', []);
        foreach ($receiverUuids as $receiverUuid) {
            $notice->receivers()->create([
                'user_uuid' => $receiverUuid,
            ]);
        }

        // 保存附件
        $files = $request->input('files', []);
        foreach ($files as $file) {
            $notice->files()->create([
                'file_url' => $file['file_url'] ?? '',
                'file_name' => $file['file_name'] ?? '',
                'file_size' => $file['file_size'] ?? 0,
            ]);
        }

        return $notice->load(['receivers', 'files']);
    }

    /**
     * 公告列表
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $paginator = Notice::where('is_deleted', 0)
            ->whereHas('receivers', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['sender'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * 公告详情
     */
    public function detail(string $uuid)
    {
        $user = Auth::user();

        return Notice::where('uuid', $uuid)
            ->where('is_deleted', 0)
            ->whereHas('receivers', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['receivers', 'files', 'sender'])
            ->firstOrFail();
    }

    /**
     * 删除公告
     */
    public function delete(string $uuid): bool
    {
        $user = Auth::user();

        $notice = Notice::where('uuid', $uuid)
            ->where('sender_uuid', $user->uuid)
            ->where('is_deleted', 0)
            ->firstOrFail();

        return $notice->update(['is_deleted' => 1]);
    }
}