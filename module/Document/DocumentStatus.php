<?php

namespace Module\Document;

class DocumentStatus
{
    public const NEW = 'new';          // 新提交（待行政办公室主任审批）
    public const REJECTED = 'rejected';// 已驳回
    public const SEND = 'send';        // 已申请（兼容保留）
    public const RECEIVE = 'receive';  // 待接收（兼容保留）
    public const PENDING = 'pending';  // 主任已审批（待董事长审批）
    public const APPROVED = 'approved';// 已通过（兼容保留）
    public const COMPLETED = 'completed'; // 已完成

    // 请示类型
    public const TYPE_ZONGBANHUI = 'zongbanhui'; // 总办会
    public const TYPE_DANGWEIHUI  = 'dangweihui'; // 党委会
    public const TYPE_DONGSHIHUI  = 'dongshihui'; // 董事会

    // 审批流程：step1 行政办公室主任审批 -> step2 董事长审批 -> step3 完成
    private const STATUS_TITLES = [
        self::NEW       => '待审批',
        self::REJECTED  => '已驳回',
        self::SEND      => '已申请',
        self::RECEIVE   => '已接收',
        self::PENDING   => '待董事长审批',
        self::APPROVED  => '已通过',
        self::COMPLETED => '已完成',
    ];
    // 待处理（taskLogs）标题
    private const STATUS_TITLES_TASK = [
        self::REJECTED  => '驳回',
        self::SEND      => '待申请',
        self::RECEIVE   => '待行政办公室主任审批',
        self::PENDING   => '待行政办公室主任审批',
        self::APPROVED  => '待董事长审批',
    ];

    private const TYPE_TITLES = [
        self::TYPE_ZONGBANHUI => '总办会',
        self::TYPE_DANGWEIHUI => '党委会',
        self::TYPE_DONGSHIHUI => '董事会',
    ];

    private const STEPS = [1, 2, 3];
    public static function getNextStep(int $step): ?int
    {
        $index = array_search($step, self::STEPS);
        if ($index === false || !isset(self::STEPS[$index + 1])) {
            return null; // 已是最后一步
        }
        return self::STEPS[$index + 1];
    }

    //已处理状态
    public static function getStatusTitle(string $status): string
    {
        return self::STATUS_TITLES[$status] ?? '未知状态';
    }

    //待处理状态
    public static function getStatusTaskTitle(string $status): string
    {
        return self::STATUS_TITLES_TASK[$status] ?? '未知状态';
    }

    //请示类型标题
    public static function getTypeTitle(?string $type): string
    {
        if (!$type) {
            return '-';
        }
        return self::TYPE_TITLES[$type] ?? $type;
    }
}
