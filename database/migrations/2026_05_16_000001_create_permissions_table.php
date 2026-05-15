<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePermissionsTable extends Migration
{
    public function up()
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('code')->unique()->comment('权限码');
            $table->string('name')->comment('权限名称');
            $table->string('type')->default('function')->comment('类型: page-页面权限, function-功能权限');
            $table->text('description')->nullable()->comment('描述');
            $table->timestamps();

            $table->index('uuid');
            $table->index('code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('permissions');
    }
}
