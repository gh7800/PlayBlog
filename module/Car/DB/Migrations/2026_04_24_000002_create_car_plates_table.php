<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarPlatesTable extends Migration
{
    public function up()
    {
        Schema::create('car_plates', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('plate_number')->unique()->comment='车牌号';
            $table->text('description')->nullable()->comment='描述';
            $table->tinyInteger('status')->default(0)->comment='状态：0可用，1不可用';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_plates');
    }
}
