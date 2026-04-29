<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Services\OrganizationService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->input('company_uuid');
            if (!$companyUuid) {
                $companyUuid = $request->user()->company_uuid;
            }
            if (!$companyUuid) {
                return $this->error('请选择公司');
            }
            $departments = OrganizationService::getAllDepartmentsFlat($companyUuid);
            return $this->success($departments);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function tree(Request $request): JsonResponse
    {
        try {
            $companyUuid = $request->input('company_uuid');
            if (!$companyUuid) {
                $companyUuid = $request->user()->company_uuid;
            }
            if (!$companyUuid) {
                return $this->error('请选择公司');
            }
            $tree = OrganizationService::getDepartmentTreeByCompany($companyUuid);
            return $this->success($tree);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $user_uuid = $user->uuid;
        if (!PermissionService::userHasPermission($user_uuid, 'organization_admin')) {
            return $this->error("无管理权限_$user_uuid");
        }

        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'company_uuid' => 'required|string',
            'parent_id' => 'nullable|integer',
            'leader_id' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
            'sort' => 'nullable|integer|min:0',
        ], [
            'name.required' => '请填写部门名称',
            'name.max' => '部门名称最多255个字符',
            'company_uuid.required' => '请选择所属公司',
            'status.in' => '状态值不正确',
            'sort.integer' => '排序值必须是整数',
            'sort.min' => '排序值不能为负数',
        ]);

        try {
            $department = OrganizationService::createDepartment($validate);
            return $this->success($department);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $department = OrganizationService::getDepartmentByUuid($uuid);
            if (!$department) {
                return $this->error('部门不存在');
            }
            return $this->success($department);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'name' => 'nullable|string|max:255',
            'parent_id' => 'nullable|integer',
            'leader_id' => 'nullable|string',
            'status' => 'nullable|integer|in:0,1',
            'sort' => 'nullable|integer|min:0',
        ], [
            'name.max' => '部门名称最多255个字符',
            'status.in' => '状态值不正确',
            'sort.integer' => '排序值必须是整数',
            'sort.min' => '排序值不能为负数',
        ]);

        try {
            $department = OrganizationService::updateDepartment($uuid, $validate);
            return $this->success($department);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            OrganizationService::deleteDepartment($uuid);
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
