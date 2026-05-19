# PlayBlog 项目开发规则

## 项目概述

PlayBlog 是一个基于 Laravel 8.x 的企业级管理系统，采用模块化架构设计，主要面向企业内部管理场景。

## 技术栈

### 后端
- **PHP**: 7.4+ / 8.0+
- **框架**: Laravel 8.x
- **认证**: Laravel Sanctum
- **数据库**: MySQL (utf8mb4_unicode_ci)
- **队列**: Database Queue

### 前端
- **框架**: Vue.js + Laravel Mix
- **移动端**: Framework7

### 核心依赖
- jpush/jpush - 极光推送
- guzzlehttp/guzzle - HTTP 客户端
- symfony/uid - UUID 生成
- ramsey/uuid - UUID 处理

## 代码规范

### 命名约定

#### 文件和目录命名
- **控制器**: `PascalCase`，如 `UserController.php`
- **模型**: `PascalCase`，如 `BlogUser.php`
- **服务类**: `PascalCase`，如 `PermissionService.php`
- **路由文件**: `kebab-case`，如 `organization.php`
- **迁移文件**: 遵循 Laravel 命名规范 `YYYY_MM_DD_HHMMSS_description.php`

#### 变量和函数命名
- **变量**: `camelCase`
- **函数**: `camelCase`
- **常量**: `UPPER_SNAKE_CASE`
- **数据库表名**: `snake_case`，复数形式

#### 类命名
- **类名**: `PascalCase`
- **接口**: `PascalCase` + `Interface` 后缀
- **抽象类**: `PascalCase` + `Abstract` 前缀
- **Trait**: `PascalCase` + `Trait` 后缀

### 代码风格

#### PHP 代码
- 遵循 PSR-12 编码标准
- 使用 4 空格缩进
- 类和方法使用文档注释
- 使用类型声明（PHP 7.4+）

#### 注释规范
- 类注释使用 `/** */` 格式
- 方法注释包含参数说明和返回值说明
- 复杂逻辑添加行内注释
- 注释使用中文

```php
/**
 * 用户控制器
 * 
 * 处理用户相关的所有业务逻辑
 */
class UserController extends Controller
{
    /**
     * 获取用户信息
     * 
     * @param Request $request 请求对象
     * @return JsonResponse 用户信息
     */
    public function getUserInfo(Request $request): JsonResponse
    {
        // 实现代码
    }
}
```

## 架构约定

### 模块化架构

#### 模块结构
每个业务模块应包含以下结构：

```
module/ModuleName/
├── API/              # 控制器
│   └── XxxController.php
├── Models/           # 数据模型
│   └── Xxx.php
├── Services/         # 业务逻辑服务
│   └── XxxService.php
├── DB/               # 数据库相关
│   └── Migrations/
│       └── xxx_create_table.php
├── Enums/            # 枚举类
│   └── XxxStatus.php
├── Jobs/             # 队列任务
│   └── XxxJob.php
├── api.php           # 模块路由
└── ModuleServiceProvider.php  # 服务提供者
```

#### 模块注册
每个模块必须创建 ServiceProvider 并在 `config/app.php` 中注册：

```php
// 模块路由加载
$this->loadRoutesFrom(__DIR__.'/api.php');

// 模块迁移文件加载
$this->loadMigrationsFrom(__DIR__.'/DB/Migrations');
```

### 分层架构

#### 控制器层 (Controller)
- 处理 HTTP 请求和响应
- 参数验证
- 调用服务层处理业务逻辑
- 不包含复杂业务逻辑

#### 服务层 (Service)
- 核心业务逻辑
- 数据处理和转换
- 调用模型层进行数据操作
- 事务管理

#### 模型层 (Model)
- 数据库操作
- 关系定义
- 数据验证
- 软删除支持

## 数据库设计规范

### 表设计

#### 主键规范
- 所有表使用 `uuid` 作为主键
- 使用 `Ramsey\Uuid\Uuid` 生成 UUID
- 在模型 boot 方法中自动生成 UUID

```php
protected static function booted()
{
    static::creating(function ($model) {
        if (empty($model->uuid)) {
            $model->uuid = Uuid::uuid4()->toString();
        }
    });
}
```

#### 字段命名
- 主键: `uuid`
- 外键: `{table}_uuid`
- 时间戳: `created_at`, `updated_at`
- 软删除: `deleted_at`
- 状态字段: `status`

#### 必需字段
- 所有表必须包含时间戳字段
- 重要表支持软删除
- 使用 `deleted_at` 而非物理删除

#### 字符集和排序规则
- 字符集: `utf8mb4`
- 排序规则: `utf8mb4_unicode_ci`

### 迁移文件规范

#### 迁移文件命名
- 创建表: `create_{table_name}_table.php`
- 添加字段: `add_{field_name}_to_{table_name}_table.php`
- 修改表: `modify_{table_name}_table.php`

#### 迁移执行
- 主数据库迁移: `php artisan migrate --path=database/migrations/blogDb --database=mysql`
- 模块迁移: 通过 ServiceProvider 自动加载

## API 设计规范

### RESTful 设计

#### 路由命名
- 使用复数形式: `/api/users`
- 使用 kebab-case: `/api/user-info`
- 资源路由: `Route::resource()`

#### HTTP 方法
- `GET` - 获取资源
- `POST` - 创建资源
- `PUT/PATCH` - 更新资源
- `DELETE` - 删除资源

#### 响应格式
```json
{
    "code": 200,
    "message": "success",
    "data": {}
}
```

### 认证和授权

#### 认证方式
- 使用 Laravel Sanctum token 认证
- 路由中间件: `auth:sanctum`

#### 权限检查
- 使用 PermissionService 检查权限
- 权限码格式: `{module}.{action}`
- 支持权限组和权限等级

