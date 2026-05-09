# 用车审批流程设计方案

## 一、概述

**目标：** 将用车审批流程改为配置化，支持多级审批节点，方便后续扩展。

**核心改动：**
- 新建审批链表和节点表，通过数据库配置审批流程
- 保留现有 `CarApplication` 结构，复用 `taskLogs` 机制
- 支持三种审批人类型：权限组、指定人、部门负责人

---

## 二、数据模型

### 2.1 审批链表表 `car_approval_chains`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | char(36) | UUID |
| name | varchar(100) | 链表名称，如"用车审批链" |
| description | varchar(255) | 描述 |
| is_active | tinyint | 是否启用（1=启用） |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### 2.2 审批节点表 `car_approval_nodes`

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | char(36) | UUID |
| chain_uuid | char(36) | 所属链表UUID（外键） |
| step | int | 节点顺序（1, 2, 3...） |
| name | varchar(100) | 节点名称，如"行办审批" |
| approver_type | enum | 审批人类型 |
| approver_value | varchar(255) | 审批人值（权限组code或用户UUID） |
| created_at | datetime | 创建时间 |
| updated_at | datetime | 更新时间 |

### 2.3 审批人类型 `approver_type`

| 类型值 | 说明 | approver_value 示例 |
|--------|------|---------------------|
| `permission_group` | 权限组 | `car_approver` |
| `user` | 指定用户 | 用户UUID字符串 |
| `dept_head` | 部门负责人 | 部门code，如 `dept_001` |

### 2.4 审批人获取逻辑

```php
function getApprovers($node) {
    switch ($node->approver_type) {
        case 'permission_group':
            $group = PermissionService::getGroupByCode($node->approver_value);
            return $group->users()->get()->pluck('user_uuid');

        case 'user':
            return [$node->approver_value];

        case 'dept_head':
            // 申请人所属部门的负责人
            return [getDeptHeadUserUuid($applicant->dept_uuid, $node->approver_value)];
    }
}
```

---

## 三、流程设计

### 3.1 完整流程

```
申请提交 → 节点1审批 → 节点2审批 → ... → 节点N审批 → 审批通过 → 结束用车 → 已完成
                ↓
            驳回 → 申请被拒绝
```

### 3.2 当前简化场景

使用单一固定审批链：

| 节点 | 名称 | 审批人类型 | 说明 |
|------|------|-----------|------|
| 1 | 行办审批 | permission_group | `car_approver` 权限组 |
| - | 结束用车 | - | 无审批，申请人直接操作 |

### 3.3 状态变化

| 状态 | 值 | 说明 |
|------|-----|------|
| applying | `applying` | 申请中，等待审批 |
| approved | `approved` | 审批通过，可结束用车 |
| rejected | `rejected` | 审批拒绝，流程结束 |
| completed | `completed` | 已完成，用车结束 |

---

## 四、API 设计

### 4.1 保持不变的接口

| 接口 | 说明 |
|------|------|
| POST `/api/car/apply` | 提交申请 |
| GET `/api/car/apply` | 我的申请列表 |
| GET `/api/car/apply/{uuid}` | 申请详情 |
| DELETE `/api/car/apply/{uuid}` | 删除申请（仅 `applying` 状态） |
| POST `/api/car/approve` | 审批（改为动态获取审批人） |
| GET `/api/car/approve/todo` | 待处理列表 |
| GET `/api/car/approve/done` | 已处理列表 |
| POST `/api/car/end/{uuid}` | 结束用车 |

### 4.2 新增接口

| 接口 | 说明 |
|------|------|
| GET `/api/car/chain` | 获取当前审批链配置（可选） |
| GET `/api/car/chain/nodes` | 获取当前审批链的节点列表（可选） |

### 4.3 审批接口变化

**原逻辑：**
```php
// 检查用户是否是 car_approver 权限组成员
PermissionService::userHasPermission($user->uuid, 'car_approver')
```

