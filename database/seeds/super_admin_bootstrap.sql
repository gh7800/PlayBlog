-- ============================================================
-- 等价 SuperAdminSeeder 的纯 SQL 版（打破首组授权死锁）
-- 适用：CLI 无法直连远程库（47.93.14.46）时，在任意能连 app_db 的
--       客户端执行本脚本：Navicat / 阿里云 DMS / 服务器本地 mysql 命令行
-- 特点：可重复执行（已做幂等处理），与 SuperAdminSeeder.php 效果一致
-- ============================================================

-- 1) 确保「超级管理员」组存在（固定 uuid，避免与手动建的组冲突）
INSERT INTO permission_groups (uuid, code, name, type, level, description, created_at, updated_at, deleted_at)
SELECT '00000000-0000-0000-0000-0000000000aa', 'super_admin', '超级管理员', 'user', 1,
       '系统初始化创建，拥有全部权限', NOW(), NOW(), NULL
WHERE NOT EXISTS (
    SELECT 1 FROM permission_groups WHERE uuid = '00000000-0000-0000-0000-0000000000aa'
);

-- 2) 把全部权限挂到该组（LEFT JOIN 防重复，幂等安全）
INSERT INTO permission_group_permissions (uuid, group_uuid, permission_uuid, created_at, updated_at, deleted_at)
SELECT UUID(), '00000000-0000-0000-0000-0000000000aa', p.uuid, NOW(), NOW(), NULL
FROM permissions p
LEFT JOIN permission_group_permissions pgp
       ON pgp.permission_uuid = p.uuid
      AND pgp.group_uuid = '00000000-0000-0000-0000-0000000000aa'
WHERE pgp.uuid IS NULL;

-- 3) 把所有用户加进该组（LEFT JOIN 防重复，幂等安全）
INSERT INTO permission_group_users (uuid, group_uuid, user_uuid, created_at, updated_at, deleted_at)
SELECT UUID(), '00000000-0000-0000-0000-0000000000aa', u.uuid, NOW(), NOW(), NULL
FROM user u
LEFT JOIN permission_group_users pgu
       ON pgu.user_uuid = u.uuid
      AND pgu.group_uuid = '00000000-0000-0000-0000-0000000000aa'
WHERE pgu.uuid IS NULL;

-- ========== 验证（可选）：确认结果 ==========
-- SELECT g.name, COUNT(pgp.uuid) AS perm_count
-- FROM permission_groups g
-- LEFT JOIN permission_group_permissions pgp ON pgp.group_uuid = g.uuid
-- WHERE g.uuid = '00000000-0000-0000-0000-0000000000aa'
-- GROUP BY g.name;
