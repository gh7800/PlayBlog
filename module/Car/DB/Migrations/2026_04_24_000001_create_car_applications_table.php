<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCarApplicationsTable extends Migration
{
    public function up()
    {
        Schema::create('car_applications', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('user_uuid');
            $table->string('user_name');
            $table->enum('car_type', ['general', 'business', 'other'])->comment='用车类型';
            $table->text('reason')->comment='用车事由';
            $table->integer('passenger_count')->comment='用车人数';
            $table->datetime('use_time')->comment='用车时间';
            $table->text('remark')->nullable()->comment='备注';
            $table->string('status')->default('applying')->comment='状态';
            $table->string('status_title')->comment='状态中文';
            $table->integer('step')->default(1)->comment='步骤';
            $table->bigInteger('approved_plate_id')->nullable()->comment='审批车牌ID';
            $table->string('approved_plate_number')->nullable()->comment='审批车牌号';
            $table->text('reject_reason')->nullable()->comment='拒绝原因';
            $table->decimal('start_km', 10, 2)->nullable()->comment='开始公里数';
            $table->decimal('end_km', 10, 2)->nullable()->comment='结束公里数';
            $table->timestamps();
            $table->softDeletes();

            $table->index('uuid');
            $table->index('user_uuid');
            $table->index('status');
        });
    }

    public function down()
    {
        Schema::dropIfExists('car_applications');
    }
}
