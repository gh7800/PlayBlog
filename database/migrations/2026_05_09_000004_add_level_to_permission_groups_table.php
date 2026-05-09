<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLevelToPermissionGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->integer('level')->default(8)->comment('角色等级，数值越小权限越高')->after('description');
            $table->index('level');
        });
    }

    public function down()
    {
        Schema::table('permission_groups', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
}
