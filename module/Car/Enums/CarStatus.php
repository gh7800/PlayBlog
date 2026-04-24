<?php

namespace Module\Car\Enums;

class CarStatus
{
    const APPLYING = 'applying';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const ONGOING = 'ongoing';
    const COMPLETED = 'completed';

    public static function getStatusTitle($status): string
    {
        $titles = [
            self::APPLYING => '申请中',
            self::APPROVED => '已同意',
            self::REJECTED => '已拒绝',
            self::ONGOING => '用车中',
            self::COMPLETED => '已完成',
        ];
        return $titles[$status] ?? $status;
    }

    public static function getCarTypeTitle($type): string
    {
        $titles = [
            'general' => '一般用车',
            'business' => '业务用车',
            'other' => '其他',
        ];
        return $titles[$type] ?? $type;
    }
}