**新逻辑：**
```php
// 1. 获取申请当前应该审批的节点（根据 step 字段）
$currentNode = CarApprovalNode::where('chain_uuid', $chainUuid)
    ->where('step', $application->step)
    ->first();

// 2. 获取该节点的审批人
$approverUuids = getApprovers($currentNode);

// 3. 检查当前用户是否是审批人之一
if (!in_array($user->uuid, $approverUuids)) {
    return error('无审批权限');
}
```

---

## 五、业务逻辑

### 5.1 申请提交 `apply()`

1. 创建 `CarApplication`，状态=`applying`，step=1
2. 查询启用的审批链（is_active=1）
3. 获取 step=1 的节点，创建 `taskLogs` 给该节点的所有审批人
4. 记录日志

### 5.2 审批 `approve()`

1. 根据 `application.step` 获取当前节点
2. 获取节点审批人，判断当前用户是否有权限审批
3. 审批操作：
   - **同意**：`step++`，更新 `status` 为下一个节点的 status（最后一个节点则 `approved`）
   - **拒绝**：`status=rejected`
4. 清除当前 `taskLogs`
5. 如果还有下一节点，创建新的 `taskLogs`
6. 记录日志

### 5.3 结束用车 `end()`

1. 检查 `status` 必须是 `approved`
2. 检查结束公里数 > 开始公里数
3. 更新状态为 `completed`
4. 记录日志

---

## 六、数据库变更

### 6.1 迁移文件顺序

```
module/Car/DB/Migrations/
├── 2026_04_24_000001_create_car_applications_table.php  (已有)
├── 2026_04_24_000002_create_car_plates_table.php          (已有)
├── 2026_05_09_000001_create_car_approval_chains_table.php  (新增)
└── 2026_05_09_000002_create_car_approval_nodes_table.php   (新增)
```

### 6.2 初始化数据

```sql
-- 插入默认审批链
INSERT INTO car_approval_chains (uuid, name, description, is_active, created_at)
VALUES ('car_chain_default', '用车审批链', '默认审批流程', 1, NOW());

-- 插入默认节点（行办审批）
INSERT INTO car_approval_nodes (uuid, chain_uuid, step, name, approver_type, approver_value, created_at)
VALUES ('car_node_1', 'car_chain_default', 1, '行办审批', 'permission_group', 'car_approver', NOW());
```

---

## 七、文件变更

| 操作 | 文件 |
|------|------|
| 新增 | `module/Car/Models/CarApprovalChain.php` |
| 新增 | `module/Car/Models/CarApprovalNode.php` |
| 新增 | `module/Car/Services/CarApprovalService.php` |
| 修改 | `module/Car/Services/CarService.php` |
| 新增 | `module/Car/DB/Migrations/2026_05_09_000001_create_car_approval_chains_table.php` |
| 新增 | `module/Car/DB/Migrations/2026_05_09_000002_create_car_approval_nodes_table.php` |
| 新增 | `database/migrations/blogDb/2026_05_09_000001_create_car_approval_chains_table.php` |
| 新增 | `database/migrations/blogDb/2026_05_09_000002_create_car_approval_nodes_table.php` |

---

## 八、扩展方向（未来）

1. **多审批链**：按申请类型（一般用车/业务用车）走不同审批链
2. **条件审批**：根据申请金额、车型等条件决定审批节点
3. **会签模式**：同一节点需要多人全部审批通过
4. **委托审批**：审批人可委托他人代为审批
5. **消息推送**：审批时发送 JPush 通知

---

## 九、测试要点

1. 申请提交后，taskLogs 是否正确创建给审批人
2. 非审批人无法审批（权限校验）
3. 审批通过后，step 是否正确递增
4. 最后一个节点审批通过后，状态是否为 `approved`
5. 拒绝后状态是否为 `rejected`，后续是否无法继续审批
6. `approved` 状态下才能结束用车
7. 结束用车后状态变为 `completed`
