<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDepartmentsTable extends Migration
{
    public function up()
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name')->comment='部门名称';
            $table->unsignedBigInteger('parent_id')->nullable()->comment='上级部门id';
            $table->string('company_uuid')->comment='所属公司uuid';
            $table->string('leader_id')->nullable()->comment='负责人uuid';
            $table->integer('sort')->default(0)->comment='排序';
            $table->tinyInteger('status')->default(1)->comment='状态: 0禁用 1启用';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('parent_id');
            $table->index('company_uuid');
            $table->index('leader_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('departments');
    }
}