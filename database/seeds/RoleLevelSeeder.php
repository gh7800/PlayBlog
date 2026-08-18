<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionGroup;

/**
 * 角色等级
 */
class RoleLevelSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => '董事长', 'code' => 'role_chairman', 'level' => 1, 'description' => '最高管理者'],
            ['name' => '总经理', 'code' => 'role_general_manager', 'level' => 2, 'description' => '公司总经理'],
            ['name' => '副经理', 'code' => 'role_deputy_manager', 'level' => 3, 'description' => '副总经理'],
            ['name' => '主任', 'code' => 'role_director', 'level' => 4, 'description' => '部门主任'],
            ['name' => '部长', 'code' => 'role_department_head', 'level' => 5, 'description' => '部门部长'],
            ['name' => '科员', 'code' => 'role_staff', 'level' => 6, 'description' => '普通科员'],
            ['name' => '班长', 'code' => 'role_foreman', 'level' => 7, 'description' => '班组班长'],
            ['name' => '副班长', 'code' => 'role_deputy_foreman', 'level' => 8, 'description' => '班组副班长'],
        ];

        foreach ($roles as $role) {
            PermissionGroup::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}
