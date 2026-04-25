<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Services\OrganizationService;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function index(): JsonResponse
    {
        try {
            $companies = OrganizationService::getAllCompaniesFlat();
            return $this->success($companies);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function tree(): JsonResponse
    {
        try {
            $tree = OrganizationService::getCompanyTree();
            return $this->success($tree);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|string',
            'logo' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
            'sort' => 'nullable|integer|min:0',
        ], [
            'name.required' => '请填写公司名称',
            'name.max' => '公司名称最多255个字符',
            'status.in' => '状态值不正确',
            'sort.integer' => '排序值必须是整数',
            'sort.min' => '排序值不能为负数',
        ]);

        try {
            $company = OrganizationService::createCompany($validate);
            return $this->success($company);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    public function show(string $uuid): JsonResponse
    {
        try {
            $company = OrganizationService::getCompanyByUuid($uuid);
            if (!$company) {
                return $this->error('公司不存在');
            }
            return $this->success($company);
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
            'parent_id' => 'nullable|string',
            'logo' => 'nullable|string|max:500',
            'status' => 'nullable|integer|in:0,1',
            'sort' => 'nullable|integer|min:0',
        ], [
            'name.max' => '公司名称最多255个字符',
            'status.in' => '状态值不正确',
            'sort.integer' => '排序值必须是整数',
            'sort.min' => '排序值不能为负数',
        ]);

        try {
            if (isset($validate['parent_id']) && $validate['parent_id'] !== $uuid) {
                if (!OrganizationService::canSetParent($uuid, $validate['parent_id'])) {
                    return $this->error('不能将自己或下级公司设为上级公司');
                }
            }

            $company = OrganizationService::updateCompany($uuid, $validate);
            return $this->success($company);
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
            OrganizationService::deleteCompany($uuid);
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}