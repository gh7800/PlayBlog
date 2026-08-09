<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToPermissionsTable extends Migration
{
    public function up()
    {
        // create_permissions_table 已包含 type 列，此处仅对旧库补充（幂等处理）
        if (!Schema::hasColumn('permissions', 'type')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('type')->default('function')->after('name')->comment('类型: page-页面权限, function-功能权限');
            });
        }
    }

    public function down()
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
}
