<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('name')->comment='组名称';
            $table->string('code')->unique()->comment='编码';
            $table->text('description')->nullable()->comment='描述';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permission_groups');
    }
}
