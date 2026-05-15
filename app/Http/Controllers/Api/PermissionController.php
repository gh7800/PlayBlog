<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends ApiController
{
    /**
     * 权限列表（支持 tree / flat 两种返回格式）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Permission::query();

            if ($code = $request->input('code')) {
                $query->where('code', 'like', "%{$code}%");
            }
            if ($name = $request->input('name')) {
                $query->where('name', 'like', "%{$name}%");
            }
            if ($type = $request->input('type')) {
                $query->where('type', $type);
            }
            if ($module = $request->input('module')) {
                $query->where('module', $module);
            }

            $permissions = $query->get();

            // 扁平结构：?tree=0 或 ?flat=1
            if ($request->boolean('flat') || $request->input('tree') === '0') {
                return $this->success($permissions->values());
            }

            // 按 module + type 分组为 tree 结构
            $groupedByModule = $permissions->groupBy('module');

            $tree = [];
            foreach ($groupedByModule as $module => $items) {
                $groupedByType = $items->groupBy('type');
                $children = [];

                if ($groupedByType->has('page')) {
                    $children[] = [
                        'type' => 'page',
                        'label' => '页面权限',
                        'children' => $groupedByType->get('page')->values(),
                    ];
                }
                if ($groupedByType->has('function')) {
                    $children[] = [
                        'type' => 'function',
                        'label' => '功能权限',
                        'children' => $groupedByType->get('function')->values(),
                    ];
                }

                $tree[] = [
                    'module' => $module,
                    'children' => $children,
                ];
            }

            return $this->success(array_values($tree));
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建权限
     */
    public function store(Request $request): JsonResponse
    {
        /*if (!PermissionService::userHasPermission($request->user()->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }*/

        $validate = $request->validate([
            'code' => 'required|string|unique:permissions,code',
            'name' => 'required|string',
            'type' => 'required|string|in:page,function',
            'module' => 'required|string',
            'description' => 'nullable|string',
        ], [
            'code.required' => '请填写权限码',
            'code.unique' => '权限码已存在',
            'name.required' => '请填写权限名称',
            'type.required' => '请选择权限类型',
            'type.in' => '权限类型必须是 page(页面权限) 或 function(功能权限)',
            'module.required' => '请填写所属模块',
        ]);

        try {
            $permission = Permission::create($validate);
            return $this->success($permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 编辑权限
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        if (!PermissionService::userHasPermission($request->user()->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'code' => 'string|unique:permissions,code,' . $uuid . ',uuid',
            'name' => 'string',
            'type' => 'string|in:page,function',
            'module' => 'string',
            'description' => 'nullable|string',
        ]);

        try {
            $permission = Permission::where('uuid', $uuid)->firstOrFail();
            $permission->fill($validate);
            $permission->save();
            return $this->success($permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除权限
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        if (!PermissionService::userHasPermission($request->user()->uuid, 'organization_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $permission = Permission::where('uuid', $uuid)->firstOrFail();
            $permission->delete();
            return $this->success(null, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
