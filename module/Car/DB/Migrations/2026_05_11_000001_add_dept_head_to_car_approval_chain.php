<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDeptHeadToCarApprovalChain extends Migration
{
    public function up()
    {
        // 将行办审批节点 step 从 1 改为 2
        DB::table('car_approval_nodes')
            ->where('uuid', 'car_node_1')
            ->update(['step' => 2, 'updated_at' => now()]);

        // 插入部门部长审批节点，step = 1
        DB::table('car_approval_nodes')->insert([
            'uuid' => 'car_node_dept_head',
            'chain_uuid' => 'car_chain_default',
            'step' => 1,
            'name' => '部门部长审批',
            'approver_type' => 'dept_head',
            'approver_value' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('car_approval_nodes')->where('uuid', 'car_node_dept_head')->delete();

        DB::table('car_approval_nodes')
            ->where('uuid', 'car_node_1')
            ->update(['step' => 1, 'updated_at' => now()]);
    }
}
