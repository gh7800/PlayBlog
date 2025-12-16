<?php

namespace App\Services\JPush;

class PushController
{
    public function sendPush()
    {
        $jpush = new JPushService();

        // 1. 单设备推送
        $result = $jpush->pushToSingleDevice(
            '1234567890abcdef1234567890abcdef', // 客户端获取的Registration ID
            '测试标题',
            '这是一条极光推送测试消息',
            ['jump_url' => 'https://www.example.com', 'order_id' => '10086'] // 附加参数
        );

        // 2. 批量推送（示例）
        // $result = $jpush->pushToBatchDevices(
        //     ['id1', 'id2', 'id3'],
        //     '批量推送标题',
        //     '批量推送内容'
        // );

        // 3. 按别名推送（示例）
        // $result = $jpush->pushToAlias('user_1001', '别名推送标题', '别名推送内容');

        // 输出结果
        if ($result['success']) {
            echo "推送成功，消息ID：" . $result['msg_id'];
        } else {
            echo "推送失败：" . $result['error_msg'];
        }
    }
}
