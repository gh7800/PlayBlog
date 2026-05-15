<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTypeToPermissionGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->string('type')->nullable()->after('level')->comment('类型: user=人员组, permission=权限组');
            $table->index('type');
        });
    }

    public function down()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
}
