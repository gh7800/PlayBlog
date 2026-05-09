# 编辑用户时同步编辑角色 - 实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在编辑用户时同步支持角色分配/变更，每个用户只能分配一个角色。

**Architecture:** 通过在 `updateUser` 接口增加 `role_uuid` 字段，实现角色变更时先移除原角色再添加新角色的逻辑。新增 `PermissionService::removeUserFromAllGroups` 方法支撑此功能。

**Tech Stack:** Laravel 7.x / PHP

---

## 文件变更

| 文件 | 变更 |
|------|------|
| `app/Services/PermissionService.php` | 新增 `removeUserFromAllGroups` 方法 |
| `app/Http/Controllers/UserController.php` | 修改 `updateUser` 方法，支持 role_uuid |

---

## Task 1: 新增 PermissionService::removeUserFromAllGroups 方法

**Files:**
- Modify: `app/Services/PermissionService.php:88-93` (在 `removeUserFromGroup` 方法后添加)

- [ ] **Step 1: 添加 removeUserFromAllGroups 方法**

在 `PermissionService` 类的 `removeUserFromGroup` 方法之后添加新方法：

```php
/**
 * 移除用户所有角色
 */
public static function removeUserFromAllGroups(string $userUuid): int
{
    return PermissionGroupUser::where('user_uuid', $userUuid)->forceDelete();
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Services/PermissionService.php
git commit -m "feat(permission): add removeUserFromAllGroups method"
```

---

## Task 2: 修改 updateUser 支持 role_uuid

**Files:**
- Modify: `app/Http/Controllers/UserController.php:59-105` (完整替换 updateUser 方法)

- [ ] **Step 1: 替换 updateUser 方法**

将现有的 `updateUser` 方法完整替换为以下实现：

```php
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

        // 处理角色变更
        $roleUuid = $request->input('role_uuid');
        if ($roleUuid !== null) {
            $role = PermissionGroup::where('uuid', $roleUuid)->first();
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => '角色不存在',
                    'data' => null
                ]);
            }
            PermissionService::removeUserFromAllGroups($user->uuid);
            PermissionService::addUserToGroup($roleUuid, $user->uuid);
        }

        $user->update($updateData);
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
```

- [ ] **Step 2: 添加 PermissionGroup 引用**

在文件顶部 `use` 语句区域添加（如果还没有）：

```php
use App\Models\PermissionGroup;
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/UserController.php
git commit -m "feat(user): updateUser支持编辑角色"
```

---

## Task 3: 验证

- [ ] **Step 1: 手动测试接口**

使用 PUT 请求测试 `/api/user/{uuid}`:

1. 不传 role_uuid - 验证现有角色不变
2. 传入存在的 role_uuid - 验证角色已变更
3. 传入不存在的 role_uuid - 验证返回 "角色不存在" 错误

- [ ] **Step 2: Commit**

如果验证通过，提交所有变更。

---

**Plan 完成。两种执行方式：**

**1. Subagent-Driven (推荐)** - 每个 task 由新的 subagent 执行，task 间有检查点

**2. Inline Execution** - 在当前 session 执行，使用 executing-plans skill

选择哪种方式？
