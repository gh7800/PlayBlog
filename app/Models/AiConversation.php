<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AI 助理对话记录。
 * 一条记录 = 一个会话（sidebar 里的一项），messages 以 JSON 数组存储整段对话，
 * 结构：[{role:'user'|'assistant', content:'...', chart?:{...}, action?:{key,label}}]。
 * 仅存文本与图表配置，不存 token 流，回载时可按 chart 重绘。
 */
class AiConversation extends Model
{
    // 业务数据在 app_db（与白名单表同库），显式指定连接，避免依赖默认连接
    protected $connection = 'app';

    protected $table = 'ai_conversations';

    protected $fillable = [
        'user_uuid',
        'title',
        'messages',
        'message_count',
        'last_message',
    ];

    // messages 是 longtext JSON，自动序列化/反序列化
    protected $casts = [
        'messages' => 'array',
    ];

    public $timestamps = true;
}
