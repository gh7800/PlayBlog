<?php

namespace Database\Seeders;

use App\Models\BlogUser;
use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupPermission;
use App\Models\PermissionGroupUser;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    // 固定超级管理员组 uuid，保证可重复执行（幂等），同时避免与手动建的组冲突
    const GROUP_UUID = '00000000-0000-0000-0000-0000000000aa';
    const GROUP_CODE = 'super_admin';

    public function run()
    {
        // 1) 超级管理员权限组（固定 uuid，重复执行不会新建）
        $group = PermissionGroup::firstOrCreate(
            ['uuid' => self::GROUP_UUID],
            [
                'code'        => self::GROUP_CODE,
                'name'        => '超级管理员',
                'type'        => 'user',
                'level'       => 1,
                'description' => '系统初始化创建，拥有全部权限，用于打破首组授权死锁',
            ]
        );

        // 2) 把全部权限挂到该组
        $permissions = Permission::all();
        foreach ($permissions as $perm) {
            PermissionGroupPermission::firstOrCreate([
                'group_uuid'      => $group->uuid,
                'permission_uuid' => $perm->uuid,
            ]);
        }

        // 3) 把所有用户都加进该组（seeder 仅执行一次，不影响后续新注册用户）
        $users = BlogUser::all();
        foreach ($users as $user) {
            PermissionGroupUser::firstOrCreate([
                'group_uuid' => $group->uuid,
                'user_uuid'  => $user->uuid,
            ]);
        }

        $this->command->info(
            "SuperAdminSeeder 完成：组[{$group->name}] 已挂 {$permissions->count()} 个权限，已加入 {$users->count()} 个用户。"
        );
    }
}
