# 通知公告模块设计

## 概述

为办公系统增加通知公告模块，支持发布公告给指定人员，支持附件。

## 数据模型

### notice_notices (公告主表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| uuid | char(36) | UUID |
| title | varchar(255) | 标题 |
| content | text | 内容 |
| sender_uuid | char(36) | 发布人 UUID |
| sender_name | varchar(100) | 发布人姓名 |
| is_deleted | tinyint | 软删除标记 |
| created_at | timestamp | 创建时间 |
| updated_at | timestamp | 更新时间 |

### notice_receivers (公告-用户关联表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| notice_id | bigint | 公告 ID |
| user_uuid | char(36) | 接收人员 UUID |

### notice_files (附件表)

| 字段 | 类型 | 说明 |
|------|------|------|
| id | bigint | 主键 |
| notice_id | bigint | 公告 ID |
| file_url | varchar(500) | 文件 URL |
| file_name | varchar(255) | 文件名 |
| file_size | bigint | 文件大小 |

## API 设计

| 方法 | 路径 | 说明 | 权限 |
|------|------|------|------|
| POST | `/api/notice` | 创建并发布公告 | auth:sanctum |
| GET | `/api/notice` | 公告列表（分页） | auth:sanctum |
| GET | `/api/notice/{uuid}` | 公告详情 | auth:sanctum |
| DELETE | `/api/notice/{uuid}` | 删除公告 | auth:sanctum |

### 创建公告

**请求**
```json
{
  "title": "公告标题",
  "content": "公告内容",
  "receiver_uuids": ["uuid1", "uuid2"],
  "files": [
    {
      "file_url": "https://...",
      "file_name": "xxx.pdf",
      "file_size": 1024
    }
  ]
}
```

**响应**
```json
{
  "success": true,
  "message": "发布成功",
  "data": { "uuid": "xxx" }
}
```

### 公告列表

**请求**: GET `/api/notice?page=1&per_page=15`

**响应**: 返回当前用户被指定接收的公告分页列表

### 公告详情

**响应**: 返回公告完整信息，包含附件列表

### 删除公告

物理删除（软删除 is_deleted=1）

## 目录结构

```
module/Notice/
├── NoticeServiceProvider.php
├── api.php
├── Services/
│   └── NoticeService.php
├── Models/
│   ├── Notice.php
│   ├── NoticeReceiver.php
│   └── NoticeFile.php
├── API/
│   └── NoticeController.php
└── DB/
    └── Migrations/
        ├── 2026_04_27_000001_create_notices_table.php
        ├── 2026_04_27_000002_create_notice_receivers_table.php
        └── 2026_04_27_000003_create_notice_files_table.php
```

## 业务规则

1. 公告永久有效，无过期时间
2. 接收人员按用户 UUID 指定（非部门）
3. 附件可选，支持多个
4. 删除为物理删除
5. 公告列表仅返回当前用户被指定接收的公告
6. 发布人可删除自己发布的公告
