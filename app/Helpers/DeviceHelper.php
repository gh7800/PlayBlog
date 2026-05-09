<?php

namespace App\Helpers;

class DeviceHelper
{
    /**
     * 根据 User-Agent 判断设备类型
     */
    public static function getDeviceType(?string $userAgent): string
    {
        if (!$userAgent) {
            return 'Unknown';
        }

        // iOS 设备
        if (str_contains($userAgent, 'iPhone')
            || str_contains($userAgent, 'iPad')
            || str_contains($userAgent, 'iPod')) {
            return 'iOS';
        }

        // Android 设备
        if (str_contains($userAgent, 'Android') || str_contains($userAgent, 'okhttp')) {
            return 'Android';
        }

        // PC/Web
        if (str_contains($userAgent, 'Windows NT')
            || str_contains($userAgent, 'Macintosh')
            || str_contains($userAgent, 'Linux')) {
            return 'Web';
        }

        return 'Unknown';
    }

    /**
     * 获取设备详细信息
     */
    public static function getDeviceInfo(?string $userAgent): array
    {
        $type = self::getDeviceType($userAgent);

        return [
            'type' => $type,
            'user_agent' => $userAgent,
            'is_mobile' => in_array($type, ['iOS', 'Android']),
            'is_web' => $type === 'Web',
            'is_wechat' => $type === 'Wechat',
        ];
    }
}
