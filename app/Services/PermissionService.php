<?php

namespace App\Services;

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
                $query->where('permission_code', $permissionCode);
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
     * 检查用户是否有指定权限（包含等级继承）
     * 高等级用户（level值小）自动拥有低等级用户的权限
     */
    public static function userHasPermission(string $userUuid, string $permissionCode): bool
    {
        // 直接权限检查
        if (self::userHasDirectPermission($userUuid, $permissionCode)) {
            return true;
        }

        // 获取用户所有角色
        $userGroups = PermissionGroupUser::where('user_uuid', $userUuid)
            ->with('group')
            ->get()
            ->pluck('group')
            ->filter();

        if ($userGroups->isEmpty()) {
            return false;
        }

        // 获取用户最高等级（最小数字）
        $userMaxLevel = $userGroups->min('level');

        // 检查是否存在更低等级（数字更大）的角色拥有该权限
        // 高等级用户(level值小)自动继承低等级用户(level值大)的权限
        return PermissionGroupPermission::where('permission_code', $permissionCode)
            ->whereHas('group', function ($query) use ($userMaxLevel) {
                $query->where('level', '>', $userMaxLevel);
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
