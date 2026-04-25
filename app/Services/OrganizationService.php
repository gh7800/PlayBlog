<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Department;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;

class OrganizationService
{
    // ==================== 公司操作 ====================

    public static function createCompany(array $data): Company
    {
        return Company::create($data);
    }

    public static function updateCompany(string $uuid, array $data): Company
    {
        $company = Company::where('uuid', $uuid)->firstOrFail();
        $company->fill($data);
        $company->save();
        return $company;
    }

    public static function deleteCompany(string $uuid): bool
    {
        $company = Company::where('uuid', $uuid)->firstOrFail();

        self::deleteChildrenCompanies($company->id);
        Department::where('company_uuid', $uuid)->delete();

        return $company->delete();
    }

    private static function deleteChildrenCompanies(int $parentId): void
    {
        $children = Company::where('parent_id', $parentId)->get();
        foreach ($children as $child) {
            self::deleteChildrenCompanies($child->id);
            Department::where('company_uuid', $child->uuid)->delete();
            $child->delete();
        }
    }

    public static function getCompanyByUuid(string $uuid): ?Company
    {
        return Company::where('uuid', $uuid)->first();
    }

    public static function getRootCompanies(): Collection
    {
        return Company::whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public static function getCompanyTree(): Collection
    {
        $rootCompanies = self::getRootCompanies();
        return self::buildCompanyTree($rootCompanies);
    }

    private static function buildCompanyTree(Collection $companies): Collection
    {
        return $companies->map(function ($company) {
            $children = $company->children()
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            return [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'logo' => $company->logo,
                'status' => $company->status,
                'sort' => $company->sort,
                'parent_id' => $company->parent_id,
                'children' => self::buildCompanyTree($children),
            ];
        });
    }

    public static function getAllCompaniesFlat(): Collection
    {
        return Company::orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function ($company) {
                return [
                    'uuid' => $company->uuid,
                    'name' => $company->name,
                    'parent_id' => $company->parent_id,
                ];
            });
    }

    // ==================== 部门操作 ====================

    public static function createDepartment(array $data): Department
    {
        return Department::create($data);
    }

    public static function updateDepartment(string $uuid, array $data): Department
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();
        $department->fill($data);
        $department->save();
        return $department;
    }

    public static function deleteDepartment(string $uuid): bool
    {
        $department = Department::where('uuid', $uuid)->firstOrFail();

        self::deleteChildrenDepartments($department->id);

        return $department->delete();
    }

    private static function deleteChildrenDepartments(int $parentId): void
    {
        $children = Department::where('parent_id', $parentId)->get();
        foreach ($children as $child) {
            self::deleteChildrenDepartments($child->id);
            $child->delete();
        }
    }

    public static function getDepartmentByUuid(string $uuid): ?Department
    {
        return Department::with(['company', 'leader'])->where('uuid', $uuid)->first();
    }

    public static function getDepartmentsByCompany(string $companyUuid): Collection
    {
        return Department::where('company_uuid', $companyUuid)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();
    }

    public static function getDepartmentTreeByCompany(string $companyUuid): Collection
    {
        $rootDepartments = Department::where('company_uuid', $companyUuid)
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return self::buildDepartmentTree($rootDepartments);
    }

    private static function buildDepartmentTree(Collection $departments): Collection
    {
        return $departments->map(function ($department) {
            $children = $department->children()
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            return [
                'uuid' => $department->uuid,
                'name' => $department->name,
                'company_uuid' => $department->company_uuid,
                'leader_id' => $department->leader_id,
                'leader' => $department->leader ? [
                    'uuid' => $department->leader->uuid,
                    'real_name' => $department->leader->real_name,
                ] : null,
                'status' => $department->status,
                'sort' => $department->sort,
                'parent_id' => $department->parent_id,
                'children' => self::buildDepartmentTree($children),
            ];
        });
    }

    public static function getAllDepartmentsFlat(string $companyUuid): Collection
    {
        return Department::where('company_uuid', $companyUuid)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->map(function ($department) {
                return [
                    'uuid' => $department->uuid,
                    'name' => $department->name,
                    'parent_id' => $department->parent_id,
                    'company_uuid' => $department->company_uuid,
                ];
            });
    }

    // ==================== 组织架构树 ====================

    public static function getOrganizationTree(): Collection
    {
        $companies = self::getRootCompanies();

        return $companies->map(function ($company) {
            return [
                'uuid' => $company->uuid,
                'name' => $company->name,
                'logo' => $company->logo,
                'status' => $company->status,
                'type' => 'company',
                'children' => self::buildOrganizationChildren($company),
            ];
        });
    }

    private static function buildOrganizationChildren(Company $company): Collection
    {
        $result = collect();

        $childrenCompanies = $company->children()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($childrenCompanies as $childCompany) {
            $result->push([
                'uuid' => $childCompany->uuid,
                'name' => $childCompany->name,
                'logo' => $childCompany->logo,
                'status' => $childCompany->status,
                'type' => 'company',
                'children' => self::buildOrganizationChildren($childCompany),
            ]);
        }

        $rootDepartments = Department::where('company_uuid', $company->uuid)
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        foreach ($rootDepartments as $department) {
            $result->push([
                'uuid' => $department->uuid,
                'name' => $department->name,
                'company_uuid' => $department->company_uuid,
                'status' => $department->status,
                'type' => 'department',
                'children' => self::buildDepartmentTree($department->children),
            ]);
        }

        return $result;
    }

    // ==================== 辅助方法 ====================

    public static function isDescendantOf(string $companyUuid, string $ancestorUuid): bool
    {
        $company = Company::where('uuid', $companyUuid)->first();
        if (!$company || !$company->parent_id) {
            return false;
        }

        if ($company->parent_id === $ancestorUuid) {
            return true;
        }

        return self::isDescendantOf($company->parent_id, $ancestorUuid);
    }

    public static function canSetParent(string $companyUuid, string $newParentUuid): bool
    {
        if ($companyUuid === $newParentUuid) {
            return false;
        }

        return !self::isDescendantOf($newParentUuid, $companyUuid);
    }
}