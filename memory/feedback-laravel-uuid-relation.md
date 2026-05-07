---
name: laravel-uuid-relation-fix
description: Laravel 关联使用 uuid 主键时必须指定第三个参数
type: feedback
---

**规则**：当模型使用 `uuid` 而不是 `id` 作为主键时，`hasMany`/`belongsTo` 关联必须显式指定第三个参数为目标主键。

**为什么**：`hasMany` 关联默认假设目标主键是 `id`。如果模型主键是 `uuid` 而不指定，查询会用 `id` 去找关联记录，导致一直返回空。

**如何应用**：检查所有使用 uuid 作为主键的模型（如 PermissionGroup、Department 等）的关联：

```php
// ✅ 正确
return $this->hasMany(PermissionGroupUser::class, 'group_uuid', 'uuid');
return $this->belongsTo(BlogUser::class, 'user_uuid', 'uuid');

// ❌ 错误（默认用 id）
return $this->hasMany(PermissionGroupUser::class, 'group_uuid');
```

**何时检查**：当关联查询返回空但数据库数据存在时，很可能是这个问题。
