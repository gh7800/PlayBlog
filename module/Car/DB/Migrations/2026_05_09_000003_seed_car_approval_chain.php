<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedCarApprovalChain extends Migration
{
    public function up()
    {
        $chainUuid = 'car_chain_default';
        DB::table('car_approval_chains')->insert([
            'uuid' => $chainUuid,
            'name' => '用车审批链',
            'description' => '默认用车审批流程',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('car_approval_nodes')->insert([
            'uuid' => 'car_node_1',
            'chain_uuid' => $chainUuid,
            'step' => 1,
            'name' => '行办审批',
            'approver_type' => 'permission_group',
            'approver_value' => 'car_approver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('car_approval_nodes')->where('uuid', 'car_node_1')->delete();
        DB::table('car_approval_chains')->where('uuid', 'car_chain_default')->delete();
    }
}
