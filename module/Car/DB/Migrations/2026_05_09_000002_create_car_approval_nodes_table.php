<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApprovalNodesTable extends Migration
{
    public function up()
    {
        Schema::create('car_approval_nodes', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->char('chain_uuid', 36)->comment('所属链表UUID');
            $table->integer('step')->comment('节点顺序');
            $table->string('name', 100)->comment('节点名称');
            $table->enum('approver_type', ['permission_group', 'user', 'dept_head'])
                  ->comment('审批人类型');
            $table->string('approver_value', 255)->comment('审批人值');
            $table->timestamps();
            $table->softDeletes();

            $table->index('chain_uuid');
            $table->index(['chain_uuid', 'step']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_approval_nodes');
    }
}
