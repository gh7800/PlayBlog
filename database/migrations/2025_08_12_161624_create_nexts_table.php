<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNextsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('next', function (Blueprint $table) {
            $table->id()->autoIncrement();
            //$table->uuid('parent_uuid')->unique();
            $table->string('text');
            $table->string('step');

            // 多态关联字段
            $table->uuid('nextTable_id');  // 关联目标模型的UUID
            $table->string('nextTable_type'); // 关联目标模型的类名

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('next');
    }
}
