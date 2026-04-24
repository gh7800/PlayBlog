<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupPermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('permission_group_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('group_uuid');
            $table->string('permission_code')->comment='权限码';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('group_uuid');
            $table->index('permission_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_group_permissions');
    }
}
