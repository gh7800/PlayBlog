<?php

namespace App\Http\Controllers;

use App\Models\BlogUser;
use App\Models\Department;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 *User控制器
 */
class UserController extends Controller
{
    //添加user
    public function addUser(Request $request): JsonResponse
    {
        $user = Auth::user();

        $username = $request->input('username');
        $password = $request->input('password');

        $userExists = BlogUser::withTrashed()->where('username', $username)->first();

        if ($userExists) {
            return response()->json([
                'success' => false,
                'message' => 'username已存在1',
                'data' => [
                    'username' => $username
                ]
            ]);
        }

        $data = [
            'username' => $username,
            'password' => bcrypt($password),
            'real_name' => $request->input('real_name', $username)
        ];

        try {
            BlogUser::create($data);

            return response()->json([
                'success' => true,
                'message' => '添加成功',
                'data' => $data
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $data
            ]);
        }
    }

    //编辑
    public function updateUser(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = BlogUser::where('uuid', $uuid)->firstOrFail();

            $updateData = [
                'username' => $request->input('username', $user->username),
                'real_name' => $request->input('real_name', $user->real_name),
            ];

            $companyUuid = $request->input('company_uuid');
            $departmentUuid = $request->input('department_uuid');

            if ($departmentUuid) {
                $department = Department::where('uuid', $departmentUuid)->first();
                if (!$department) {
                    return response()->json([
                        'success' => false,
                        'message' => '部门不存在',
                        'data' => null
                    ]);
                }
                $updateData['department_uuid'] = $departmentUuid;
                $updateData['company_uuid'] = $department->company_uuid;
            } elseif ($companyUuid) {
                $updateData['company_uuid'] = $companyUuid;
                $updateData['department_uuid'] = null;
            }

            $user->update($updateData);
            //$user->load(['company', 'department']);
            $data = $user->toArray();
            $data['company_name'] = $user->company->name ?? null;
            $data['department_name'] = $user->department->name ?? null;
            return response()->json([
                'success' => true,
                'message' => '修改成功！',
                'data' => $data
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null
            ]);
        }
    }

    //获取个人信息
    public function getUserInfo(): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '用户未登录',
                'data' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $user
        ]);
    }

    //获取用户列表
    public function getUserList(Request $request): JsonResponse
    {
        $query = BlogUser::query();

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

        $page = $request->input('page', 1);
        $pageSize = $request->input('page_size', 15);

        $total = $query->count();
        $list = $query->with(['company', 'department'])
            ->orderBy('id', 'desc')
            ->skip(($page - 1) * $pageSize)
            ->take($pageSize)
            ->get()
            ->map(function ($user) {
                return [
                    'uuid' => $user->uuid,
                    'username' => $user->username,
                    'real_name' => $user->real_name,
                    'company_uuid' => $user->company_uuid,
                    'company_name' => $user->company->name ?? null,
                    'department_uuid' => $user->department_uuid,
                    'department_name' => $user->department->name ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => [
                'list' => $list,
                'total' => $total,
                'page' => (int) $page,
                'page_size' => (int) $pageSize,
            ]
        ]);
    }

    //按部门获取用户列表（不分页，树形结构）
    public function getUserListByDepartment(Request $request): JsonResponse
    {
        $companyUuid = $request->input('company_uuid');
        if (!$companyUuid) {
            $companyUuid = $request->user()->company_uuid;
        }
        if (!$companyUuid) {
            return response()->json([
                'success' => false,
                'message' => '请选择公司',
                'data' => null
            ]);
        }

        $rootDepartments = Department::where('company_uuid', $companyUuid)
            ->whereNull('parent_id')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        $departments = $this->buildDepartmentWithUsers($rootDepartments);

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $departments,
        ]);
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
            return response()->json([
                'success' => false,
                'message' => '参数不完整',
                'data' => null
            ]);
        }

        try {
            $user = BlogUser::where('uuid', $userUuid)->firstOrFail();
            $user->update(['push_id' => $pushId]);

            return response()->json([
                'success' => true,
                'message' => '设置成功',
                'data' => $user->refresh()
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => '用户不存在',
                'data' => null
            ]);
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
                return response()->json([
                    'success' => true,
                    'message' => '删除成功',
                    'data' => [
                        'username' => $username
                    ]
                ]);

            } catch (Exception $e) {
                return response()->json([
                    'success' => true,
                    'message' => '删除失败_' . $e->getMessage(),
                    'data' => [
                        'username' => $username
                    ]
                ]);
            }
        } else {
            return response()->json([
                'success' => true,
                'message' => '账号不存在',
                'data' => [
                    'username' => $username
                ]
            ]);
        }
    }

}
