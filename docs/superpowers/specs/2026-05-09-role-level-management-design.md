# 角色等级管理设计

## 背景

系统需要支持角色管理（如董事长、总经理、副经理、主任、部长、科员、班长、副班长等），角色具有等级属性，高等级角色自动继承低等级角色的权限。

## 现有系统

项目已有 `PermissionGroup` 权限组系统，支持：
- 权限组的 CRUD
- 用户与权限组的关联
- 权限与权限组的关联
- 软删除、多租户隔离

## 设计方案

### 1. 数据库扩展

在 `permission_groups` 表添加 `level` 字段：

```php
$table->integer('level')->default(8)->comment('角色等级，数值越小权限越高');
```

### 2. 角色等级定义

数值越小等级越高，权限越多。

| 角色 | code | level |
|------|------|-------|
| 董事长 | role_chairman | 1 |
| 总经理 | role_general_manager | 2 |
| 副经理 | role_deputy_manager | 3 |
| 主任 | role_director | 4 |
| 部长 | role_department_head | 5 |
| 科员 | role_staff | 6 |
| 班长 | role_foreman | 7 |
| 副班长 | role_deputy_foreman | 8 |

### 3. 业务逻辑

在 `PermissionService` 中扩展 `userHasPermission` 方法：

- 用户拥有某权限 = 直接分配 OR 拥有更高等级角色
- 高等级角色自动继承低等级角色的权限

```php
public static function userHasPermission(string $userUuid, string $permissionCode): bool
{
    // 直接权限检查
    if (self::userHasDirectPermission($userUuid, $permissionCode)) {
        return true;
    }

    // 获取用户最高角色等级
    $userGroups = PermissionGroupUser::where('user_uuid', $userUuid)
        ->with('group')
        ->get()
        ->pluck('group');

    $userMaxLevel = $userGroups->min('level') ?? PHP_INT_MAX;

    // 检查是否存在更高等级角色拥有该权限（更高等级 = 更小数字）
    return PermissionGroupPermission::where('permission_code', $permissionCode)
        ->whereHas('group', fn($q) => $q->where('level', '<', $userMaxLevel))
        ->exists();
}
```

### 4. API（复用现有）

| 功能 | API |
|------|-----|
| 角色列表 | GET /api/group |
| 创建角色 | POST /api/group |
| 编辑角色 | PUT /api/group/{uuid} |
| 删除角色 | DELETE /api/group/{uuid} |
| 分配用户到角色 | POST /api/group/{uuid}/user |
| 从角色移除用户 | DELETE /api/group/{uuid}/user/{userUuid} |

### 5. 种子数据

提供数据库种子命令，预置8个基础角色。

## 扩展方向

- 添加 `parent_id` 支持树形层级结构
- 角色权限配置界面
- 角色代管、代理权限

## 实施步骤

1. 创建数据库迁移，添加 `level` 字段
2. 修改 `PermissionGroup` 模型，添加 `level` 到 fillable
3. 扩展 `PermissionService::userHasPermission()` 方法
4. 创建角色种子数据
5. 测试验证
