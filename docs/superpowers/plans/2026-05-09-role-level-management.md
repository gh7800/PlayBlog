# 角色等级管理实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 为 PermissionGroup 系统添加角色等级功能，高等级（数字小）角色自动继承低等级（数字大）角色的权限。

**Architecture:** 复用现有 PermissionGroup 系统，添加 `level` 字段，在 `PermissionService::userHasPermission()` 中实现等级继承逻辑。

**Tech Stack:** Laravel 7.x, MySQL, Sanctum

---

## 文件结构

```
database/migrations/
  └── 2026_05_09_000004_add_level_to_permission_groups_table.php  (新建)

app/Models/
  └── PermissionGroup.php  (修改)

app/Services/
  └── PermissionService.php  (修改)

database/seeds/
  └── RoleLevelSeeder.php  (新建)
```

---

## 实施步骤

### Task 1: 创建迁移文件

**Files:**
- Create: `database/migrations/2026_05_09_000004_add_level_to_permission_groups_table.php`

- [ ] **Step 1: 创建迁移文件**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLevelToPermissionGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->integer('level')->default(8)->comment('角色等级，数值越小权限越高')->after('description');
            $table->index('level');
        });
    }

    public function down()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
}
```

- [ ] **Step 2: 提交**

```bash
git add database/migrations/2026_05_09_000004_add_level_to_permission_groups_table.php
git commit -m "feat(permission): add level field to permission_groups"
```

---

### Task 2: 修改 PermissionGroup 模型

**Files:**
- Modify: `app/Models/PermissionGroup.php:25`

- [ ] **Step 1: 添加 level 到 fillable**

在 `PermissionGroup` 模型的 `$fillable` 数组中添加 `level`：

```php
protected $fillable = ['uuid', 'name', 'code', 'description', 'level'];
```

- [ ] **Step 2: 提交**

```bash
git add app/Models/PermissionGroup.php
git commit -m "feat(model): add level field to PermissionGroup fillable"
```

---

### Task 3: 扩展 PermissionService

**Files:**
- Modify: `app/Services/PermissionService.php`

- [ ] **Step 1: 添加 userHasDirectPermission 方法**

在 `PermissionService` 开头添加新方法：

```php
/**
 * 检查用户是否有直接分配的权限
 */
public static function userHasDirectPermission(string $userUuid, string $permissionCode): bool
{
    return PermissionGroupUser::where('user_uuid', $userUuid)
        ->whereHas('group.permissions', function ($query) use ($permissionCode) {
            $query->where('permission_code', $permissionCode);
        })
        ->exists();
}
```

- [ ] **Step 2: 修改 userHasPermission 方法**

将原方法替换为带等级继承的版本：

```php
/**
 * 检查用户是否有指定权限（包含等级继承）
 * 高等级用户（level值小）自动拥有低等级用户的权限
 */
public static function userHasPermission(string $userUuid, string $permissionCode): bool
{
    // 直接权限检查
    if (self::userHasDirectPermission($userUuid, $permissionCode)) {
        return true;
    }

    // 获取用户所有角色
    $userGroups = PermissionGroupUser::where('user_uuid', $userUuid)
        ->with('group')
        ->get()
        ->pluck('group')
        ->filter();

    if ($userGroups->isEmpty()) {
        return false;
    }

    // 获取用户最高等级（最小数字）
    $userMaxLevel = $userGroups->min('level');

    // 检查是否存在更高等级（数字更小）的角色拥有该权限
    return PermissionGroupPermission::where('permission_code', $permissionCode)
        ->whereHas('group', function ($query) use ($userMaxLevel) {
            $query->where('level', '<', $userMaxLevel);
        })
        ->exists();
}
```

- [ ] **Step 3: 提交**

```bash
git add app/Services/PermissionService.php
git commit -m "feat(permission): add level inheritance logic to userHasPermission"
```

---

### Task 4: 创建角色种子数据

**Files:**
- Create: `database/seeds/RoleLevelSeeder.php`

- [ ] **Step 1: 创建 RoleLevelSeeder**

```php
<?php

use Illuminate\Database\Seeder;
use App\Models\PermissionGroup;

class RoleLevelSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['name' => '董事长', 'code' => 'role_chairman', 'level' => 1, 'description' => '最高管理者'],
            ['name' => '总经理', 'code' => 'role_general_manager', 'level' => 2, 'description' => '公司总经理'],
            ['name' => '副经理', 'code' => 'role_deputy_manager', 'level' => 3, 'description' => '副总经理'],
            ['name' => '主任', 'code' => 'role_director', 'level' => 4, 'description' => '部门主任'],
            ['name' => '部长', 'code' => 'role_department_head', 'level' => 5, 'description' => '部门部长'],
            ['name' => '科员', 'code' => 'role_staff', 'level' => 6, 'description' => '普通科员'],
            ['name' => '班长', 'code' => 'role_foreman', 'level' => 7, 'description' => '班组班长'],
            ['name' => '副班长', 'code' => 'role_deputy_foreman', 'level' => 8, 'description' => '班组副班长'],
        ];

        foreach ($roles as $role) {
            PermissionGroup::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}
```

- [ ] **Step 2: 注册 Seeder**

在 `database/seeds/DatabaseSeeder.php` 的 `run()` 方法中添加：

```php
public function run()
{
    $this->call([
        RoleLevelSeeder::class,
    ]);
}
```

- [ ] **Step 3: 运行迁移和种子**

```bash
php artisan migrate --path=database/migrations/blogDb --database=mysql
php artisan db:seed --class=RoleLevelSeeder
```

- [ ] **Step 4: 验证种子数据**

```bash
php artisan tinker
>>> App\Models\PermissionGroup::where('code', 'like', 'role_%')->orderBy('level')->get(['name', 'code', 'level'])
```

- [ ] **Step 5: 提交**

```bash
git add database/seeds/RoleLevelSeeder.php database/seeds/DatabaseSeeder.php
git commit -m "feat(seeds): add default role levels"
```

---

### Task 5: 验证测试

- [ ] **Step 1: 测试等级继承逻辑**

```bash
php artisan tinker
```

```php
// 模拟：主任(level=4)应该继承科员(level=6)的权限
// 假设 permission_code = 'car_apply' 被分配给科员(role_staff, level=6)
// 验证主任是否有该权限
$director = App\Models\PermissionGroup::where('code', 'role_director')->first();
$staffPermission = App\Models\PermissionGroupPermission::where('group_uuid', App\Models\PermissionGroup::where('code', 'role_staff')->first()->uuid)->get()->pluck('permission_code');

// 检查主任是否通过继承获得权限（主任level=4 < 科员level=6）
// 由于主任level更小，应该能继承科员的权限
```

```php
// 实际验证
$userUuid = '某个用户uuid'; // 该用户属于主任角色
$hasPermission = App\Services\PermissionService::userHasPermission($userUuid, 'car_apply');
echo $hasPermission ? '有权限' : '无权限';
```

- [ ] **Step 2: 提交**

```bash
git add .
git commit -m "test: verify level inheritance works correctly"
```

---

## 验证检查清单

- [ ] 迁移成功执行，`permission_groups` 表有 `level` 字段
- [ ] 8个角色种子数据创建成功
- [ ] `PermissionGroup::where('code', 'role_director')->first()->level` 返回 `4`
- [ ] 用户属于主任时，`userHasPermission(userUuid, '某权限')` 正确返回结果
