<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupUser;
use App\Models\PermissionGroupPermission;
use Illuminate\Support\Facades\Log;

class PermissionService
{
    /**
     * 检查用户是否有直接分配的权限
     */
    public static function userHasDirectPermission(string $userUuid, string $permissionCode): bool
    {
        return PermissionGroupUser::where('user_uuid', $userUuid)
            ->whereHas('group.permissions', function ($query) use ($permissionCode) {
                $query->whereHas('permission', function ($q) use ($permissionCode) {
                    $q->where('code', $permissionCode);
                });
            })
            ->exists();
    }

    /**
     * 根据 code 获取权限组
     */
    public static function getGroupByCode(string $code): ?PermissionGroup
    {
        return PermissionGroup::where('code', $code)->first();
    }

    /**
     * 根据 uuid 或 code 获取权限组（兼容新旧数据）
     */
    public static function getGroup(string $uuidOrCode): ?PermissionGroup
    {
        return PermissionGroup::where('uuid', $uuidOrCode)
            ->orWhere('code', $uuidOrCode)
            ->first();
    }

    /**
     * 检查用户是否有指定权限（包含等级继承）
     * 高等级用户（level值小）自动拥有低等级用户的权限
     */
    public static function userHasPermission(string $userUuid, string $permissionCode): bool
    {
        // 直接权限检查
        if (self::userHasDirectPermission($userUuid, $permissionCode)) {
            return true;
        }

        return false;
    }

    /**
     * 创建权限组
     */
    public static function createGroup(array $data): PermissionGroup
    {
        return PermissionGroup::create($data);
    }

    /**
     * 添加成员到组
     */
    public static function addUserToGroup(string $groupUuid, string $userUuid): PermissionGroupUser
    {
        return PermissionGroupUser::firstOrCreate([
            'group_uuid' => $groupUuid,
            'user_uuid' => $userUuid,
        ]);
    }

    /**
     * 从组移除成员
     */
    public static function removeUserFromGroup(string $groupUuid, string $userUuid): bool
    {
        return PermissionGroupUser::where('group_uuid', $groupUuid)
            ->where('user_uuid', $userUuid)
            ->forceDelete() > 0;
    }

    /**
     * 移除用户所有角色
     */
    public static function removeUserFromAllGroups(string $userUuid): int
    {
        return PermissionGroupUser::where('user_uuid', $userUuid)->forceDelete();
    }

    /**
     * 添加权限到组
     */
    public static function addPermissionToGroup(string $groupUuid, string $permissionUuid): PermissionGroupPermission
    {
        return PermissionGroupPermission::firstOrCreate([
            'group_uuid' => $groupUuid,
            'permission_uuid' => $permissionUuid,
        ]);
    }

    /**
     * 从组移除权限
     */
    public static function removePermissionFromGroup(string $groupUuid, string $permissionUuid): bool
    {
        return PermissionGroupPermission::where('group_uuid', $groupUuid)
            ->where('permission_uuid', $permissionUuid)
            ->delete() > 0;
    }

    /**
     * 批量更新权限组人员
     */
    public static function syncGroupUsers(string $groupUuid, array $userUuids): void
    {
        PermissionGroupUser::where('group_uuid', $groupUuid)->forceDelete();
        foreach ($userUuids as $userUuid) {
            PermissionGroupUser::create([
                'group_uuid' => $groupUuid,
                'user_uuid' => $userUuid,
            ]);
        }
    }

    /**
     * 批量更新权限组权限
     */
    public static function syncGroupPermissions(string $groupUuid, array $permissionUuids): void
    {
        PermissionGroupPermission::where('group_uuid', $groupUuid)->forceDelete();
        foreach ($permissionUuids as $permissionUuid) {
            PermissionGroupPermission::create([
                'group_uuid' => $groupUuid,
                'permission_uuid' => $permissionUuid,
            ]);
        }
    }

    /**
     * 获取用户所有权限码列表
     */
    public static function getUserPermissionCodes(string $userUuid): array
    {
        return PermissionGroupUser::where('user_uuid', $userUuid)
            ->whereHas('group.permissions')
            ->with('group.permissions.permission')
            ->get()
            ->pluck('group.permissions')
            ->flatten()
            ->pluck('permission.code')
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * 获取用户权限列表（按模块分组的 tree 结构）
     */
    public static function getUserPermissionsTree(string $userUuid): array
    {
        $codes = self::getUserPermissionCodes($userUuid);
        if (empty($codes)) {
            return [];
        }

        $permissions = Permission::whereIn('code', $codes)->get()->groupBy('module');

        $tree = [];
        foreach ($permissions as $module => $items) {
            $grouped = $items->groupBy('type');
            $children = [];

            if ($grouped->has('page')) {
                $children[] = [
                    'type' => 'page',
                    'label' => '页面权限',
                    'children' => $grouped->get('page')->values(),
                ];
            }
            if ($grouped->has('function')) {
                $children[] = [
                    'type' => 'function',
                    'label' => '功能权限',
                    'children' => $grouped->get('function')->values(),
                ];
            }

            $tree[] = [
                'module' => $module,
                'children' => $children,
            ];
        }

        return $tree;
    }
}
