<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        $permissions = [
            ['code' => 'organization_admin',          'name' => '组织管理', 'type' => 'function', 'module' => 'system',   'description' => '组织架构管理权限'],
            ['code' => 'car_admin',                   'name' => '车辆管理', 'type' => 'function', 'module' => 'car',     'description' => '车辆管理权限'],
            ['code' => 'car_approver',                'name' => '用车审批', 'type' => 'function', 'module' => 'car',     'description' => '用车申请审批权限'],
            ['code' => 'chairman_only_permission',    'name' => '董事长专属', 'type' => 'page',   'module' => 'system', 'description' => '董事长专用页面权限'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(
                ['code' => $perm['code']],
                $perm
            );
        }
    }
}