```php
if (!PermissionService::userHasPermission($userUuid, 'car.apply')) {
    return response()->json(['message' => '无权限'], 403);
}
```

## 权限管理规范

### 权限组设计
- 权限组支持等级 (`level` 字段)
- 权限组支持类型 (`type` 字段)
- 高等级用户自动拥有低等级权限

### 权限设计
- 权限按模块分组 (`module` 字段)
- 权限按类型分类 (`type` 字段: page/function)
- 权限码唯一标识 (`code` 字段)

### 权限检查
- 使用 PermissionService 统一管理
- 支持直接权限和继承权限
- 权限树结构返回

## 业务模块开发规范

### 审批流程模块
- 必须包含审批链表
- 必须包含审批节点表
- 必须包含审批日志表
- 支持待办和已办查询
- 支持自动审批（队列任务）

### 通知模块
- 集成极光推送服务
- 支持接收者管理
- 支持文件附件
- 推送 ID 绑定用户

### 文件管理
- 统一使用 FileUploadService
- 支持多种文件类型
- 文件 URL 返回格式统一

## 测试规范

### 单元测试
- 测试文件放在 `tests/Unit/`
- 测试类命名: `XxxTest.php`
- 测试方法命名: `test_xxx_scenario()`

### 功能测试
- 测试文件放在 `tests/Feature/`
- 测试 API 接口
- 测试业务流程

### 运行测试
```bash
php artisan test
```

## 部署规范

### 环境配置
- 使用 `.env` 文件配置环境变量
- 敏感信息不提交到版本控制
- 生产环境设置 `APP_ENV=production`

### 缓存管理
- 部署前清除所有缓存:
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
```

### 数据库迁移
- 生产环境迁移前备份数据库
- 使用 `--force` 参数强制执行
- 检查迁移状态: `php artisan migrate:status`

### 队列处理
- 使用 Supervisor 管理队列进程
- 队列命令: `php artisan queue:work database --sleep=3 --tries=3`

## 常用命令

### 开发命令
```bash
# 启动开发服务器
php artisan serve

# 创建控制器
php artisan make:controller XxxController

# 创建模型
php artisan make:model Xxx -m

# 创建迁移
php artisan make:migration create_xxx_table

# 执行迁移
php artisan migrate --path=database/migrations/blogDb --database=mysql

# 回滚迁移
php artisan migrate:rollback
```

### 代码优化
```bash
# 代码格式化
php artisan cbf

# 清除缓存
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 优化自动加载
composer dump-autoload
```

## 安全规范

### 输入验证
- 使用 FormRequest 验证输入
- 验证规则定义在 Requests 目录
- 敏感操作需要二次验证

### 数据安全
- 密码使用 bcrypt 加密
- API token 使用 Sanctum 管理
- 敏感字段不返回给前端

### SQL 注入防护
- 使用 Eloquent ORM 或查询构建器
- 避免直接拼接 SQL
- 使用参数绑定

### XSS 防护
- 输出数据时使用 `{{ }}` 转义
- 验证和过滤用户输入
- 使用 CSRF 保护

## 性能优化

### 数据库优化
- 使用索引优化查询
- 避免 N+1 查询问题
- 使用 eager loading
- 合理使用缓存

### 代码优化
- 避免重复查询
- 使用队列处理耗时任务
- 合理使用 Redis 缓存
- 优化算法复杂度

### API 优化
- 使用分页查询
- 避免返回过多数据
- 使用压缩传输
- 合理设置缓存头

## 文档规范

### 代码注释
- 类和方法必须有文档注释
- 复杂逻辑添加行内注释
- 注释使用中文
- 注释保持更新

### API 文档
- 使用 Swagger/OpenAPI 规范
- 文档包含请求和响应示例
- 文档与代码保持同步

### 项目文档
- README.md 包含项目概述
- CLAUDE.md 包含开发指南
- docs/ 目录存放详细文档

## 版本控制

### Git 规范
- 使用语义化版本号
- 提交信息使用中文
- 分支命名: `feature/xxx`, `bugfix/xxx`, `hotfix/xxx`

### 提交信息格式
```
feat: 添加新功能
fix: 修复 bug
docs: 更新文档
style: 代码格式调整
refactor: 重构代码
test: 添加测试
chore: 构建/工具变更
```

## 错误处理

### 异常处理
- 使用 try-catch 捕获异常
- 自定义异常类
- 统一错误响应格式

### 日志记录
- 使用 Log facade 记录日志
- 重要操作记录日志
- 错误日志包含堆栈信息

```php
Log::info('用户登录', ['user_uuid' => $userUuid]);
Log::error('审批失败', ['error' => $e->getMessage()]);
```

## 常见问题解决

### UUID 生成问题
- 确保安装了 `ramsey/uuid` 包
- 在模型 boot 方法中自动生成

### 权限检查问题
- 使用 PermissionService 统一管理
- 检查权限组和权限配置
- 确保用户已分配权限组

### 模块路由不生效
- 检查 ServiceProvider 是否注册
- 检查路由文件路径是否正确
- 清除路由缓存: `php artisan route:clear`

### 队列任务不执行
- 检查队列配置
- 启动队列工作进程
- 检查失败任务表

## 注意事项

1. **不要提交敏感信息**: .env 文件、密钥、密码等
2. **保持代码简洁**: 避免过度设计，保持代码可读性
3. **遵循现有模式**: 参考现有代码风格和架构
4. **及时更新文档**: 代码变更时同步更新文档
5. **编写测试**: 重要功能必须编写测试
6. **性能优先**: 考虑查询性能和响应时间
7. **安全第一**: 始终考虑安全性问题
8. **用户体验**: 提供友好的错误提示和响应

## 联系方式

如有问题，请查看项目文档或联系开发团队。