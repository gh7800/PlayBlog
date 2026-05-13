<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use App\Models\Department;
use App\Models\PermissionGroup;
use Exception;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 *User控制器
 */
class UserController extends ApiController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    //添加user
    public function addUser(Request $request): JsonResponse
    {
        $username = $request->input('username');
        $password = $request->input('password');

        $userExists = BlogUser::withTrashed()->where('username', $username)->first();

        if ($userExists) {
            return $this->error('username已存在1', ['username' => $username]);
        }

        $data = [
            'username' => $username,
            'password' => bcrypt($password),
            'real_name' => $request->input('real_name', $username),
            'phone' => $request->input('phone'),
            'email' => $request->input('email'),
            'address' => $request->input('address'),
            'status' => $request->input('status', 1),
        ];

        $departmentUuid = $request->input('department_uuid');
        if ($departmentUuid) {
            $department = Department::where('uuid', $departmentUuid)->first();
            if (!$department) {
                return $this->error('部门不存在');
            }
            $data['department_uuid'] = $departmentUuid;
            $data['company_uuid'] = $department->company_uuid;
        } elseif ($request->has('company_uuid')) {
            $data['company_uuid'] = $request->input('company_uuid');
        }

        if ($request->has('role_uuid')) {
            $roleUuid = $request->input('role_uuid');
            $role = PermissionGroup::where('uuid', $roleUuid)->first();
            if (!$role) {
                return $this->error('角色不存在');
            }
            $data['role_uuid'] = $roleUuid;
        }

        try {
            $user = BlogUser::create($data);

            return $this->success($user->fresh()->load(['company', 'department', 'role']), '添加成功');
        } catch (Exception $exception) {
            return $this->error($exception->getMessage(), $data);
        }
    }

    //编辑
    public function updateUser(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = BlogUser::where('uuid', $uuid)->firstOrFail();

            $updateData = [];
            foreach (['username', 'real_name', 'phone', 'email', 'address', 'status'] as $field) {
                if ($request->has($field)) {
                    $updateData[$field] = $request->input($field);
                }
            }

            $departmentUuid = $request->input('department_uuid');
            if ($departmentUuid) {
                $department = Department::where('uuid', $departmentUuid)->first();
                if (!$department) {
                    return $this->error('部门不存在');
                }
                $updateData['department_uuid'] = $departmentUuid;
                $updateData['company_uuid'] = $department->company_uuid;
            } elseif ($request->has('company_uuid')) {
                $updateData['company_uuid'] = $request->input('company_uuid');
                $updateData['department_uuid'] = null;
            }

            if ($request->has('role_uuid')) {
                $roleUuid = $request->input('role_uuid');
                $role = PermissionGroup::where('uuid', $roleUuid)->first();
                if (!$role) {
                    return $this->error('角色不存在');
                }
                $updateData['role_uuid'] = $roleUuid;
            }

            if (!empty($updateData)) {
                $user->update($updateData);
            }

            $user->load(['company', 'department', 'role']);
            $data = $user->toArray();
            $data['company_name'] = $user->company->name ?? null;
            $data['department_name'] = $user->department->name ?? null;
            $data['role_name'] = $user->role->name ?? null;
            return $this->success($data, '修改成功！');
        } catch (Exception $exception) {
            return $this->error($exception->getMessage());
        }
    }

    //获取个人信息
    public function getUserInfo(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->error('用户未登录');
        }

        return $this->success($user, '获取成功');
    }

    //获取用户列表
    public function getUserList(Request $request): JsonResponse
    {
        $query = BlogUser::with(['company', 'department', 'role']);

        if ($request->filled('company_uuid')) {
            $query->where('company_uuid', $request->input('company_uuid'));
        }

        if ($request->filled('department_uuid')) {
            $query->where('department_uuid', $request->input('department_uuid'));
        }

        if ($request->filled('username')) {
            $query->where('username', 'like', '%' . $request->input('username') . '%');
        }

        if ($request->filled('real_name')) {
            $query->where('real_name', 'like', '%' . $request->input('real_name') . '%');
        }

        $pageSize = $request->input('page_size', 15);
        $paginator = $query->orderBy('username', 'asc')->paginate($pageSize);

        return $this->successPaginator($paginator->items(), $paginator);
    }

    //按部门获取用户列表（不分页，树形结构）
    public function getUserListByDepartment(Request $request): JsonResponse
    {
        $companyUuid = $request->input('company_uuid');
        if (!$companyUuid) {
            $companyUuid = $request->user()->company_uuid;
        }
        if (!$companyUuid) {
            return $this->error('请选择公司');
        }

        $rootDepartments = Department::where('company_uuid', $companyUuid)
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $departments = $this->buildDepartmentWithUsers($rootDepartments);

        return $this->success($departments, '获取成功');
    }

    private function buildDepartmentWithUsers($departments)
    {
        return $departments->map(function ($dept) {
            $children = $dept->children()
                ->orderBy('sort')
                ->orderBy('id')
                ->get();

            return [
                'uuid' => $dept->uuid,
                'name' => $dept->name,
                'parent_id' => $dept->parent_id,
                'users' => $dept->users->map(function ($user) {
                    return [
                        'uuid' => $user->uuid,
                        'username' => $user->username,
                        'real_name' => $user->real_name,
                    ];
                }),
                'children' => $this->buildDepartmentWithUsers($children),
            ];
        });
    }

    //设置推送Id
    public function setPushId(Request $request): JsonResponse
    {
        $userUuid = $request->input('user_uuid');
        $pushId = $request->input('push_id');

        if (empty($userUuid) || empty($pushId)) {
            return $this->error('参数不完整');
        }

        try {
            $user = BlogUser::where('uuid', $userUuid)->firstOrFail();
            $user->update(['push_id' => $pushId]);

            return $this->success($user->refresh(), '设置成功');
        } catch (Exception $exception) {
            return $this->error('用户不存在');
        }
    }

    //删除单个
    public function deleteUser(Request $request): JsonResponse
    {
        $username = $request->input('username');

        $users = BlogUser::withTrashed()->where('username', $username);

        if ($users->exists()) {
            try {
                $users->forceDelete();
                return $this->success(['username' => $username], '删除成功');
            } catch (Exception $e) {
                return $this->success(['username' => $username], '删除失败_' . $e->getMessage());
            }
        } else {
            return $this->success(['username' => $username], '账号不存在');
        }
    }

}
