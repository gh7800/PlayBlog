<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ramsey\Uuid\Uuid;

class UpdatePermissionGroupPermissionsAddPermissionUuid extends Migration
{
    public function up()
    {
        // 先收集所有已有的 permission_code，插入到 permissions 表
        $existingCodes = DB::table('permission_group_permissions')
            ->select('permission_code')
            ->distinct()
            ->whereNotNull('permission_code')
            ->pluck('permission_code');

        $now = now();
        $insertData = [];
        foreach ($existingCodes as $code) {
            $existing = DB::table('permissions')->where('code', $code)->first();
            if (!$existing) {
                $insertData[] = [
                    'uuid' => Uuid::uuid4()->toString(),
                    'code' => $code,
                    'name' => $code,
                    'description' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        if (!empty($insertData)) {
            DB::table('permissions')->insert($insertData);
        }

        // 添加 permission_uuid 列
        Schema::table('permission_group_permissions', function (Blueprint $table) {
            $table->string('permission_uuid', 36)->nullable()->after('permission_code');
        });

        // 更新已有的关联数据
        DB::statement('UPDATE permission_group_permissions pgp
            INNER JOIN permissions p ON p.code = pgp.permission_code
            SET pgp.permission_uuid = p.uuid');

        DB::statement('ALTER TABLE permission_group_permissions MODIFY permission_uuid VARCHAR(36) NOT NULL');
        Schema::table('permission_group_permissions', function (Blueprint $table) {
            $table->index('permission_uuid');
        });

        // 移除旧的 permission_code 列
        Schema::table('permission_group_permissions', function (Blueprint $table) {
            $table->dropColumn('permission_code');
        });
    }

    public function down()
    {
        // 恢复 permission_code 列
        Schema::table('permission_group_permissions', function (Blueprint $table) {
            $table->string('permission_code')->nullable()->after('id');
            $table->index('permission_code');
        });

        // 从 permission_uuid 回填 permission_code
        DB::statement('UPDATE permission_group_permissions pgp
            INNER JOIN permissions p ON p.uuid = pgp.permission_uuid
            SET pgp.permission_code = p.code');

        Schema::table('permission_group_permissions', function (Blueprint $table) {
            $table->dropIndex(['permission_uuid']);
            $table->dropColumn('permission_uuid');
        });
    }
}
