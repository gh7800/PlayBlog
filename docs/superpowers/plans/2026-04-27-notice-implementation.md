# 通知公告模块实现计划

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 在 `module/Notice/` 下实现通知公告模块，支持创建、列表、详情、删除公告，接收人员按用户 UUID 指定，支持多附件。

**Architecture:** 采用 Laravel 模块化架构，参考 `module/Car/` 的目录结构和服务模式。使用 ServiceProvider 自动注册路由和迁移。

**Tech Stack:** Laravel 7.x, PHP 7.2.5+/8.0+, Laravel Sanctum

---

## 文件结构

```
module/Notice/
├── NoticeServiceProvider.php    # 模块服务提供者
├── api.php                      # 路由定义
├── Services/
│   └── NoticeService.php        # 业务逻辑
├── Models/
│   ├── Notice.php               # 公告模型
│   ├── NoticeReceiver.php       # 接收人员模型
│   └── NoticeFile.php           # 附件模型
├── API/
│   └── NoticeController.php     # 控制器
└── DB/
    └── Migrations/
        ├── 2026_04_27_000001_create_notices_table.php
        ├── 2026_04_27_000002_create_notice_receivers_table.php
        └── 2026_04_27_000003_create_notice_files_table.php
```

---

### Task 1: 创建模块目录和迁移文件

**Files:**
- Create: `module/Notice/DB/Migrations/2026_04_27_000001_create_notices_table.php`
- Create: `module/Notice/DB/Migrations/2026_04_27_000002_create_notice_receivers_table.php`
- Create: `module/Notice/DB/Migrations/2026_04_27_000003_create_notice_files_table.php`

- [ ] **Step 1: 创建 notices 表迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticesTable extends Migration
{
    public function up()
    {
        Schema::create('notice_notices', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('title', 255);
            $table->text('content');
            $table->char('sender_uuid', 36);
            $table->string('sender_name', 100);
            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();

            $table->index('sender_uuid');
            $table->index('is_deleted');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_notices');
    }
}
```

- [ ] **Step 2: 创建 notice_receivers 表迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticeReceiversTable extends Migration
{
    public function up()
    {
        Schema::create('notice_receivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notice_id');
            $table->char('user_uuid', 36);
            $table->timestamps();

            $table->foreign('notice_id')
                ->references('id')
                ->on('notice_notices')
                ->onDelete('cascade');
            $table->index('user_uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_receivers');
    }
}
```

- [ ] **Step 3: 创建 notice_files 表迁移**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticeFilesTable extends Migration
{
    public function up()
    {
        Schema::create('notice_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notice_id');
            $table->string('file_url', 500);
            $table->string('file_name', 255);
            $table->bigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('notice_id')
                ->references('id')
                ->on('notice_notices')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_files');
    }
}
```

- [ ] **Step 4: 提交**

```bash
git add module/Notice/DB/Migrations/
git commit -m "feat(notice): 添加公告模块迁移文件"
```

---

### Task 2: 创建模型

**Files:**
- Create: `module/Notice/Models/Notice.php`
- Create: `module/Notice/Models/NoticeReceiver.php`
- Create: `module/Notice/Models/NoticeFile.php`

- [ ] **Step 1: 创建 Notice 模型**

```php
<?php

namespace Module\Notice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Notice extends Model
{
    protected $table = 'notice_notices';

    protected $fillable = [
        'uuid',
        'title',
        'content',
        'sender_uuid',
        'sender_name',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(NoticeReceiver::class, 'notice_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(NoticeFile::class, 'notice_id');
    }
}
```

- [ ] **Step 2: 创建 NoticeReceiver 模型**

```php
<?php

namespace Module\Notice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeReceiver extends Model
{
    protected $table = 'notice_receivers';

    protected $fillable = [
        'notice_id',
        'user_uuid',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class, 'notice_id');
    }
}
```

- [ ] **Step 3: 创建 NoticeFile 模型**

```php
<?php

namespace Module\Notice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeFile extends Model
{
    protected $table = 'notice_files';

    protected $fillable = [
        'notice_id',
        'file_url',
        'file_name',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class, 'notice_id');
    }
}
```

- [ ] **Step 4: 提交**

```bash
git add module/Notice/Models/
git commit -m "feat(notice): 添加公告模块模型"
```

---

### Task 3: 创建 NoticeService

**Files:**
- Create: `module/Notice/Services/NoticeService.php`

- [ ] **Step 1: 创建 NoticeService**

```php
<?php

namespace Module\Notice\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Module\Notice\Models\Notice;
use Module\Notice\Models\NoticeFile;
use Module\Notice\Models\NoticeReceiver;

class NoticeService
{
    /**
     * 创建公告
     */
    public function create(Request $request): Notice
    {
        $user = Auth::user();

        $notice = Notice::create([
            'title' => $request->input('title'),
            'content' => $request->input('content'),
            'sender_uuid' => $user->uuid,
            'sender_name' => $user->real_name ?? '',
        ]);

        // 保存接收人员
        $receiverUuids = $request->input('receiver_uuids', []);
        foreach ($receiverUuids as $receiverUuid) {
            $notice->receivers()->create([
                'user_uuid' => $receiverUuid,
            ]);
        }

        // 保存附件
        $files = $request->input('files', []);
        foreach ($files as $file) {
            $notice->files()->create([
                'file_url' => $file['file_url'] ?? '',
                'file_name' => $file['file_name'] ?? '',
                'file_size' => $file['file_size'] ?? 0,
            ]);
        }

        return $notice->load(['receivers', 'files']);
    }

