# 用车管理模块实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 实现完整的用车管理模块，包括用车申请、行办审批、结束用车、车牌管理、权限组管理

**Architecture:** 基于 Document 模块的架构模式，使用 Laravel 的 morph 关联实现日志和待办任务的复用，通过 CarService 处理业务逻辑

**Tech Stack:** Laravel 7.x, PHP 7.2.5+/8.0+, Laravel Sanctum, MySQL

---

## 文件结构

```
module/Car/
├── API/
│   ├── CarPlateController.php          # 车牌管理
│   ├── CarApplyController.php           # 用车申请
│   ├── CarApproveController.php        # 用车审批
│   ├── CarEndController.php            # 结束用车
│   └── PermissionGroupController.php    # 权限组管理
├── Models/
│   ├── CarApplication.php              # 用车申请
│   ├── CarPlate.php                    # 车牌
│   ├── CarLog.php                      # 用车日志（关联 approval_logs）
│   ├── CarTaskLog.php                  # 用车待办（关联 document_task_logs）
│   ├── PermissionGroup.php             # 权限组
│   ├── PermissionGroupUser.php         # 组成员
│   └── PermissionGroupPermission.php   # 组权限
├── Services/
│   ├── CarService.php                  # 用车业务逻辑
│   └── PermissionService.php           # 权限组业务逻辑
├── Enums/
│   └── CarStatus.php                   # 用车状态枚举
└── DB/Migrations/
    ├── xxxx_create_car_applications_table.php
    ├── xxxx_create_car_plates_table.php
    ├── xxxx_create_permission_groups_table.php
    ├── xxxx_create_permission_group_users_table.php
    └── xxxx_create_permission_group_permissions_table.php

module/Document/Models/ 需要添加关联（如果尚未支持 Car 模块）
```

---

## Task 1: 创建枚举类 CarStatus

**Files:**
- Create: `module/Car/Enums/CarStatus.php`

- [ ] **Step 1: 创建枚举文件**

```php
<?php

namespace Module\Car\Enums;

class CarStatus
{
    const APPLYING = 'applying';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const ONGOING = 'ongoing';
    const COMPLETED = 'completed';

    public static function getStatusTitle($status): string
    {
        $titles = [
            self::APPLYING => '申请中',
            self::APPROVED => '已同意',
            self::REJECTED => '已拒绝',
            self::ONGOING => '用车中',
            self::COMPLETED => '已完成',
        ];
        return $titles[$status] ?? $status;
    }

    public static function getCarTypeTitle($type): string
    {
        $titles = [
            'general' => '一般用车',
            'business' => '业务用车',
            'other' => '其他',
        ];
        return $titles[$type] ?? $type;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add module/Car/Enums/CarStatus.php
git commit -m "feat(car): 添加用车状态枚举"
```

---

## Task 2: 创建 Model 类

**Files:**
- Create: `module/Car/Models/CarApplication.php`
- Create: `module/Car/Models/CarPlate.php`
- Create: `module/Car/Models/CarLog.php`
- Create: `module/Car/Models/CarTaskLog.php`
- Create: `module/Car/Models/PermissionGroup.php`
- Create: `module/Car/Models/PermissionGroupUser.php`
- Create: `module/Car/Models/PermissionGroupPermission.php`

- [ ] **Step 1: 创建 CarApplication 模型**

```php
<?php

namespace Module\Car\Models;

use App\Models\Next;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApplication extends Model
{
    protected $table = 'car_applications';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = [
        'uuid', 'user_uuid', 'user_name', 'car_type', 'reason',
        'passenger_count', 'use_time', 'remark', 'status',
        'status_title', 'step', 'approved_plate_id', 'approved_plate_number',
        'reject_reason', 'start_km', 'end_km'
    ];

    protected $casts = [
        'step' => 'integer',
        'passenger_count' => 'integer',
        'start_km' => 'decimal:2',
        'end_km' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(CarLog::class, 'approvalLog');
    }

    public function taskLogs(): MorphMany
    {
        return $this->morphMany(CarTaskLog::class, 'taskLog');
    }

    public function next(): MorphMany
    {
        return $this->morphMany(Next::class, 'nextTable');
    }

    public function plate(): BelongsTo
    {
        return $this->belongsTo(CarPlate::class, 'approved_plate_id');
    }
}
```

