<?php
namespace App\Services\JPush;

use JPush\Client as JPushClient;
use JPush\Exceptions\APIConnectionException;
use JPush\Exceptions\APIRequestException;
use JPush\Exceptions\JPushException;

class JPushService
{
    // 极光配置
    private $appKey = 'eb3c43f763083f5749baf8fa';
    private $masterSecret = 'ea679aba1277857bab18f507'; // 已移除后面的空格
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
        try {
            $client = $this->getClient();

            $response = $client->push()
                ->setPlatform(['android', 'ios']) // 推送平台：安卓+iOS
                ->addRegistrationId($registrationId) // 添加单个设备ID
                ->androidNotification($content, [ // 安卓推送配置
                    'title' => $title,
                    'builder_id' => 1,
                    'extras' => $extras
                ])
                ->iosNotification($content, [ // iOS推送配置
                    'sound' => 'default',
                    'badge' => '+1',
                    'content-available' => true,
                    'extras' => $extras
                ])
                ->options([
                    'time_to_live' => 86400, // 离线消息保存时间（秒），默认86400
                    'apns_production' => $this->isProduction, // iOS生产环境
                ])
                ->send();

            return [
                'success' => true,
                'msg_id' => $response['body']['msg_id'],
                'data' => $response
            ];
        } catch (APIConnectionException $e) {
            return [
                'success' => false,
                'error_msg' => '连接极光服务器失败：' . $e->getMessage()
            ];
        } catch (APIRequestException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '极光API请求失败：' . $e->getMessage(),
                'http_code' => $e->getHttpCode(),
                'headers' => $e->getHeaders()
            ];
        } catch (JPushException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '推送失败：' . $e->getMessage()
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
        try {
            $client = $this->getClient();

            $push = $client->push()
                ->setPlatform(['android', 'ios']);

            // 添加多个设备ID（逐个添加）
            foreach ($registrationIds as $registrationId) {
                $push->addRegistrationId($registrationId);
            }

            $response = $push->androidNotification($content, [ // 安卓推送配置
                    'title' => $title,
                    'builder_id' => 1,
                    'extras' => $extras
                ])
                ->iosNotification($content, [ // iOS推送配置
                    'sound' => 'default',
                    'badge' => '+1',
                    'content-available' => true,
                    'extras' => $extras
                ])
                ->options([
                    'time_to_live' => 86400, // 离线消息保存时间（秒）
                    'apns_production' => $this->isProduction, // iOS生产环境
                ])
                ->send();

            return [
                'success' => true,
                'msg_id' => $response['body']['msg_id'],
                'data' => $response
            ];
        } catch (APIConnectionException $e) {
            return [
                'success' => false,
                'error_msg' => '连接极光服务器失败：' . $e->getMessage()
            ];
        } catch (APIRequestException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '极光API请求失败：' . $e->getMessage(),
                'http_code' => $e->getHttpCode(),
                'headers' => $e->getHeaders()
            ];
        } catch (JPushException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '推送失败：' . $e->getMessage()
            ];
        }
    }

    /**
     * 按别名推送（客户端绑定别名后）
     * @param string $alias 设备别名
     * @param string $title 标题
     * @param string $content 内容
     * @param array $extras 附加参数
     * @return array
     */
    public function pushToAlias(string $alias, string $title, string $content, array $extras = [])
    {
        try {
            $client = $this->getClient();

            $response = $client->push()
                ->setPlatform(['android', 'ios']) // 推送平台：安卓+iOS
                ->addAlias($alias) // 按别名推送
                ->androidNotification($content, [ // 安卓推送配置
                    'title' => $title,
                    'builder_id' => 1,
                    'extras' => $extras
                ])
                ->iosNotification($content, [ // iOS推送配置
                    'sound' => 'default',
                    'badge' => '+1',
                    'content-available' => true,
                    'extras' => $extras
                ])
                ->options([
                    'apns_production' => $this->isProduction, // iOS生产环境
                ])
                ->send();

            return [
                'success' => true,
                'msg_id' => $response['body']['msg_id'],
                'data' => $response
            ];
        } catch (APIConnectionException $e) {
            return [
                'success' => false,
                'error_msg' => '连接极光服务器失败：' . $e->getMessage()
            ];
        } catch (APIRequestException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '极光API请求失败：' . $e->getMessage(),
                'http_code' => $e->getHttpCode(),
                'headers' => $e->getHeaders()
            ];
        } catch (JPushException $e) {
            return [
                'success' => false,
                'error_code' => $e->getCode(),
                'error_msg' => '推送失败：' . $e->getMessage()
            ];
        }
    }
}
