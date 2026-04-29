<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\PermissionGroup;
use App\Models\PermissionGroupUser;
use App\Models\PermissionGroupPermission;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionGroupController extends ApiController
{
    /**
     * 权限组列表
     */
    public function index(): JsonResponse
    {
        try {
            $groups = PermissionGroup::with(['users', 'permissions'])->get();
            return $this->success($groups);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建权限组
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        /*if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }*/

        $validate = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:permission_groups,code',
            'description' => 'nullable|string',
        ], [
            'name.required' => '请填写组名称',
            'code.required' => '请填写组编码',
            'code.unique' => '组编码已存在',
        ]);

        try {
            $group = PermissionService::createGroup($validate);
            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新权限组
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
            $group->save();

            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除权限组
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
     * 添加成员
     */
    public function addUser(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        /*if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }*/

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
     * 移除成员
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
     * 添加权限
     */
    public function addPermission(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'permission_code' => 'required|string',
        ], [
            'permission_code.required' => '请填写权限码',
        ]);

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();
            $permission = PermissionService::addPermissionToGroup($uuid, $validate['permission_code']);
            return $this->success($permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 移除权限
     */
    public function removePermission(Request $request, string $uuid, string $code): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            PermissionService::removePermissionFromGroup($uuid, $code);
            return $this->success(null, '移除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
