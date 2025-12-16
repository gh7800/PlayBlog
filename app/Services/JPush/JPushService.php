<?php
namespace App\Services\JPush;

use JPush\Client as JPushClient;
use JPush\Exceptions\JPushException;

class JPushService
{
    // 极光配置
    private $appKey = '你的AppKey';
    private $masterSecret = '你的Master Secret';
    private $isProduction = false; // iOS推送环境：false=开发环境，true=生产环境

    // 初始化客户端
    private function getClient()
    {
        try {
            $client = new JPushClient($this->appKey, $this->masterSecret);
            // 开启调试（可选，开发环境用）
            $client->setDebug(true);
            return $client;
        } catch (JPushException $e) {
            throw new \Exception('JPush初始化失败：' . $e->getMessage());
        }
    }

    /**
     * 单设备推送（通过Registration ID）
     * @param string $registrationId 设备注册ID（客户端获取）
     * @param string $title 推送标题（安卓必填，iOS可选）
     * @param string $content 推送内容
     * @param array $extras 附加参数（如跳转链接、自定义数据）
     * @return array 推送结果
     */
    public function pushToSingleDevice(string $registrationId, string $title, string $content, array $extras = [])
    {
        $client = $this->getClient();
        try {
            $push = $client->push();
            // 1. 设置推送目标（单个设备）
            $push->setPlatform(['android', 'ios']); // 推送平台：安卓+iOS
            $push->addRegistrationId($registrationId);

            // 2. 设置推送内容
            $push->setNotification(
            // 安卓推送配置
                $client->android($content, $title, 1, $extras),
                // iOS推送配置
                $client->ios($content, 'default', 1, true, $this->isProduction, $extras)
            );

            // 3. 设置可选参数（如离线保存时间、推送时间）
            $push->setOptions([
                'time_to_live' => 86400, // 离线消息保存时间（秒），默认86400
                'apns_production' => $this->isProduction, // iOS生产环境
            ]);

            // 4. 执行推送
            $response = $push->send();
            return [
                'success' => true,
                'msg_id' => $response['msg_id'],
                'data' => $response
            ];
        } catch (JPushException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => $e->getMessage()
            ];
        }
    }

    /**
     * 批量推送（多个Registration ID）
     * @param array $registrationIds 设备ID列表（最多1000个）
     * @param string $title 标题
     * @param string $content 内容
     * @param array $extras 附加参数
     * @return array
     */
    public function pushToBatchDevices(array $registrationIds, string $title, string $content, array $extras = [])
    {
        $client = $this->getClient();
        try {
            $push = $client->push();
            $push->setPlatform(['android', 'ios']);
            $push->addRegistrationIds($registrationIds); // 批量添加设备ID

            $push->setNotification(
                $client->android($content, $title, 1, $extras),
                $client->ios($content, 'default', 1, true, $this->isProduction, $extras)
            );

            $push->setOptions(['time_to_live' => 86400]);
            $response = $push->send();

            return [
                'success' => true,
                'msg_id' => $response['msg_id'],
                'data' => $response
            ];
        } catch (JPushException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => $e->getMessage()
            ];
        }
    }

    /**
     * 按别名推送（客户端绑定别名后）
     * @param string $alias 设备别名
     * @param string $title 标题
     * @param string $content 内容
     * @return array
     */
    public function pushToAlias(string $alias, string $title, string $content)
    {
        $client = $this->getClient();
        try {
            $push = $client->push();
            $push->setPlatform(['android', 'ios']);
            $push->addAlias($alias); // 按别名推送

            $push->setNotification(
                $client->android($content, $title),
                $client->ios($content, 'default', 1, true, $this->isProduction)
            );

            $response = $push->send();
            return ['success' => true, 'data' => $response];
        } catch (JPushException $e) {
            return ['success' => false, 'error_msg' => $e->getMessage()];
        }
    }
}
