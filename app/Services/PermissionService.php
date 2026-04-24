<?php

namespace App\Services;

use App\Models\PermissionGroup;
use App\Models\PermissionGroupUser;
use App\Models\PermissionGroupPermission;

class PermissionService
{
    /**
     * 根据 code 获取权限组
     */
    public static function getGroupByCode(string $code): ?PermissionGroup
    {
        return PermissionGroup::where('code', $code)->first();
    }

    /**
     * 检查用户是否有指定权限
     */
    public static function userHasPermission(string $userUuid, string $permissionCode): bool
    {
        return PermissionGroupUser::where('user_uuid', $userUuid)
            ->whereHas('group.permissions', function ($query) use ($permissionCode) {
                $query->where('permission_code', $permissionCode);
            })
            ->exists();
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
            ->delete() > 0;
    }

    /**
     * 添加权限到组
     */
    public static function addPermissionToGroup(string $groupUuid, string $permissionCode): PermissionGroupPermission
    {
        return PermissionGroupPermission::firstOrCreate([
            'group_uuid' => $groupUuid,
            'permission_code' => $permissionCode,
        ]);
    }

    /**
     * 从组移除权限
     */
    public static function removePermissionFromGroup(string $groupUuid, string $permissionCode): bool
    {
        return PermissionGroupPermission::where('group_uuid', $groupUuid)
            ->where('permission_code', $permissionCode)
            ->delete() > 0;
    }
}
