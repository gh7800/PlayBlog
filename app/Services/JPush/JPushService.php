<?php
namespace App\Services\JPush;

use JPush\Client as JPushClient;
use JPush\Exceptions\JPushException;

class JPushService
{
    // 极光配置
    private $appKey = 'eb3c43f763083f5749baf8fa';
    private $masterSecret = 'ea679aba1277857bab18f507 ';
    private $isProduction = false; // iOS推送环境：false=开发环境，true=生产环境
    private $logFile; // 先声明，不初始化

    // 构造函数
    public function __construct()
    {
        // 在构造函数中初始化需要使用辅助函数的属性
        $this->logFile = storage_path('logs/jpush.log');
    }

    // 初始化客户端
    private function getClient()
    {
        try {
            // 初始化客户端，通过日志文件实现调试功能
            $client = new JPushClient($this->appKey, $this->masterSecret, $this->logFile);
            return $client;
        } catch (JPushException $e) {
            throw new \Exception('JPush初始化失败：' . $e->getMessage());
        }
    }

    // 其他方法保持不变...
}
