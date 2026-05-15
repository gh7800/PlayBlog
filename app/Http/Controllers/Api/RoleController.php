<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupUser;
use App\Models\PermissionGroupPermission;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends ApiController
{
    /**
     * 角色列表
     */
    public function index(): JsonResponse
    {
        try {
            $groups = PermissionGroup::with(['users', 'permissions.permission'])
                ->where('code', 'like', 'role_%')
                ->get();
            return $this->success($groups);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建角色
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validate = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:permission_groups,code',
            'description' => 'nullable|string',
            'level' => 'nullable|integer|min:1',
        ], [
            'name.required' => '请填写角色名称',
            'code.required' => '请填写角色编码',
            'code.unique' => '角色编码已存在',
            'level.integer' => '等级必须是整数',
            'level.min' => '等级最小为1',
        ]);

        try {
            $group = PermissionService::createGroup($validate);
            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新角色
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();
        $user_uuid = $user->uuid;
        if (!PermissionService::userHasPermission($user_uuid, 'organization_admin')) {
            return $this->error("无管理权限_$user_uuid");
        }

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();

            $group->name = $request->input('name', $group->name);
            $group->description = $request->input('description', $group->description);
            if ($request->has('level')) {
                $group->level = $request->input('level');
            }
            $group->save();

            // 批量替换人员
            if ($request->has('user_uuids')) {
                PermissionService::syncGroupUsers($uuid, $request->input('user_uuids'));
            }

            // 批量替换权限
            if ($request->has('permission_uuids')) {
                PermissionService::syncGroupPermissions($uuid, $request->input('permission_uuids'));
            }

            $group->load(['users', 'permissions.permission']);
            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除角色
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();

            // 删除关联的成员和权限
            $group->users()->delete();
            $group->permissions()->delete();
            $group->delete();

            return $this->success($group, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加成员到角色
     */
    public function addUser(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        $validate = $request->validate([
            'user_uuid' => 'required|uuid',
        ], [
            'user_uuid.required' => '请填写用户UUID',
        ]);

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();
            $member = PermissionService::addUserToGroup($uuid, $validate['user_uuid']);
            return $this->success($member);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 从角色移除成员
     */
    public function removeUser(Request $request, string $uuid, string $userUuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            PermissionService::removeUserFromGroup($uuid, $userUuid);
            return $this->success(null, '移除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 给角色添加权限
     */
    public function addPermission(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'permission_uuid' => 'required|string',
        ], [
            'permission_uuid.required' => '请填写权限UUID',
        ]);

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();
            $permission = PermissionService::addPermissionToGroup($uuid, $validate['permission_uuid']);
            return $this->success($permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 移除角色权限
     */
    public function removePermission(Request $request, string $uuid, string $permissionUuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            PermissionService::removePermissionFromGroup($uuid, $permissionUuid);
            return $this->success(null, '移除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
