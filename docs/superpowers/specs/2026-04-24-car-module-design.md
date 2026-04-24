# 用车管理模块设计方案

## 概述

基于 Document 模块的架构模式，为 Car 模块设计完整的用车申请、审批、结束流程。

## 表结构设计

### 1. car_applications（用车申请表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | uuid | 唯一标识 |
| user_uuid | uuid | 申请人uuid |
| user_name | string | 申请人姓名 |
| car_type | enum | 用车类型：general(一般用车)、business(业务用车)、other(其他) |
| reason | string | 用车事由 |
| passenger_count | int | 用车人数 |
| use_time | datetime | 用车时间 |
| remark | text | 备注 |
| status | string | 状态：applying/approved/rejected/ongoing/completed |
| status_title | string | 状态中文 |
| step | int | 流程步骤 |
| approved_plate_id | bigint | 审批通过后的车牌ID |
| approved_plate_number | string | 审批通过后的车牌号 |
| reject_reason | string | 拒绝原因 |
| start_km | decimal | 开始公里数 |
| end_km | decimal | 结束公里数 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |
| deleted_at | datetime | 删除时间 |

### 2. car_plates（车牌表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | uuid | 唯一标识 |
| plate_number | string | 车牌号 |
| description | string | 描述 |
| status | tinyint | 状态：0可用，1不可用 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |
| deleted_at | datetime | 删除时间 |

### 3. car_logs（用车流程日志）
关联到 existing `approval_logs` 表，使用 morph 关联：
- `approvalLog_id` -> car_applications.uuid
- `approvalLog_type` -> 'Module\Car\Models\CarApplication'

### 4. car_task_logs（待办任务表）
关联到 existing `document_task_logs` 表：
- `taskLog_id` -> car_applications.uuid
- `taskLog_type` -> 'Module\Car\Models\CarApplication'

### 5. permission_groups（权限组表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | uuid | 唯一标识 |
| name | string | 组名称 |
| code | string | 编码（如 car_approver, car_admin） |
| description | string | 描述 |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |
| deleted_at | datetime | 删除时间 |

### 6. permission_group_users（权限组成员表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | uuid | 唯一标识 |
| group_uuid | uuid | 权限组uuid |
| user_uuid | uuid | 用户uuid |
| created_at | datetime | 创建时间 |

### 7. permission_group_permissions（权限组权限表）
| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | uuid | 唯一标识 |
| group_uuid | uuid | 权限组uuid |
| permission_code | string | 权限码（如 car_approve, car_admin） |
| created_at | datetime | 创建时间 |

## API 路由设计

### 车牌管理（管理员权限 car_admin）
```
POST   /api/car/plate              # 添加车牌
GET    /api/car/plate              # 车牌列表
PUT    /api/car/plate/{uuid}       # 更新车牌
DELETE /api/car/plate/{uuid}       # 删除车牌
```

### 用车申请（需认证）
```
POST   /api/car/apply              # 申请用车
GET    /api/car/apply              # 我的申请列表（支持模糊查询）
GET    /api/car/apply/{uuid}       # 申请详情
DELETE /api/car/apply/{uuid}       # 删除申请（仅申请中状态可删除）
```

### 用车审批（行办权限 car_approve）
```
POST   /api/car/approve            # 审批（同意/拒绝）
GET    /api/car/approve/todo       # 待处理列表
GET    /api/car/approve/done       # 已处理列表
```

### 结束用车（申请人）
```
POST   /api/car/end/{uuid}         # 结束用车（必填 start_km, end_km）
```

### 权限组管理（管理员权限 car_admin）
```
POST   /api/permission/group              # 创建权限组
GET    /api/permission/group               # 权限组列表
PUT    /api/permission/group/{uuid}       # 更新权限组
DELETE /api/permission/group/{uuid}       # 删除权限组
POST   /api/permission/group/{uuid}/user   # 添加成员
DELETE /api/permission/group/{uuid}/user/{userUuid} # 移除成员
POST   /api/permission/group/{uuid}/permission   # 添加权限
DELETE /api/permission/group/{uuid}/permission/{code} # 移除权限
```

## 流程状态

| 状态 | 说明 | 可执行操作 |
|------|------|-----------|
| applying | 申请中 | 审批（行办） |
| approved | 已同意 | 开始用车 |
| rejected | 已拒绝 | 重新申请 |
| ongoing | 用车中 | 结束用车 |
| completed | 已结束 | - |

## 流程说明

### 1. 申请用车
- 用户提交申请，包含：用车类型、事由、人数、时间、备注
- 创建待办任务给行办审批人员
- 记录日志

### 2. 行办审批
- 同意：选择车牌，状态改为 approved，记录日志
- 拒绝：填写拒绝原因，状态改为 rejected，记录日志

### 3. 结束用车
- 申请人填写开始公里数和结束公里数
- 状态改为 completed，记录日志

## 文件结构

```
module/Car/
├── API/
│   ├── CarPlateController.php      # 车牌管理
│   ├── CarApplyController.php       # 用车申请
│   ├── CarApproveController.php      # 用车审批
│   ├── CarEndController.php          # 结束用车
│   └── PermissionGroupController.php # 权限组管理
├── Models/
│   ├── CarApplication.php           # 用车申请模型
│   ├── CarPlate.php                  # 车牌模型
│   ├── CarLog.php                    # 用车日志（关联 approval_logs）
│   └── CarTaskLog.php                # 用车待办（关联 document_task_logs）
├── Services/
│   └── CarService.php                # 用车业务逻辑
├── Permission/
│   └── PermissionService.php         # 权限组业务逻辑
├── DB/Migrations/
│   ├── 2026_04_24_xxxx_create_car_applications_table.php
│   ├── 2026_04_24_xxxx_create_car_plates_table.php
│   ├── 2026_04_24_xxxx_create_permission_groups_table.php
│   ├── 2026_04_24_xxxx_create_permission_group_users_table.php
│   └── 2026_04_24_xxxx_create_permission_group_permissions_table.php
├── Enums/
│   └── CarStatus.php                 # 用车状态枚举
└── api.php                           # 路由配置
```

## 参考实现

- Document 模块的 DocumentService 用于审批流程参考
- Document 模块的 ApprovalLog 用于日志关联参考
- Document 模块的 DocumentTaskLog 用于待办任务参考
