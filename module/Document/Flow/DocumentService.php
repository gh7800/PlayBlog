<?php

namespace Module\Document\Flow;

use App\Models\BlogUser;
use App\Services\PermissionService;
use Illuminate\Http\Request;
use Module\Document\DocumentStatus;
use Module\Document\Models\Document;

class DocumentService
{
    // 审批人权限组（可在权限群组管理中调整人员）
    public const GROUP_DIRECTOR = 'role_director';   // 行政办公室主任
    public const GROUP_CHAIRMAN = 'role_chairman';   // 董事长

    /**
     * 获取某步骤的审批人 uuid 列表
     * @param int $step 2=行政办公室主任审批 3=董事长审批
     * @return array
     */
    public function getStepApprovers(int $step): array
    {
        $groupCode = $step === 2 ? self::GROUP_DIRECTOR : self::GROUP_CHAIRMAN;
        $group = PermissionService::getGroup($groupCode);
        if (!$group) {
            return [];
        }
        return $group->users()->withTrashed()->get()->pluck('user_uuid')->toArray();
    }

    /**
     * 审批流程
     * 参数：uuid（请示uuid）、action（agree=同意 / reject=驳回）、reply（意见）
     */
    public function approval(Request $request)
    {
        $user = $request->user();
        $uuid = $request->input('uuid');
        $action = $request->input('action', 'agree');
        $reply = $request->input('reply', '');

        $document = Document::query()->where('uuid', $uuid)->firstOrFail();

        // 校验当前用户是否为当前节点的审批人（在待办 taskLogs 中）
        $isApprover = $document->taskLogs()->where('user_uuid', $user->uuid)->exists();
        if (!$isApprover) {
            throw new \Exception('您不是当前审批人，无审批权限');
        }

        $currentStep = (int) $document->step;

        if ($action === 'reject') { // 驳回
            $document->update([
                'step' => -1,
                'status' => DocumentStatus::REJECTED,
                'status_title' => DocumentStatus::getStatusTitle(DocumentStatus::REJECTED),
            ]);

            $document->logs()->create([
                'reply' => $reply,
                'result' => -1,
                'user_uuid' => $user->uuid,
                'user_name' => $user->real_name,
                'status' => DocumentStatus::REJECTED,
                'status_title' => '驳回',
                'step' => $currentStep,
            ]);

            // 清除待办与下一步操作
            $document->taskLogs()->forceDelete();
            $document->next()->forceDelete();

        } else if ($action === 'agree') { // 同意

            if ($currentStep === 1) {
                // 行政办公室主任同意 -> 董事长审批
                $document->update([
                    'step' => 2,
                    'status' => DocumentStatus::PENDING,
                    'status_title' => DocumentStatus::getStatusTitle(DocumentStatus::PENDING),
                ]);

                $document->logs()->create([
                    'reply' => $reply,
                    'result' => 1,
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->real_name,
                    'status' => DocumentStatus::PENDING,
                    'status_title' => '行政办公室主任同意',
                    'step' => $currentStep,
                ]);

                // 清除当前待办，创建董事长待办
                $document->taskLogs()->forceDelete();
                foreach ($this->getStepApprovers(3) as $approverUuid) {
                    $approver = BlogUser::where('uuid', $approverUuid)->first();
                    if (!$approver) {
                        continue;
                    }
                    $document->taskLogs()->create([
                        'user_uuid' => $approver->uuid,
                        'user_name' => $approver->real_name ?? '',
                        'status' => DocumentStatus::APPROVED,
                        'status_title' => DocumentStatus::getStatusTaskTitle(DocumentStatus::APPROVED),
                    ]);
                }

                // 更新下一步操作
                $document->next()->forceDelete();
                $document->next()->createMany([
                    ['step' => 2, 'text' => '驳回'],
                    ['step' => 3, 'text' => '同意'],
                ]);

            } else if ($currentStep === 2) {
                // 董事长同意 -> 完成
                $document->update([
                    'step' => 3,
                    'status' => DocumentStatus::COMPLETED,
                    'status_title' => DocumentStatus::getStatusTitle(DocumentStatus::COMPLETED),
                ]);

                $document->logs()->create([
                    'reply' => $reply,
                    'result' => 1,
                    'user_uuid' => $user->uuid,
                    'user_name' => $user->real_name,
                    'status' => DocumentStatus::COMPLETED,
                    'status_title' => '董事长同意',
                    'step' => $currentStep,
                ]);

                // 流程结束，清除待办与下一步操作
                $document->taskLogs()->forceDelete();
                $document->next()->forceDelete();
            } else {
                throw new \Exception('该请示已处理完成，无需重复审批');
            }
        } else {
            throw new \Exception('无效的审批操作');
        }

        return $document->refresh()->load(['logs', 'next', 'taskLogs']);
    }
}
