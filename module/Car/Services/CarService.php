<?php

namespace Module\Car\Services;

use App\Models\BlogUser;
use Illuminate\Http\Request;
use Module\Car\Enums\CarStatus;
use Module\Car\Models\CarApplication;
use Module\Car\Models\CarPlate;

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

        // 创建待办给行办审批人员
        $this->createApproverTask($application, $user);

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
     * 创建审批待办
     */
    private function createApproverTask(CarApplication $application, $applicant)
    {
        $approverGroup = PermissionService::getGroupByCode('car_approver');
        if (!$approverGroup) {
            return;
        }

        $approverUsers = $approverGroup->users()->get();
        foreach ($approverUsers as $approverUser) {
            $application->taskLogs()->create([
                'user_uuid' => $approverUser->user_uuid,
                'user_name' => $approverUser->user->real_name ?? '',
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

        if ($action === 'agree') {
            // 同意
            $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

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

            // 清除待办
            $application->taskLogs()->forceDelete();

        } else if ($action === 'reject') {
            // 拒绝
            $application->update([
                'status' => CarStatus::REJECTED,
                'status_title' => CarStatus::getStatusTitle(CarStatus::REJECTED),
                'reject_reason' => $reply,
                'step' => -1,
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
        }

        return $application->refresh()->load(['logs']);
    }

    /**
     * 开始用车（状态变为进行中）
     */
    public function start(Request $request, string $uuid): CarApplication
    {
        $application = CarApplication::where('uuid', $uuid)->firstOrFail();

        $application->update([
            'status' => CarStatus::ONGOING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::ONGOING),
        ]);

        return $application->refresh();
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

        if ($endKm <= $startKm) {
            throw new \Exception('结束公里数必须大于开始公里数');
        }

        $application->update([
            'status' => CarStatus::COMPLETED,
            'status_title' => CarStatus::getStatusTitle(CarStatus::COMPLETED),
            'start_km' => $startKm,
            'end_km' => $endKm,
        ]);

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
