<?php

namespace App\Services\JPush;


use Illuminate\Http\Request;

class PushController
{
    public function sendPush(Request $request)
    {
        $jpush = new JPushService();
        $regId = $request->input('registrationId');

        $regIds = is_array($regId) ? $regId : [$regId];

        // 1. 单设备推送
        $result = $jpush->pushToBatchDevices(
            $regIds, // 客户端获取的Registration ID
            '测试标题',
            '这是一条极光推送测试消息',
            ['type' => 'document', 'uuid' => '10010'] // 附加参数
        );

        // 2. 批量推送（示例）
        // $result = $jpush->pushToBatchDevices(
        //     ['id1', 'id2', 'id3'],
        //     '批量推送标题',
        //     '批量推送内容'
        // );

        // 3. 按别名推送（示例）
        // $result = $jpush->pushToAlias('user_1001', '别名推送标题', '别名推送内容');

        return $result;

    }
}
