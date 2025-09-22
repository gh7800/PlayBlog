<?php

namespace Module\Document;

class DocumentStatus
{
    public const NEW = 'new';
    public const REJECTED = 'rejected';
    public const SEND = 'send';
    public const RECEIVE = 'receive';
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const COMPLETED = 'completed';

    private const STATUS_TITLES = [
        self::NEW  => '草稿',
        self::REJECTED  => '驳回',
        self::SEND => '已申请',
        self::RECEIVE => '已接收',
        self::PENDING => '已审批',
        self::APPROVED => '分管领导已审批',
        self::COMPLETED => '已完成',
    ];
    private const STATUS_TITLES_TASK = [
        self::REJECTED  => '驳回',
        self::SEND => '待申请',
        self::RECEIVE => '待接收',
        self::PENDING => '待部长审批',
        self::APPROVED => '待分管领导审批'
    ];

    private const STEPS = [1,2,3,4,5];
    public static function getNextStep(int $step): string{
        $index = array_search($step, self::STEPS) + 1;
        return self::STEPS[$index];
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


}
