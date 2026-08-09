# 注册接口设计

日期：2026-08-09
状态：已确认

## 背景

项目（Laravel 8.83 REST API）目前只有登录/登出接口，没有对外开放的用户注册入口。
管理员通过 `POST /api/user/add` 创建用户（需 `auth:sanctum` 认证）。
需要一个无需认证的 App 端自注册接口。

## 目标

新增 `POST /auth/register` 开放接口，实现：

1. 用户自行注册（用户名 + 密码必填）
2. 注册成功后自动登录（直接返回 token，无需二次调 `/auth/login`）
3. 与现有 `/auth/login` 的校验规则、返回结构保持一致

## 接口设计

```
POST /auth/register     （无认证中间件，与 /auth/login 同级）
```

### 请求参数

| 字段 | 必填 | 校验规则 |
|---|---|---|
| `username` | ✅ | 4-16 位，`alpha_num`（字母数字），与 login 一致 |
| `password` | ✅ | 4-16 位，`alpha_num`（字母数字），与 login 一致 |
| `real_name` | 否 | 真实姓名，默认取 username |
| `phone` | 否 | 手机号 |
| `email` | 否 | 邮箱 |
| `address` | 否 | 地址 |

组织归属字段（`company_uuid` / `department_uuid` / `role_uuid`）**不开放**给自注册，
留空由管理员后续通过 `PUT /api/user/update/{uuid}` 分配。

### 响应结构

注册成功返回与 `/auth/login` 一致的结构（含 `token` / `device` / `permissions`）：

```json
{
  "success": true,
  "message": "操作成功",
  "data": {
    "uuid": "...",
    "username": "...",
    "real_name": "...",
    "...": "...",
    "token": "1|xxx...",
    "device": "Android|iOS|Web",
    "permissions": []
  }
}
```

## 实现方案

**方案：`LoginController` 新增 `register()` 方法**（已确认）。

- 路由 `POST /auth/register` 挂到 `routes/auth.php`
- `register()` 放在 `LoginController`，复用 login 的设备判断 + token 生成逻辑
- 控制器薄，不引入 Service 层（与现有 login 规模一致）

### 改动文件

1. `app/Http/Controllers/LoginController.php` — 新增 `register()` 方法
2. `routes/auth.php` — 新增路由 `Route::post('register', [LoginController::class, 'register']);`

### register() 业务逻辑

1. 校验参数（`bail|required|between:4,16|alpha_num`，中文报错文案与 login 一致）
2. 用户名唯一性：`BlogUser::withTrashed()->where('username', ...)` 查重
   （与 `UserController::addUser` 一致——软删除的账号同名也不允许注册）
3. 创建用户：`bcrypt($password)`，`status` 默认 `1`，其余可选字段写入
4. 自动登录：复用 login 的 `DeviceHelper::getDeviceType` + `createToken` 流程，
   返回 `token` / `device` / `permissions`
5. 响应：`$this->success(...)`，结构与 login 对齐

### 错误处理

| 场景 | 返回 |
|---|---|
| 用户名已存在（含软删） | `error('用户名已存在', 400)` |
| 校验失败 | 框架验证错误（与 login 一致） |

## 测试

- 正常注册：返回 token + 用户信息，`user` 表新增记录，密码为 bcrypt 密文
- 重复用户名：返回"用户名已存在"
- 已软删用户同名注册：同样拒绝
- 注册后可用返回的 token 访问受保护接口（如 `GET /api/user/info`）

## 不做的事（YAGNI）

- 不做验证码 / 手机号短信验证
- 不做开放注册时的组织分配
- 不改动现有 login / logout 逻辑
