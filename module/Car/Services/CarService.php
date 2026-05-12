<?php

namespace Module\Car\Services;

use App\Models\BlogUser;
use App\Models\Next;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Module\Car\Enums\CarStatus;
use Module\Car\Jobs\CarAutoApproveJob;
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
        $approverService = new CarApprovalService();
        $nextStep = $approverService->getNextStep($application);
        $application->next()->delete();
        $application->next()->createMany([
            ['step' => $application->step, 'text' => '驳回'],
            ['step' => $nextStep, 'text' => '同意'],
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

        $approverUuids = $node->getApproverUuids($applicant);

        foreach ($approverUuids as $userUuid) {
            $user = BlogUser::where('uuid', $userUuid)->first();
            $application->taskLogs()->create([
                'user_uuid' => $userUuid,
                'user_name' => $user->real_name ?? '',
                'status' => CarStatus::APPLYING,
                'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            ]);
        }

        // 部长审批节点：分发延迟2分钟的自动审批任务
        if ($node->approver_type === 'dept_head') {
            CarAutoApproveJob::dispatch($application->uuid, $application->step)
                ->delay(now()->addMinutes(2));
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
            $isLastNode = $approverService->isLastNode($application);
            $nextStep = $approverService->getNextStep($application);

            if ($isLastNode) {
                // 最后一个节点 - 需要选择车牌，审批通过
                if (!$plateId) {
                    throw new \Exception('同意时必须选择车牌');
                }
                $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

                $application->update([
                    'status' => CarStatus::APPROVED,
                    'status_title' => CarStatus::getStatusTitle(CarStatus::APPROVED),
                    'step' => $nextStep,
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
                    'step' => $nextStep,
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
                    'step' => $nextStep + 1,
                    'text' => '结束用车',
                ]);

            } else {
                // 非最后节点 - 推进到下一节点
                $application->update([
                    'step' => $nextStep,
                ]);

                $application->logs()->create([
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->real_name,
                    'status' => CarStatus::APPLYING,
                    'status_title' => '同意',
                    'reply' => $reply,
                    'result' => 1,
                    'step' => $nextStep,
                ]);

                // 清除当前待办，为下一节点审批人创建待办
                $application->taskLogs()->forceDelete();
                $this->createApproverTaskByNode($application, $application->user);

                // 更新next记录
                $application->next()->delete();
                $application->next()->createMany([
                    ['step' => $nextStep, 'text' => '驳回'],
                    ['step' => $nextStep + 1, 'text' => '同意'],
                ]);
            }

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
     * 系统自动审批（部长审批倒计时到期自动同意）
     */
    public function autoApprove(CarApplication $application): CarApplication
    {
        $approverService = new CarApprovalService();

        if ($approverService->isLastNode($application)) {
            throw new \Exception('最后一个节点不支持自动审批');
        }

        // 用待办中的审批人作为日志记录的用户
        $approverUser = null;
        $taskLog = $application->taskLogs()->first();
        if ($taskLog) {
            $approverUser = BlogUser::where('uuid', $taskLog->user_uuid)->first();
        }

        $nextStep = $approverService->getNextStep($application);

        // 推进到下一节点
        $application->update([
            'step' => $nextStep,
        ]);

        // 记录日志
        $logUserUuid = $approverUser ? $approverUser->uuid : '';
        $logUserName = ($approverUser ? $approverUser->real_name : '系统') . '(自动审批)';
        $application->logs()->create([
            'user_uuid' => $logUserUuid,
            'user_name' => $logUserName,
            'status' => CarStatus::APPLYING,
            'status_title' => '自动同意',
            'reply' => '倒计时结束，系统自动同意',
            'result' => 1,
            'step' => $nextStep,
        ]);

        // 清除当前待办，为下一节点创建待办
        $application->taskLogs()->forceDelete();
        $this->createApproverTaskByNode($application, $application->user);

        // 更新next记录
        $application->next()->delete();
        $application->next()->createMany([
            ['step' => $nextStep, 'text' => '驳回'],
            ['step' => $nextStep + 1, 'text' => '同意'],
        ]);

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

        // 检查里程异常：本次开始公里数比上次结束公里数大于2
        $mileageStatus = 'normal';
        if ($application->approved_plate_id) {
            $lastApplication = CarApplication::where('id', '!=', $application->id)
                ->where('approved_plate_id', $application->approved_plate_id)
                ->where('status', CarStatus::COMPLETED)
                ->orderBy('use_time', 'desc')
                ->first();

            if ($lastApplication && $lastApplication->end_km !== null) {
                if ($startKm - $lastApplication->end_km > 2) {
                    $mileageStatus = 'abnormal';
                }
            }
        }

        // 结束用车，step = 3
        $application->update([
            'status' => CarStatus::COMPLETED,
            'status_title' => CarStatus::getStatusTitle(CarStatus::COMPLETED),
            'step' => 3,
            'start_km' => $startKm,
            'end_km' => $endKm,
            'mileage_status' => $mileageStatus,
        ]);

        if ($mileageStatus === 'abnormal') {
            Log::warning("用车里程异常", [
                'application_uuid' => $application->uuid,
                'plate_id' => $application->approved_plate_id,
                'plate_number' => $application->approved_plate_number,
                'last_end_km' => $lastApplication->end_km,
                'current_start_km' => $startKm,
                'diff' => $startKm - $lastApplication->end_km,
            ]);
        }

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
        $perPage = $request->input('per_page', config('pagination.per_page'));

        return CarApplication::query()
            ->when($request->input('mine'), fn($q) => $q->where('user_uuid', $request->user()->uuid))
            ->when($keyword = $request->input('keyword'), fn($q) => $q->where(function ($q) use ($keyword) {
                $q->where('reason', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%")
                    ->orWhere('approved_plate_number', 'like', "%{$keyword}%");
            }))
            ->when($request->input('km_min'), fn($q, $v) => $q->where(fn($q) => $q->where('start_km', '>=', $v)->orWhere('end_km', '>=', $v)))
            ->when($request->input('km_max'), fn($q, $v) => $q->where(fn($q) => $q->where('start_km', '<=', $v)->orWhere('end_km', '<=', $v)))
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * 待处理列表（行办）
     */
    public function todoList(Request $request)
    {
        $user = $request->user();
        $perPage = $request->input('per_page', config('pagination.per_page'));

        return CarApplication::query()->whereHas('taskLogs', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        })
            ->with(['taskLogs'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
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

    /**
     * 里程异常列表
     */
    public function mileageAbnormalList(Request $request)
    {
        $perPage = $request->input('per_page', config('pagination.per_page'));

        return CarApplication::where('mileage_status', 'abnormal')
            ->with(['plate'])
            ->orderBy('updated_at', 'desc')
            ->paginate($perPage);
    }
}
