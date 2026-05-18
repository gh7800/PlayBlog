<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixCarApprovalNodeSteps extends Migration
{
    public function up()
    {
        // 行办审批节点 step 从 3 改为 2
        DB::table('car_approval_nodes')
            ->where('uuid', 'car_node_1')
            ->update(['step' => 2, 'updated_at' => now()]);

        // 同步现有进行中的申请：step=3 的行办待审批申请恢复为 step=2
        DB::table('car_applications')
            ->where('step', 3)
            ->where('status', 'applying')
            ->update(['step' => 2, 'updated_at' => now()]);

        // 修正 next 记录：step=3→2（驳回）, step=4→3（同意/结束用车）
        $appIds = DB::table('car_applications')
            ->where('step', 2)
            ->where('status', 'applying')
            ->pluck('id');

        if ($appIds->isNotEmpty()) {
            DB::table('next')
                ->where('nextTable_type', 'Module\Car\Models\CarApplication')
                ->whereIn('nextTable_id', $appIds)
                ->where('step', 3)
                ->where('text', '驳回')
                ->update(['step' => 2]);

            DB::table('next')
                ->where('nextTable_type', 'Module\Car\Models\CarApplication')
                ->whereIn('nextTable_id', $appIds)
                ->where('step', 4)
                ->where('text', '同意')
                ->update(['step' => 3]);
        }
    }

    public function down()
    {
        DB::table('car_approval_nodes')
            ->where('uuid', 'car_node_1')
            ->update(['step' => 3, 'updated_at' => now()]);

        DB::table('car_applications')
            ->where('step', 2)
            ->where('status', 'applying')
            ->update(['step' => 3, 'updated_at' => now()]);
    }
}
