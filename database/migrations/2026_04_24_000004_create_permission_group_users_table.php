<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupUsersTable extends Migration
{
    public function up()
    {
        Schema::create('permission_group_users', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('group_uuid');
            $table->string('user_uuid');
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('group_uuid');
            $table->index('user_uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_group_users');
    }
}
