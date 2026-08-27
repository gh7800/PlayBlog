<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AI 助理对话记录表（app_db）。
 * 一条记录 = 一个会话；messages 以 JSON 存储整段对话，回载时一次性取出。
 * 用 Schema::connection('app') 显式落在 app_db，与白名单业务表同库。
 */
class CreateAiConversationsTable extends Migration
{
    public function up()
    {
        Schema::connection('app')->create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('user_uuid', 36)->comment('所属用户（user.uuid）');
            $table->string('title')->nullable()->comment('会话标题（取首条用户问题）');
            $table->longText('messages')->comment('对话内容 JSON：[{role,content,chart?,action?}]');
            $table->unsignedInteger('message_count')->default(0)->comment('消息条数（冗余，供列表展示）');
            $table->text('last_message')->nullable()->comment('最后一条消息预览（冗余，供列表展示）');
            $table->timestamps();

            $table->index('user_uuid', 'idx_ai_conv_user');
            $table->index('updated_at', 'idx_ai_conv_updated');
        });
    }

    public function down()
    {
        Schema::connection('app')->dropIfExists('ai_conversations');
    }
}
