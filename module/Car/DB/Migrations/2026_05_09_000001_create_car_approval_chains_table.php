<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApprovalChainsTable extends Migration
{
    public function up()
    {
        Schema::create('car_approval_chains', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('name', 100)->comment('链表名称');
            $table->string('description', 255)->nullable()->comment('描述');
            $table->tinyInteger('is_active')->default(0)->comment('是否启用 1=启用');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_approval_chains');
    }
}
