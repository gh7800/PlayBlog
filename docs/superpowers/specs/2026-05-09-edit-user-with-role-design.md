# 编辑用户时同步编辑角色

## 背景

当前编辑用户（`updateUser`）只支持修改用户名、姓名、公司/部门信息，不支持编辑用户所属角色。
角色管理目前是独立的，通过 `RoleController` 的 `addUser`/`removeUser` 接口操作。

## 目标

在编辑用户时同步支持角色分配/变更。

## 约束

- 每个用户只能分配一个角色
- 角色变更操作：先移除原角色分配，再新增新角色

## 接口变更

**请求**

```
PUT /api/user/{uuid}
```

| 字段 | 类型 | 必填 | 说明 |
|------|------|------|------|
| username | string | 否 | 用户名 |
| real_name | string | 否 | 真实姓名 |
| company_uuid | string | 否 | 公司UUID |
| department_uuid | string | 否 | 部门UUID |
| role_uuid | string | 否 | 角色UUID（PermissionGroup.uuid） |

**处理逻辑**

1. 如果传入了 `role_uuid`：
   - 查询角色是否存在，不存在则返回错误
   - 调用 `PermissionService::removeUserFromAllGroups()` 移除该用户所有现有角色
   - 调用 `PermissionService::addUserToGroup()` 添加新角色
2. 如果未传或传 `null`，保持现有角色不变

**响应**：保持现有结构不变

## 实现要点

### 1. 新增 Service 方法

在 `PermissionService` 中新增 `removeUserFromAllGroups` 方法：

```php
public static function removeUserFromAllGroups(string $userUuid): int
{
    return PermissionGroupUser::where('user_uuid', $userUuid)->forceDelete();
}
```

### 2. 修改 updateUser

在 `UserController::updateUser()` 中：

```php
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
```

### 3. 返回值增强（可选）

当前返回值包含 `company_name`、`department_name`，可考虑增加 `role_name` 字段。

## 文件变更

| 文件 | 变更 |
|------|------|
| `app/Services/PermissionService.php` | 新增 `removeUserFromAllGroups` 方法 |
| `app/Http/Controllers/UserController.php` | 修改 `updateUser` 方法，支持 role_uuid |
