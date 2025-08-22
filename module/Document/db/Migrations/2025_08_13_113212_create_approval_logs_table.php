<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovalLogsTable extends Migration
{
    /**
     * Run the migrations.
     * $fillable = ['approvalLog_id','user_name','user_uuid','reply','status','status_title','result','step'];
     * @return void
     */
    public function up()
    {
        Schema::create('approval_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('approvalLog_id');
            $table->uuid('approvalLog_type');
            $table->string('user_name');
            $table->string('user_uuid');
            $table->string('reply');
            $table->string('status');
            $table->string('status_title');
            $table->integer('result');
            $table->integer('step');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('approval_logs');
    }
}