- [ ] **Step 2: 创建 CarPlate 模型**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarPlate extends Model
{
    protected $table = 'car_plates';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'plate_number', 'description', 'status'];

    protected $casts = [
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
```

- [ ] **Step 3: 创建 CarLog 模型（关联 approval_logs）**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarLog extends Model
{
    protected $table = 'approval_logs';

    protected $fillable = ['approvalLog_id', 'approvalLog_type', 'user_name', 'user_uuid', 'reply', 'status', 'status_title', 'result', 'step'];

    protected $casts = [
        'result' => 'integer',
        'step' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public $timestamps = true;
    use SoftDeletes;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function approvalLog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'approvalLog_type', 'approvalLog_id');
    }
}
```

- [ ] **Step 4: 创建 CarTaskLog 模型（关联 document_task_logs）**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarTaskLog extends Model
{
    protected $table = 'document_task_logs';

    protected $fillable = ['taskLog_id', 'taskLog_type', 'user_uuid', 'user_name', 'status', 'status_title'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public $timestamps = true;
    use SoftDeletes;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function taskLog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'taskLog_type', 'taskLog_id');
    }
}
```

- [ ] **Step 5: 创建 PermissionGroup 模型**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class PermissionGroup extends Model
{
    protected $table = 'permission_groups';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'name', 'code', 'description'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function users(): HasMany
    {
        return $this->hasMany(PermissionGroupUser::class, 'group_uuid');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(PermissionGroupPermission::class, 'group_uuid');
    }
}
```

