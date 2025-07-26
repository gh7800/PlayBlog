<?php

namespace Module\Document\api;

class DocumentStatus
{
    const NEW = 'new';


    public static function getStatusTitle(string $status): string
    {
        $map = [
            DocumentStatus::NEW => '草稿',
            'in_progress' => '待处理',
            'approved' => '已通过',
            'rejected' => '已驳回',
            'completed' => '已完成',
        ];

        return $map[$status] ?? '未知';
    }


}
