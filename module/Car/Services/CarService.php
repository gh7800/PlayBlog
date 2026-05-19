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
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Style\Table;

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
                    'step' => $nextStep + 1,
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
                    'step' => $nextStep + 1,
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
            ->when($request->input('mileage_status'), fn($q, $v) => $q->where('mileage_status', $v))
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

    /**
     * 导出申请列表
     */
    public function export(Request $request): string
    {
        $query = CarApplication::query()
            ->when($request->input('mine'), fn($q) => $q->where('user_uuid', $request->user()->uuid))
            ->when($keyword = $request->input('keyword'), fn($q) => $q->where(function ($q) use ($keyword) {
                $q->where('reason', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%")
                    ->orWhere('approved_plate_number', 'like', "%{$keyword}%");
            }))
            ->when($request->input('km_min'), fn($q, $v) => $q->where(fn($q) => $q->where('start_km', '>=', $v)->orWhere('end_km', '>=', $v)))
            ->when($request->input('km_max'), fn($q, $v) => $q->where(fn($q) => $q->where('start_km', '<=', $v)->orWhere('end_km', '<=', $v)))
            ->when($request->input('mileage_status'), fn($q, $v) => $q->where('mileage_status', $v))
            ->orderBy('created_at', 'desc');

        $applications = $query->get();

        $headers = ['申请编号', '申请人', '用车类型', '用车事由', '乘车人数', '用车时间', '车牌号', '状态', '开始公里数', '结束公里数', '里程状态', '备注', '创建时间'];

        $output = fopen('php://temp', 'r+');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, $headers);

        foreach ($applications as $app) {
            $row = [
                $app->uuid,
                $app->user_name,
                CarStatus::getCarTypeTitle($app->car_type),
                $app->reason,
                $app->passenger_count,
                $app->use_time,
                $app->approved_plate_number ?: '-',
                CarStatus::getStatusTitle($app->status),
                $app->start_km ?: '-',
                $app->end_km ?: '-',
                $app->mileage_status === 'abnormal' ? '异常' : ($app->mileage_status === 'normal' ? '正常' : '-'),
                $app->remark ?: '-',
                $app->created_at,
            ];
            fputcsv($output, $row);
        }

        rewind($output);
            $content = stream_get_contents($output);
            fclose($output);

            return $content;
        }

        /**
         * 导出用车审批流程 Word 文档
         */
        public function exportWord(string $uuid): string
        {
            $application = CarApplication::where('uuid', $uuid)
                ->with(['logs', 'plate'])
                ->firstOrFail();

            $phpWord = new PhpWord();

            $section = $phpWord->addSection();

            $section->addText('用车审批流程单', ['bold' => true, 'size' => 18], ['alignment' => 'center']);
            $section->addTextBreak(1);

            $section->addText('基本信息', ['bold' => true, 'size' => 14]);

            $basicTable = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('申请编号');
            $basicTable->addCell(7000)->addText($application->uuid);

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('申请人');
            $basicTable->addCell(7000)->addText($application->user_name);

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('用车类型');
            $basicTable->addCell(7000)->addText(CarStatus::getCarTypeTitle($application->car_type));

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('用车事由');
            $basicTable->addCell(7000)->addText($application->reason);

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('乘车人数');
            $basicTable->addCell(7000)->addText($application->passenger_count . ' 人');

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('用车时间');
            $basicTable->addCell(7000)->addText($application->use_time);

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('车牌号');
            $basicTable->addCell(7000)->addText($application->approved_plate_number ?: '未分配');

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('当前状态');
            $basicTable->addCell(7000)->addText(CarStatus::getStatusTitle($application->status));

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('备注');
            $basicTable->addCell(7000)->addText($application->remark ?: '无');

            $basicTable->addRow();
            $basicTable->addCell(2000)->addText('申请时间');
            $basicTable->addCell(7000)->addText($application->created_at);

            $section->addTextBreak(1);

            if ($application->start_km || $application->end_km) {
                $section->addText('里程信息', ['bold' => true, 'size' => 14]);

                $mileageTable = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);

                $mileageTable->addRow();
                $mileageTable->addCell(2000)->addText('开始公里数');
                $mileageTable->addCell(7000)->addText($application->start_km ?: '-');

                $mileageTable->addRow();
                $mileageTable->addCell(2000)->addText('结束公里数');
                $mileageTable->addCell(7000)->addText($application->end_km ?: '-');

                $mileageTable->addRow();
                $mileageTable->addCell(2000)->addText('行驶里程');
                $mileageTable->addCell(7000)->addText(
                    ($application->start_km && $application->end_km)
                        ? ($application->end_km - $application->start_km) . ' km'
                        : '-'
                );

                $mileageTable->addRow();
                $mileageTable->addCell(2000)->addText('里程状态');
                $mileageTable->addCell(7000)->addText(
                    $application->mileage_status === 'abnormal' ? '异常' : ($application->mileage_status === 'normal' ? '正常' : '-')
                );

                $section->addTextBreak(1);
            }

            $section->addText('审批流程', ['bold' => true, 'size' => 14]);

            $logs = $application->logs()->orderBy('created_at', 'asc')->get();

            if ($logs->isEmpty()) {
                $section->addText('暂无审批记录');
            } else {
                $logTable = $section->addTable(['borderSize' => 6, 'borderColor' => '000000', 'cellMargin' => 80]);

                $logTable->addRow();
                $logTable->addCell(1500)->addText('步骤', ['bold' => true]);
                $logTable->addCell(1500)->addText('审批人', ['bold' => true]);
                $logTable->addCell(1500)->addText('操作', ['bold' => true]);
                $logTable->addCell(2500)->addText('回复', ['bold' => true]);
                $logTable->addCell(2000)->addText('时间', ['bold' => true]);

                foreach ($logs as $log) {
                    $logTable->addRow();
                    $logTable->addCell(1500)->addText($log->step);
                    $logTable->addCell(1500)->addText($log->user_name);
                    $logTable->addCell(1500)->addText($log->status_title);
                    $logTable->addCell(2500)->addText($log->reply ?: '-');
                    $logTable->addCell(2000)->addText($log->created_at->format('Y-m-d H:i'));
                }
            }

            $section->addTextBreak(2);
            $section->addText('打印时间：' . now()->format('Y-m-d H:i:s'), ['size' => 10], ['alignment' => 'right']);

            $objWriter = IOFactory::createWriter($phpWord, 'Word2007');

            ob_start();
            $objWriter->save('php://output');
            $content = ob_get_contents();
            ob_end_clean();

            return $content;
        }
}
