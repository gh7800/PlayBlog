# 用车审批流实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 将用车审批改为配置化，支持多级审批节点

**Architecture:** 新建审批链和节点表，通过 `CarApprovalService` 统一处理审批人获取逻辑，`CarService` 保持业务不变仅调用新服务

**Tech Stack:** Laravel 7.x, MySQL, PermissionService

---

## 文件结构

```
module/Car/
├── Models/
│   ├── CarApprovalChain.php      (新增)
│   └── CarApprovalNode.php       (新增)
├── Services/
│   └── CarApprovalService.php    (新增)
│   └── CarService.php            (修改)
└── DB/Migrations/
    └── 2026_05_09_000001_create_car_approval_chains_table.php  (新增)
    └── 2026_05_09_000002_create_car_approval_nodes_table.php   (新增)
```

---

## Task 1: 创建审批链表迁移文件

**Files:**
- Create: `module/Car/DB/Migrations/2026_05_09_000001_create_car_approval_chains_table.php`

- [ ] **Step 1: 创建迁移文件**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApprovalChainsTable extends Migration
{
    public function up()
    {
        Schema::create('car_approval_chains', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('name', 100)->comment('链表名称');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->tinyInteger('is_active')->default(0)->comment('是否启用 1=启用');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_approval_chains');
    }
}
```

---

## Task 2: 创建审批节点迁移文件

**Files:**
- Create: `module/Car/DB/Migrations/2026_05_09_000002_create_car_approval_nodes_table.php`

- [ ] **Step 1: 创建迁移文件**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApprovalNodesTable extends Migration
{
    public function up()
    {
        Schema::create('car_approval_nodes', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->char('chain_uuid', 36)->comment('所属链表UUID');
            $table->integer('step')->comment('节点顺序');
            $table->string('name', 100)->comment('节点名称');
            $table->enum('approver_type', ['permission_group', 'user', 'dept_head'])
                  ->comment('审批人类型');
            $table->string('approver_value', 255)->comment('审批人值');
            $table->timestamps();

            $table->index('chain_uuid');
            $table->index(['chain_uuid', 'step']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_approval_nodes');
    }
}
```

---

## Task 3: 创建 CarApprovalChain 模型

**Files:**
- Create: `module/Car/Models/CarApprovalChain.php`

- [ ] **Step 1: 创建模型**

```php
<?php

namespace Module\Car\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApprovalChain extends Model
{
    protected $table = 'car_approval_chains';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    public function nodes(): HasMany
    {
        return $this->hasMany(CarApprovalNode::class, 'chain_uuid', 'uuid')
                    ->orderBy('step', 'asc');
    }

    /**
     * 获取当前启用的审批链
     */
    public static function getActiveChain(): ?self
    {
        return self::where('is_active', 1)->first();
    }
}
```

---

## Task 4: 创建 CarApprovalNode 模型

**Files:**
- Create: `module/Car/Models/CarApprovalNode.php`

- [ ] **Step 1: 创建模型**

```php
<?php

namespace Module\Car\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApprovalNode extends Model
{
    protected $table = 'car_approval_nodes';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'chain_uuid', 'step', 'name', 'approver_type', 'approver_value'];

    protected $casts = [
        'step' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    public function chain()
    {
        return $this->belongsTo(CarApprovalChain::class, 'chain_uuid', 'uuid');
    }

    /**
     * 获取当前节点的审批人UUID列表
     */
    public function getApproverUuids($applicant = null): array
    {
        switch ($this->approver_type) {
            case 'permission_group':
                $group = PermissionService::getGroupByCode($this->approver_value);
                if (!$group) {
                    return [];
                }
                return $group->users()->get()->pluck('user_uuid')->toArray();

            case 'user':
                return [$this->approver_value];

            case 'dept_head':
                // TODO: 根据申请人部门获取部门负责人
                return [];

            default:
                return [];
        }
    }
}
```

---

## Task 5: 创建 CarApprovalService

**Files:**
- Create: `module/Car/Services/CarApprovalService.php`

- [ ] **Step 1: 创建服务类**

```php
<?php

namespace Module\Car\Services;

use Module\Car\Models\CarApprovalChain;
use Module\Car\Models\CarApprovalNode;
use Module\Car\Models\CarApplication;

class CarApprovalService
{
    /**
     * 获取当前应该审批的节点
     */
    public function getCurrentNode(CarApplication $application): ?CarApprovalNode
    {
        $chain = CarApprovalChain::getActiveChain();
        if (!$chain) {
            return null;
        }

        return CarApprovalNode::where('chain_uuid', $chain->uuid)
            ->where('step', $application->step)
            ->first();
    }

    /**
     * 获取当前节点的审批人UUID列表
     */
    public function getCurrentApprovers(CarApplication $application): array
    {
        $node = $this->getCurrentNode($application);
        if (!$node) {
            return [];
        }

        return $node->getApproverUuids($application->user);
    }

    /**
     * 检查用户是否是当前节点的审批人
     */
    public function isApprover(CarApplication $application, string $userUuid): bool
    {
        $approverUuids = $this->getCurrentApprovers($application);
        return in_array($userUuid, $approverUuids);
    }

    /**
     * 获取下一个节点
     */
    public function getNextNode(CarApplication $application): ?CarApprovalNode
    {
        $chain = CarApprovalChain::getActiveChain();
        if (!$chain) {
            return null;
        }

        return CarApprovalNode::where('chain_uuid', $chain->uuid)
            ->where('step', '>', $application->step)
            ->orderBy('step', 'asc')
            ->first();
    }

    /**
     * 是否是最后一个节点
     */
    public function isLastNode(CarApplication $application): bool
    {
        return is_null($this->getNextNode($application));
    }

    /**
     * 获取审批链的下一个step
     */
    public function getNextStep(CarApplication $application): int
    {
        $nextNode = $this->getNextNode($application);
        return $nextNode ? $nextNode->step : $application->step;
    }
}
```

---

## Task 6: 修改 CarService - apply 方法

**Files:**
- Modify: `module/Car/Services/CarService.php:15-51`

**变更说明:** `apply` 方法中创建待办时，从配置获取审批人而非硬编码权限组

- [ ] **Step 1: 修改 apply 方法**

找到当前的 `createApproverTask` 方法调用，改为：

```php
// 创建待办给当前节点审批人
$this->createApproverTaskByNode($application, $user);
```

新增 `createApproverTaskByNode` 方法：

```php
/**
 * 根据当前审批节点创建审批待办
 */
private function createApproverTaskByNode(CarApplication $application, $applicant)
{
    $approverService = new CarApprovalService();
    $node = $approverService->getCurrentNode($application);

    if (!$node) {
        Log::warning('createApproverTaskByNode: no active node found');
        return;
    }

    $approverUuids = $node->getApproverUuids();

    foreach ($approverUuids as $userUuid) {
        $user = BlogUser::where('uuid', $userUuid)->first();
        $application->taskLogs()->create([
            'user_uuid' => $userUuid,
            'user_name' => $user->real_name ?? '',
            'status' => CarStatus::APPLYING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
        ]);
    }
}
```

---

## Task 7: 修改 CarService - approve 方法

**Files:**
- Modify: `module/Car/Services/CarService.php:94-153`

**变更说明:** `approve` 方法中检查用户是否是当前节点的审批人

- [ ] **Step 1: 修改 approve 方法中的权限检查**

找到：
```php
if (!PermissionService::userHasPermission($user->uuid, 'car_approver')) {
    return $this->error('无审批权限');
}
```

改为：
```php
$approverService = new CarApprovalService();
if (!$approverService->isApprover($application, $user->uuid)) {
    return $this->error('无审批权限');
}
```

- [ ] **Step 2: 修改审批通过后的流转逻辑**

找到审批通过的处理：
```php
if ($action === 'agree') {
    $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

    $application->update([
        'status' => CarStatus::APPROVED,
        'status_title' => CarStatus::getStatusTitle(CarStatus::APPROVED),
        'step' => 2,
        'approved_plate_id' => $plate->id,
        'approved_plate_number' => $plate->plate_number,
    ]);
    // ...
}
```

改为：
```php
if ($action === 'agree') {
    $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

    if ($approverService->isLastNode($application)) {
        // 最后一个节点，审批通过
        $application->update([
            'status' => CarStatus::APPROVED,
            'status_title' => CarStatus::getStatusTitle(CarStatus::APPROVED),
            'step' => $application->step,
            'approved_plate_id' => $plate->id,
            'approved_plate_number' => $plate->plate_number,
        ]);
    } else {
        // 还有下一节点，流转到下一节点
        $application->update([
            'status' => CarStatus::APPLYING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            'step' => $approverService->getNextStep($application),
            'approved_plate_id' => $plate->id,
            'approved_plate_number' => $plate->plate_number,
        ]);

        // 创建下一节点的待办
        $this->createApproverTaskByNode($application, $application->user);
    }
}
```

---

## Task 8: 添加 CarPlate 状态联动

**Files:**
- Modify: `module/Car/Services/CarService.php` end 方法

**变更说明:** 结束用车时将车牌状态改回空闲

- [ ] **Step 1: 修改 end 方法**

在 `end` 方法中，更新状态后添加：

```php
// 归还车牌，状态改为空闲
if ($application->approved_plate_id) {
    $plate = CarPlate::find($application->approved_plate_id);
    if ($plate) {
        $plate->update(['status' => 0]);
    }
}
```

---

## Task 9: 初始化数据迁移

**Files:**
- Create: `module/Car/DB/Migrations/2026_05_09_000003_seed_car_approval_chain.php`

- [ ] **Step 1: 创建初始化数据迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedCarApprovalChain extends Migration
{
    public function up()
    {
        // 插入默认审批链
        $chainUuid = 'car_chain_default';
        DB::table('car_approval_chains')->insert([
            'uuid' => $chainUuid,
            'name' => '用车审批链',
            'description' => '默认用车审批流程',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 插入默认节点（行办审批）
        DB::table('car_approval_nodes')->insert([
            'uuid' => 'car_node_1',
            'chain_uuid' => $chainUuid,
            'step' => 1,
            'name' => '行办审批',
            'approver_type' => 'permission_group',
            'approver_value' => 'car_approver',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        DB::table('car_approval_nodes')->where('uuid', 'car_node_1')->delete();
        DB::table('car_approval_chains')->where('uuid', $chainUuid)->delete();
    }
}
```

---

## Task 10: 运行迁移验证

- [ ] **Step 1: 运行迁移**

```bash
php artisan migrate --path=module/Car/DB/Migrations --database=mysql
```

- [ ] **Step 2: 检查表是否创建成功**

```bash
php artisan migrate:status --path=module/Car/DB/Migrations --database=mysql
```

预期输出应包含：
- car_approval_chains
- car_approval_nodes
- seed_car_approval_chain

---

## Task 11: 功能测试

- [ ] **Step 1: 提交申请测试**

```bash
curl -X POST http://localhost:8000/api/car/apply \
  -H "Authorization: Bearer {token}" \
  -d '{"car_type":"general","reason":"测试","passenger_count":2,"use_time":"2026-05-10 09:00"}'
```

预期：`taskLogs` 应包含 `car_approver` 权限组成员的UUID

- [ ] **Step 2: 审批测试（审批人）**

```bash
curl -X POST http://localhost:8000/api/car/approve \
  -H "Authorization: Bearer {approver_token}" \
  -d '{"uuid":"{application_uuid}","action":"agree","plate_id":"{plate_uuid}"}'
```

预期：状态变为 `approved`（只有一个节点时）

- [ ] **Step 3: 非审批人无法审批**

```bash
curl -X POST http://localhost:8000/api/car/approve \
  -H "Authorization: Bearer {non_approver_token}" \
  -d '{"uuid":"{application_uuid}","action":"agree","plate_id":"{plate_uuid}"}'
```

预期：返回错误"无审批权限"

- [ ] **Step 4: 结束用车测试**

```bash
curl -X POST http://localhost:8000/api/car/end/{uuid} \
  -H "Authorization: Bearer {token}" \
  -d '{"start_km":100,"end_km":150}'
```

预期：状态变为 `completed`，车牌 `status` 变为 0

---

## 变更摘要

| 文件 | 操作 |
|------|------|
| `module/Car/DB/Migrations/2026_05_09_000001_create_car_approval_chains_table.php` | 创建 |
| `module/Car/DB/Migrations/2026_05_09_000002_create_car_approval_nodes_table.php` | 创建 |
| `module/Car/DB/Migrations/2026_05_09_000003_seed_car_approval_chain.php` | 创建 |
| `module/Car/Models/CarApprovalChain.php` | 创建 |
| `module/Car/Models/CarApprovalNode.php` | 创建 |
| `module/Car/Services/CarApprovalService.php` | 创建 |
| `module/Car/Services/CarService.php` | 修改 |