- [ ] **Step 6: 创建 PermissionGroupUser 模型**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class PermissionGroupUser extends Model
{
    protected $table = 'permission_group_users';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'group_uuid', 'user_uuid'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
```

- [ ] **Step 7: 创建 PermissionGroupPermission 模型**

```php
<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class PermissionGroupPermission extends Model
{
    protected $table = 'permission_group_permissions';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'group_uuid', 'permission_code'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
```

- [ ] **Step 8: Commit**

```bash
git add module/Car/Models/
git commit -m "feat(car): 添加 Car 模块所有 Model"
```

---

## Task 3: 创建数据库迁移

**Files:**
- Create: `module/Car/DB/Migrations/xxxx_create_car_applications_table.php`
- Create: `module/Car/DB/Migrations/xxxx_create_car_plates_table.php`
- Create: `module/Car/DB/Migrations/xxxx_create_permission_groups_table.php`
- Create: `module/Car/DB/Migrations/xxxx_create_permission_group_users_table.php`
- Create: `module/Car/DB/Migrations/xxxx_create_permission_group_permissions_table.php`

- [ ] **Step 1: 创建 car_applications 迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('car_applications', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('user_uuid');
            $table->string('user_name');
            $table->enum('car_type', ['general', 'business', 'other'])->comment='用车类型';
            $table->text('reason')->comment='用车事由';
            $table->integer('passenger_count')->comment='用车人数';
            $table->datetime('use_time')->comment='用车时间';
            $table->text('remark')->nullable()->comment='备注';
            $table->string('status')->default('applying')->comment='状态';
            $table->string('status_title')->comment='状态中文';
            $table->integer('step')->default(1)->comment='步骤';
            $table->bigInteger('approved_plate_id')->nullable()->comment='审批车牌ID';
            $table->string('approved_plate_number')->nullable()->comment='审批车牌号';
            $table->text('reject_reason')->nullable()->comment='拒绝原因';
            $table->decimal('start_km', 10, 2)->nullable()->comment='开始公里数';
            $table->decimal('end_km', 10, 2)->nullable()->comment='结束公里数';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('user_uuid');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_applications');
    }
}
```

- [ ] **Step 2: 创建 car_plates 迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarPlatesTable extends Migration
{
    public function up()
    {
        Schema::create('car_plates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('plate_number')->unique()->comment='车牌号';
            $table->text('description')->nullable()->comment='描述';
            $table->tinyInteger('status')->default(0)->comment='状态：0可用，1不可用';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_plates');
    }
}
```

- [ ] **Step 3: 创建 permission_groups 迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('name')->comment='组名称';
            $table->string('code')->unique()->comment='编码';
            $table->text('description')->nullable()->comment='描述';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_groups');
    }
}
```

- [ ] **Step 4: 创建 permission_group_users 迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupUsersTable extends Migration
{
    public function up()
    {
        Schema::create('permission_group_users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('group_uuid');
            $table->string('user_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('group_uuid');
            $table->index('user_uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_group_users');
    }
}
```

- [ ] **Step 5: 创建 permission_group_permissions 迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupPermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('permission_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('group_uuid');
            $table->string('permission_code')->comment='权限码';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('group_uuid');
            $table->index('permission_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_group_permissions');
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add module/Car/DB/Migrations/
git commit -m "feat(car): 添加 Car 模块数据库迁移"
```

---

## Task 4: 创建 Service 类

**Files:**
- Create: `module/Car/Services/CarService.php`
- Create: `module/Car/Services/PermissionService.php`

- [ ] **Step 1: 创建 CarService**

```php
<?php

namespace Module\Car\Services;

use App\Models\BlogUser;
use Illuminate\Http\Request;
use Module\Car\Enums\CarStatus;
use Module\Car\Models\CarApplication;
use Module\Car\Models\CarPlate;

class CarService
{
    /**
     * 申请用车
     */
    public function apply(Request $request): CarApplication
    {
        $user = $request->user();

        $data = [
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'car_type' => $request->input('car_type'),
            'reason' => $request->input('reason'),
            'passenger_count' => $request->input('passenger_count'),
            'use_time' => $request->input('use_time'),
            'remark' => $request->input('remark', ''),
            'status' => CarStatus::APPLYING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            'step' => 1,
        ];

        $application = CarApplication::create($data);

        // 创建待办给行办审批人员
        $this->createApproverTask($application, $user);

        // 记录日志
        $application->logs()->create([
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'status' => CarStatus::APPLYING,
            'status_title' => '提交申请',
            'result' => 1,
            'step' => 1,
        ]);

        return $application->load(['logs', 'taskLogs', 'next']);
    }

    /**
     * 创建审批待办
     */
    private function createApproverTask(CarApplication $application, $applicant)
    {
        $approverGroup = PermissionService::getGroupByCode('car_approver');
        if (!$approverGroup) {
            return;
        }

        $approverUsers = $approverGroup->users()->get();
        foreach ($approverUsers as $approverUser) {
            $application->taskLogs()->create([
                'user_uuid' => $approverUser->user_uuid,
                'user_name' => $approverUser->user->real_name ?? '',
                'status' => CarStatus::APPLYING,
                'status_title' => CarStatus::getStatusTitle(CarStatus::APPLYING),
            ]);
        }
    }

    /**
     * 审批用车
     */
    public function approve(Request $request): CarApplication
    {
        $user = $request->user();
        $uuid = $request->input('uuid');
        $action = $request->input('action'); // agree, reject
        $plateId = $request->input('plate_id');
        $reply = $request->input('reply', '');

        $application = CarApplication::where('uuid', $uuid)->firstOrFail();

        if ($action === 'agree') {
            // 同意
            $plate = CarPlate::where('uuid', $plateId)->firstOrFail();

            $application->update([
                'status' => CarStatus::APPROVED,
                'status_title' => CarStatus::getStatusTitle(CarStatus::APPROVED),
                'step' => 2,
                'approved_plate_id' => $plate->id,
                'approved_plate_number' => $plate->plate_number,
            ]);

            $application->logs()->create([
                'user_uuid' => $user->uuid,
                'user_name' => $user->real_name,
                'status' => CarStatus::APPROVED,
                'status_title' => '同意',
                'reply' => $reply,
                'result' => 1,
                'step' => 2,
            ]);

            // 清除待办
            $application->taskLogs()->forceDelete();

        } else if ($action === 'reject') {
            // 拒绝
            $application->update([
                'status' => CarStatus::REJECTED,
                'status_title' => CarStatus::getStatusTitle(CarStatus::REJECTED),
                'reject_reason' => $reply,
                'step' => -1,
            ]);

            $application->logs()->create([
                'user_uuid' => $user->uuid,
                'user_name' => $user->real_name,
                'status' => CarStatus::REJECTED,
                'status_title' => '拒绝',
                'reply' => $reply,
                'result' => -1,
                'step' => -1,
            ]);

            // 清除待办
            $application->taskLogs()->forceDelete();
        }

        return $application->refresh()->load(['logs']);
    }

    /**
     * 开始用车（状态变为进行中）
     */
    public function start(Request $request, string $uuid): CarApplication
    {
        $application = CarApplication::where('uuid', $uuid)->firstOrFail();

        $application->update([
            'status' => CarStatus::ONGOING,
            'status_title' => CarStatus::getStatusTitle(CarStatus::ONGOING),
        ]);

        return $application->refresh();
    }

    /**
     * 结束用车
     */
    public function end(Request $request, string $uuid): CarApplication
    {
        $user = $request->user();
        $startKm = $request->input('start_km');
        $endKm = $request->input('end_km');

        $application = CarApplication::where('uuid', $uuid)
            ->where('user_uuid', $user->uuid)
            ->firstOrFail();

        if ($endKm <= $startKm) {
            throw new \Exception('结束公里数必须大于开始公里数');
        }

        $application->update([
            'status' => CarStatus::COMPLETED,
            'status_title' => CarStatus::getStatusTitle(CarStatus::COMPLETED),
            'start_km' => $startKm,
            'end_km' => $endKm,
        ]);

        $application->logs()->create([
            'user_uuid' => $user->uuid,
            'user_name' => $user->real_name,
            'status' => CarStatus::COMPLETED,
            'status_title' => '结束用车',
            'reply' => "开始公里数: {$startKm}, 结束公里数: {$endKm}",
            'result' => 1,
            'step' => 3,
        ]);

        return $application->refresh()->load(['logs']);
    }

    /**
     * 获取申请列表
     */
    public function list(Request $request)
    {
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);
        $keyword = $request->input('keyword');

        $query = CarApplication::query();

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('reason', 'like', "%{$keyword}%")
                    ->orWhere('user_name', 'like', "%{$keyword}%");
            });
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * 待处理列表（行办）
     */
    public function todoList(Request $request)
    {
        $user = $request->user();

        return CarApplication::whereHas('taskLogs', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        })
            ->with(['taskLogs'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * 已处理列表（行办）
     */
    public function processedList(Request $request)
    {
        $user = $request->user();

        return CarApplication::whereHas('logs', function ($query) use ($user) {
            $query->where('user_uuid', $user->uuid);
        })
            ->with(['logs'])
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
```

- [ ] **Step 2: 创建 PermissionService**

```php
<?php

namespace Module\Car\Services;

use Module\Car\Models\PermissionGroup;
use Module\Car\Models\PermissionGroupUser;
use Module\Car\Models\PermissionGroupPermission;

class PermissionService
{
    /**
     * 根据 code 获取权限组
     */
    public static function getGroupByCode(string $code): ?PermissionGroup
    {
        return PermissionGroup::where('code', $code)->first();
    }

    /**
     * 检查用户是否有指定权限
     */
    public static function userHasPermission(string $userUuid, string $permissionCode): bool
    {
        return PermissionGroupUser::where('user_uuid', $userUuid)
            ->whereHas('group.permissions', function ($query) use ($permissionCode) {
                $query->where('permission_code', $permissionCode);
            })
            ->exists();
    }

    /**
     * 创建权限组
     */
    public static function createGroup(array $data): PermissionGroup
    {
        return PermissionGroup::create($data);
    }

    /**
     * 添加成员到组
     */
    public static function addUserToGroup(string $groupUuid, string $userUuid): PermissionGroupUser
    {
        return PermissionGroupUser::firstOrCreate([
            'group_uuid' => $groupUuid,
            'user_uuid' => $userUuid,
        ]);
    }

    /**
     * 从组移除成员
     */
    public static function removeUserFromGroup(string $groupUuid, string $userUuid): bool
    {
        return PermissionGroupUser::where('group_uuid', $groupUuid)
            ->where('user_uuid', $userUuid)
            ->delete() > 0;
    }

    /**
     * 添加权限到组
     */
    public static function addPermissionToGroup(string $groupUuid, string $permissionCode): PermissionGroupPermission
    {
        return PermissionGroupPermission::firstOrCreate([
            'group_uuid' => $groupUuid,
            'permission_code' => $permissionCode,
        ]);
    }

    /**
     * 从组移除权限
     */
    public static function removePermissionFromGroup(string $groupUuid, string $permissionCode): bool
    {
        return PermissionGroupPermission::where('group_uuid', $groupUuid)
            ->where('permission_code', $permissionCode)
            ->delete() > 0;
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add module/Car/Services/
git commit -m "feat(car): 添加 CarService 和 PermissionService"
```

---

## Task 5: 创建 Controller 类

**Files:**
- Create: `module/Car/API/CarApplyController.php`
- Create: `module/Car/API/CarApproveController.php`
- Create: `module/Car/API/CarEndController.php`
- Create: `module/Car/API/CarPlateController.php`
- Create: `module/Car/API/PermissionGroupController.php`

- [ ] **Step 1: 创建 CarApplyController**

```php
<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarApplication;
use Module\Car\Services\CarService;

class CarApplyController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 申请用车
     */
    public function store(Request $request): JsonResponse
    {
        $validate = $request->validate([
            'car_type' => 'required|in:general,business,other',
            'reason' => 'required|string',
            'passenger_count' => 'required|integer|min:1',
            'use_time' => 'required|date',
            'remark' => 'nullable|string',
        ], [
            'car_type.required' => '请选择用车类型',
            'reason.required' => '请填写用车事由',
            'passenger_count.required' => '请填写用车人数',
            'use_time.required' => '请选择用车时间',
        ]);

        try {
            $result = $this->carService->apply($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 我的申请列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $result = $this->carService->list($request);
            return $this->successPaginator($result['data'], $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 申请详情
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $application = CarApplication::where('uuid', $uuid)
                ->firstOrFail()
                ->load(['logs', 'taskLogs']);

            if ($application->user_uuid === $user->uuid) {
                $application->load('next');
            }

            return $this->success($application);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除申请
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        try {
            $user = $request->user();
            $application = CarApplication::where('uuid', $uuid)
                ->where('user_uuid', $user->uuid)
                ->where('status', 'applying')
                ->firstOrFail();

            $application->delete();

            return $this->success($application, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

- [ ] **Step 2: 创建 CarApproveController**

```php
<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarPlate;
use Module\Car\Services\CarService;
use Module\Car\Services\PermissionService;

class CarApproveController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 审批
     */
    public function approve(Request $request): JsonResponse
    {
        $user = $request->user();

        // 检查权限
        if (!PermissionService::userHasPermission($user->uuid, 'car_approve')) {
            return $this->error('无审批权限');
        }

        $validate = $request->validate([
            'uuid' => 'required|uuid',
            'action' => 'required|in:agree,reject',
            'plate_id' => 'required_if:action,agree|uuid',
            'reply' => 'nullable|string',
        ], [
            'uuid.required' => '请选择要审批的申请',
            'action.required' => '请选择审批操作',
            'plate_id.required_if' => '同意时必须选择车牌',
        ]);

        try {
            $result = $this->carService->approve($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 待处理列表
     */
    public function todo(Request $request): JsonResponse
    {
        try {
            $result = $this->carService->todoList($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 已处理列表
     */
    public function processed(Request $request): JsonResponse
    {
        try {
            $result = $this->carService->processedList($request);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 获取可用车牌列表
     */
    public function plates(Request $request): JsonResponse
    {
        $plates = CarPlate::where('status', 0)->get();
        return $this->success($plates);
    }
}
```

- [ ] **Step 3: 创建 CarEndController**

```php
<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Enums\CarStatus;
use Module\Car\Models\CarApplication;
use Module\Car\Services\CarService;

class CarEndController extends ApiController
{
    protected $carService;

    public function __construct(CarService $carService)
    {
        $this->carService = $carService;
    }

    /**
     * 结束用车
     */
    public function end(Request $request, string $uuid): JsonResponse
    {
        $validate = $request->validate([
            'start_km' => 'required|numeric|min:0',
            'end_km' => 'required|numeric|gt:start_km',
        ], [
            'start_km.required' => '请填写开始公里数',
            'end_km.required' => '请填写结束公里数',
            'end_km.gt' => '结束公里数必须大于开始公里数',
        ]);

        try {
            $result = $this->carService->end($request, $uuid);
            return $this->success($result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

- [ ] **Step 4: 创建 CarPlateController**

```php
<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\CarPlate;
use Module\Car\Services\PermissionService;

class CarPlateController extends ApiController
{
    /**
     * 车牌列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $plates = CarPlate::orderBy('created_at', 'desc')->get();
            return $this->success($plates);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加车牌
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'plate_number' => 'required|string|unique:car_plates,plate_number',
            'description' => 'nullable|string',
        ], [
            'plate_number.required' => '请填写车牌号',
            'plate_number.unique' => '车牌号已存在',
        ]);

        try {
            $plate = CarPlate::create([
                'plate_number' => $validate['plate_number'],
                'description' => $validate['description'] ?? '',
                'status' => 0,
            ]);
            return $this->success($plate);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新车牌
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $plate = CarPlate::where('uuid', $uuid)->firstOrFail();

            $plate->plate_number = $request->input('plate_number', $plate->plate_number);
            $plate->description = $request->input('description', $plate->description);
            $plate->status = $request->input('status', $plate->status);
            $plate->save();

            return $this->success($plate);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除车牌
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $plate = CarPlate::where('uuid', $uuid)->firstOrFail();
            $plate->delete();
            return $this->success($plate, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

- [ ] **Step 5: 创建 PermissionGroupController**

```php
<?php

namespace Module\Car\API;

use App\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Car\Models\PermissionGroup;
use Module\Car\Models\PermissionGroupUser;
use Module\Car\Models\PermissionGroupPermission;
use Module\Car\Services\PermissionService;

class PermissionGroupController extends ApiController
{
    /**
     * 权限组列表
     */
    public function index(): JsonResponse
    {
        try {
            $groups = PermissionGroup::with(['users', 'permissions'])->get();
            return $this->success($groups);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 创建权限组
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'name' => 'required|string',
            'code' => 'required|string|unique:permission_groups,code',
            'description' => 'nullable|string',
        ], [
            'name.required' => '请填写组名称',
            'code.required' => '请填写组编码',
            'code.unique' => '组编码已存在',
        ]);

        try {
            $group = PermissionService::createGroup($validate);
            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 更新权限组
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();

            $group->name = $request->input('name', $group->name);
            $group->description = $request->input('description', $group->description);
            $group->save();

            return $this->success($group);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除权限组
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();

            // 删除关联的成员和权限
            $group->users()->delete();
            $group->permissions()->delete();
            $group->delete();

            return $this->success($group, '删除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加成员
     */
    public function addUser(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'user_uuid' => 'required|uuid',
        ], [
            'user_uuid.required' => '请填写用户UUID',
        ]);

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();
            $member = PermissionService::addUserToGroup($uuid, $validate['user_uuid']);
            return $this->success($member);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 移除成员
     */
    public function removeUser(Request $request, string $uuid, string $userUuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            PermissionService::removeUserFromGroup($uuid, $userUuid);
            return $this->success(null, '移除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 添加权限
     */
    public function addPermission(Request $request, string $uuid): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        $validate = $request->validate([
            'permission_code' => 'required|string',
        ], [
            'permission_code.required' => '请填写权限码',
        ]);

        try {
            $group = PermissionGroup::where('uuid', $uuid)->firstOrFail();
            $permission = PermissionService::addPermissionToGroup($uuid, $validate['permission_code']);
            return $this->success($permission);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 移除权限
     */
    public function removePermission(Request $request, string $uuid, string $code): JsonResponse
    {
        $user = $request->user();

        if (!PermissionService::userHasPermission($user->uuid, 'car_admin')) {
            return $this->error('无管理权限');
        }

        try {
            PermissionService::removePermissionFromGroup($uuid, $code);
            return $this->success(null, '移除成功');
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
```

- [ ] **Step 6: Commit**

```bash
git add module/Car/API/
git commit -m "feat(car): 添加 Car 模块所有 Controller"
```

---

## Task 6: 更新路由配置

**Files:**
- Modify: `module/Car/api.php`

- [ ] **Step 1: 更新路由文件**

```php
<?php

use Module\Car\API\CarApplyController;
use Module\Car\API\CarApproveController;
use Module\Car\API\CarEndController;
use Module\Car\API\CarPlateController;
use Module\Car\API\PermissionGroupController;

// 用车申请
Route::post('apply', [CarApplyController::class, 'store']);
Route::get('apply', [CarApplyController::class, 'index']);
Route::get('apply/{uuid}', [CarApplyController::class, 'show']);
Route::delete('apply/{uuid}', [CarApplyController::class, 'destroy']);

// 用车审批
Route::post('approve', [CarApproveController::class, 'approve']);
Route::get('approve/todo', [CarApproveController::class, 'todo']);
Route::get('approve/done', [CarApproveController::class, 'processed']);
Route::get('approve/plates', [CarApproveController::class, 'plates']);

// 结束用车
Route::post('end/{uuid}', [CarEndController::class, 'end']);

// 车牌管理
Route::get('plate', [CarPlateController::class, 'index']);
Route::post('plate', [CarPlateController::class, 'store']);
Route::put('plate/{uuid}', [CarPlateController::class, 'update']);
Route::delete('plate/{uuid}', [CarPlateController::class, 'destroy']);

// 权限组管理
Route::prefix('permission')->group(function () {
    Route::get('group', [PermissionGroupController::class, 'index']);
    Route::post('group', [PermissionGroupController::class, 'store']);
    Route::put('group/{uuid}', [PermissionGroupController::class, 'update']);
    Route::delete('group/{uuid}', [PermissionGroupController::class, 'destroy']);
    Route::post('group/{uuid}/user', [PermissionGroupController::class, 'addUser']);
    Route::delete('group/{uuid}/user/{userUuid}', [PermissionGroupController::class, 'removeUser']);
    Route::post('group/{uuid}/permission', [PermissionGroupController::class, 'addPermission']);
    Route::delete('group/{uuid}/permission/{code}', [PermissionGroupController::class, 'removePermission']);
});
```

- [ ] **Step 2: Commit**

```bash
git add module/Car/api.php
git commit -m "feat(car): 更新 Car 模块路由配置"
```

---

## Task 7: 更新 CarServiceProvider

**Files:**
- Modify: `module/Car/CarServiceProvider.php`

- [ ] **Step 1: 更新 ServiceProvider**

```php
<?php

namespace Module\Car;

use Illuminate\Support\ServiceProvider;
use Route;

class CarServiceProvider extends ServiceProvider
{
    function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
    }

    function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__.'/api.php');
        });
    }

    protected function routeConfiguration(): array
    {
        return [
            'namespace' => 'Module\Car\API',
            'prefix' => 'api/car',
            'middleware' => 'auth:sanctum',
        ];
    }

    protected function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/DB/Migrations');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add module/Car/CarServiceProvider.php
git commit -m "refactor(car): 简化 CarServiceProvider"
```

---

## Task 8: 修正 Document 模块关联（如果需要）

**Files:**
- Check: `app/Models/Next.php`
- Check: `module/Document/Models/ApprovalLog.php`

- [ ] **Step 1: 检查 Next 模型是否支持多态关联**

如果 `Next` 模型已经有 `morphMany` 和 `morphTo` 方法，则 Car 模块可以直接复用。

检查方法：查看 `app/Models/Next.php` 是否存在 `nextTable` morphTo 方法。

如果有问题，需要确保 Document 模块的模型关联可以同时支持 Car 模块。

- [ ] **Step 2: Commit（如有修改）**

---

## Task 9: 运行迁移并测试

- [ ] **Step 1: 运行迁移**

```bash
php artisan migrate --path=module/Car/DB/Migrations --database=mysql
```

- [ ] **Step 2: 检查迁移状态**

```bash
php artisan migrate:status
```

---

## Task 10: 创建权限组和测试数据

- [ ] **Step 1: 创建初始权限组（手动在数据库或通过 tinker）**

```php
// 创建行办审批权限组
$group = \Module\Car\Models\PermissionGroup::create([
    'name' => '行办审批群组',
    'code' => 'car_approver',
    'description' => '用车审批权限',
]);

// 添加权限
\Module\Car\Models\PermissionGroupPermission::create([
    'group_uuid' => $group->uuid,
    'permission_code' => 'car_approve',
]);

// 创建管理员权限组
$adminGroup = \Module\Car\Models\PermissionGroup::create([
    'name' => '用车管理员',
    'code' => 'car_admin',
    'description' => '用车管理权限（车牌管理、权限组管理）',
]);

\Module\Car\Models\PermissionGroupPermission::create([
    'group_uuid' => $adminGroup->uuid,
    'permission_code' => 'car_admin',
]);

\Module\Car\Models\PermissionGroupPermission::create([
    'group_uuid' => $adminGroup->uuid,
    'permission_code' => 'car_approve',
]);
```

---

## 自检清单

- [ ] 所有 Model 的 fillable、casts、relations 是否正确
- [ ] CarApplication 的 logs 和 taskLogs 是否正确关联到 CarLog 和 CarTaskLog
- [ ] CarService 中的权限检查是否完整
- [ ] 路由前缀是否为 `api/car`
- [ ] 迁移文件是否正确放在 `module/Car/DB/Migrations/`
- [ ] 所有控制器是否继承 ApiController
