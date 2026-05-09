<?php

namespace Module\Car\Services;

use App\Models\BlogUser;
use App\Models\Next;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Module\Car\Enums\CarStatus;
use Module\Car\Models\CarApplication;
use Module\Car\Models\CarPlate;
use Module\Car\Services\CarApprovalService;

class CarService
{
    /**
     * 申请用车
     */
    public function apply(Request $request): CarApplication
    {
        $user = $request->user();

        $data = [
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'car_type' => $request->input('car_type'),
            'reason' => $request->input('reason'),
            'passenger_count' => $request->input('passenger_count'),
            'use_time' => $request->input('use_time'),
            'remark' => $request->input('remark', ''),
            'status' => CarStatus::APPLYING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            'step' => 1,
        ];

        $application = CarApplication::create($data);

        // 创建待办给当前节点审批人
        $this->createApproverTaskByNode($application, $user);

        // 创建下一步操作记录
        $application->next()->delete();
        $application->next()->createMany([
            ['step' => 1, 'text' => '驳回'],
            ['step' => 2, 'text' => '同意'],
        ]);

        // 记录日志
        $application->logs()->create([
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'status' => CarStatus::APPLYING,
            'status_title' => '提交申请',
            'result' => 1,
            'step' => 1,
        ]);

        return $application->load(['logs', 'taskLogs', 'next']);
    }

    /**
     * 根据当前审批节点创建审批待办
     */
    private function createApproverTaskByNode(CarApplication $application, $applicant)
    {
        $approverService = new CarApprovalService();
        $node = $approverService->getCurrentNode($application);

        if (!$node) {
            Log::warning('createApproverTaskByNode: no active node found', [
                'application_id' => $application->id,
            ]);
            return;
        }

        $approverUuids = $node->getApproverUuids();

        foreach ($approverUuids as $userUuid) {
            $user = BlogUser::where('uuid', $userUuid)->first();
            $application->taskLogs()->create([
                'user_uuid' => $userUuid,
                'user_name' => $user->real_name ?? '',
                'status' => CarStatus::APPLYING,
                'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            ]);
        }
    }

    /**
     * 审批用车
     */
    public function approve(Request $request): CarApplication
    {
        $user = $request->user();
        $uuid = $request->input('uuid');
        $action = $request->input('action'); // agree, reject
        $plateId = $request->input('plate_id');
        $reply = $request->input('reply', '');

        $application = CarApplication::where('uuid', $uuid)->firstOrFail();

        // 检查用户是否是当前节点的审批人
        $approverService = new CarApprovalService();
        if (!$approverService->isApprover($application, $user->uuid)) {
            throw new \Exception('无审批权限');
        }

        if ($action === 'agree') {
            // 同意
            $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

            // 审批通过，step = 2
            $application->update([
                'status' => CarStatus::APPROVED,
                'status_title' => CarStatus::getStatusTitle(CarStatus::APPROVED),
                'step' => 2,
                'approved_plate_id' => $plate->id,
                'approved_plate_number' => $plate->plate_number,
            ]);

            $application->logs()->create([
                'user_uuid' => $user->uuid,
                'user_name' => $user->real_name,
                'status' => CarStatus::APPROVED,
                'status_title' => '同意',
                'reply' => $reply,
                'result' => 1,
                'step' => 2,
            ]);

            // 清除审批待办，给申请人创建结束用车待办
            $application->taskLogs()->forceDelete();
            $application->taskLogs()->create([
                'user_uuid' => $application->user_uuid,
                'user_name' => $application->user_name,
                'status' => CarStatus::APPROVED,
                'status_title' => '结束用车',
            ]);

            // 更新next记录
            $application->next()->delete();
            $application->next()->create([
                'step' => 3,
                'text' => '结束用车',
            ]);

        } else if ($action === 'reject') {
            // 拒绝
            $application->update([
                'status' => CarStatus::REJECTED,
                'status_title' => CarStatus::getStatusTitle(CarStatus::REJECTED),
                'step' => -1,
                'reject_reason' => $reply,
            ]);

            $application->logs()->create([
                'user_uuid' => $user->uuid,
                'user_name' => $user->real_name,
                'status' => CarStatus::REJECTED,
                'status_title' => '拒绝',
                'reply' => $reply,
                'result' => -1,
                'step' => -1,
            ]);

            // 清除待办
            $application->taskLogs()->forceDelete();
            $application->next()->delete();
        }

        return $application->refresh()->load(['logs', 'next']);
    }

    /**
     * 结束用车
     */
    public function end(Request $request, string $uuid): CarApplication
    {
        $user = $request->user();
        $startKm = $request->input('start_km');
        $endKm = $request->input('end_km');

        $application = CarApplication::where('uuid', $uuid)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        if ($application->status !== CarStatus::APPROVED) {
            throw new \Exception('只有审批通过的申请才能结束用车');
        }

        if ($endKm <= $startKm) {
            throw new \Exception('结束公里数必须大于开始公里数');
        }

        // 结束用车，step = 3
        $application->update([
            'status' => CarStatus::COMPLETED,
            'status_title' => CarStatus::getStatusTitle(CarStatus::COMPLETED),
            'step' => 3,
            'start_km' => $startKm,
            'end_km' => $endKm,
        ]);

        // 归还车牌，状态改为空闲
        if ($application->approved_plate_id) {
            $plate = CarPlate::find($application->approved_plate_id);
            if ($plate) {
                $plate->update(['status' => 0]);
            }
        }

        // 清除申请人的结束用车待办
        $application->taskLogs()->forceDelete();
        $application->next()->delete();

        $application->logs()->create([
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'status' => CarStatus::COMPLETED,
            'status_title' => '结束用车',
            'reply' => "开始公里数: {$startKm}, 结束公里数: {$endKm}",
            'result' => 1,
            'step' => 3,
        ]);

        return $application->refresh()->load(['logs']);
    }

    /**
     * 获取申请列表
     */
    public function list(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $keyword = $request->input('keyword');

        $query = CarApplication::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('reason', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * 待处理列表（行办）
     */
    public function todoList(Request $request)
    {
        $user = $request->user();

        return CarApplication::whereHas('taskLogs', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        })
            ->with(['taskLogs'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 已处理列表（行办）
     */
    public function processedList(Request $request)
    {
        $user = $request->user();

        return CarApplication::whereHas('logs', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        })
            ->with(['logs'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