    /**
     * 公告列表
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        $page = $request->input('page', 1);
        $perPage = $request->input('per_page', 15);

        $paginator = Notice::where('is_deleted', 0)
            ->whereHas('receivers', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['sender'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
        ];
    }

    /**
     * 公告详情
     */
    public function detail(string $uuid)
    {
        $user = Auth::user();

        return Notice::where('uuid', $uuid)
            ->where('is_deleted', 0)
            ->whereHas('receivers', function ($query) use ($user) {
                $query->where('user_uuid', $user->uuid);
            })
            ->with(['receivers', 'files', 'sender'])
            ->firstOrFail();
    }

    /**
     * 删除公告
     */
    public function delete(string $uuid): bool
    {
        $user = Auth::user();

        $notice = Notice::where('uuid', $uuid)
            ->where('sender_uuid', $user->uuid)
            ->where('is_deleted', 0)
            ->firstOrFail();

        return $notice->update(['is_deleted' => 1]);
    }
}
```

- [ ] **Step 2: 提交**

```bash
git add module/Notice/Services/NoticeService.php
git commit -m "feat(notice): 添加 NoticeService 业务逻辑"
```

---

### Task 4: 创建 NoticeController

**Files:**
- Create: `module/Notice/API/NoticeController.php`

- [ ] **Step 1: 创建 NoticeController**

```php
<?php

namespace Module\Notice\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Notice\Services\NoticeService;

class NoticeController extends Controller
{
    private NoticeService $noticeService;

    public function __construct(NoticeService $noticeService)
    {
        $this->noticeService = $noticeService;
    }

    /**
     * 创建公告
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'receiver_uuids' => 'array',
            'receiver_uuids.*' => 'string',
            'files' => 'array',
            'files.*.file_url' => 'required|string',
            'files.*.file_name' => 'required|string',
            'files.*.file_size' => 'integer',
        ]);

        $notice = $this->noticeService->create($request);

        return response()->json([
            'success' => true,
            'message' => '发布成功',
            'data' => ['uuid' => $notice->uuid],
        ]);
    }

    /**
     * 公告列表
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->noticeService->list($request);

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $result['data'],
            'total' => $result['total'],
            'page' => $result['page'],
            'per_page' => $result['per_page'],
        ]);
    }

    /**
     * 公告详情
     */
    public function show(string $uuid): JsonResponse
    {
        $notice = $this->noticeService->detail($uuid);

        return response()->json([
            'success' => true,
            'message' => '获取成功',
            'data' => $notice,
        ]);
    }

    /**
     * 删除公告
     */
    public function destroy(string $uuid): JsonResponse
    {
        $this->noticeService->delete($uuid);

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }
}
```

- [ ] **Step 2: 提交**

```bash
git add module/Notice/API/NoticeController.php
git commit -m "feat(notice): 添加 NoticeController"
```

---

### Task 5: 创建 api.php 路由文件

**Files:**
- Create: `module/Notice/api.php`

- [ ] **Step 1: 创建路由文件**

```php
<?php

use Module\Notice\API\NoticeController;

Route::post('/', [NoticeController::class, 'store']);
Route::get('/', [NoticeController::class, 'index']);
Route::get('/{uuid}', [NoticeController::class, 'show']);
Route::delete('/{uuid}', [NoticeController::class, 'destroy']);
```

- [ ] **Step 2: 提交**

```bash
git add module/Notice/api.php
git commit -m "feat(notice): 添加路由配置"
```

---

### Task 6: 创建 NoticeServiceProvider

**Files:**
- Create: `module/Notice/NoticeServiceProvider.php`

- [ ] **Step 1: 创建 ServiceProvider**

```php
<?php

namespace Module\Notice;

use Illuminate\Support\ServiceProvider;
use Route;

class NoticeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->registerRoutes();
        $this->registerResources();
    }

    private function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/api.php');
        });
    }

    private function routeConfiguration(): array
    {
        return [
            'namespace' => 'Module\\Notice\\API',
            'prefix' => 'api/notice',
            'middleware' => 'auth:sanctum',
        ];
    }

    private function registerResources()
    {
        $this->loadMigrationsFrom(__DIR__ . '/DB/Migrations');
    }
}
```

- [ ] **Step 2: 提交**

```bash
git add module/Notice/NoticeServiceProvider.php
git commit -m "feat(notice): 添加 NoticeServiceProvider"
```

---

### Task 7: 注册模块到 config/app.php

**Files:**
- Modify: `config/app.php`

- [ ] **Step 1: 在 providers 数组中添加**

找到 providers 数组，添加：
```php
Module\Notice\NoticeServiceProvider::class,
```

- [ ] **Step 2: 提交**

```bash
git add config/app.php
git commit -m "feat(notice): 注册通知公告模块"
```

---

### Task 8: 运行迁移验证

- [ ] **Step 1: 运行迁移**

```bash
php artisan migrate --path=module/Notice/DB/Migrations --database=mysql
```

预期输出: `Migrating: 2026_04_27_000001_create_notices_table` 等

- [ ] **Step 2: 验证表结构**

```bash
php artisan migrate:status --database=mysql
```

---

## 验证清单

- [ ] 创建公告 API 正常工作
- [ ] 公告列表 API 返回当前用户接收的公告
- [ ] 公告详情 API 正常工作
- [ ] 删除公告 API 正常工作（软删除）
- [ ] 附件保存正常
- [ ] 迁移执行无错误
