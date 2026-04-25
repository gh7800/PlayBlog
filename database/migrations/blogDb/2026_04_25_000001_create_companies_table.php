<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCompaniesTable extends Migration
{
    public function up()
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('name')->comment='公司名称';
            $table->string('parent_id')->nullable()->comment='上级公司uuid';
            $table->string('logo')->nullable()->comment='公司LOGO';
            $table->tinyInteger('status')->default(1)->comment='状态: 0禁用 1启用';
            $table->integer('sort')->default(0)->comment='排序';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('parent_id');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('companies');
    }
}