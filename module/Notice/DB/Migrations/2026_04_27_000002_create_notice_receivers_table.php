<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticeReceiversTable extends Migration
{
    public function up()
    {
        Schema::create('notice_receivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notice_id');
            $table->char('user_uuid', 36);
            $table->timestamps();

            $table->foreign('notice_id')
                ->references('id')
                ->on('notice_notices')
                ->onDelete('cascade');
            $table->index('user_uuid');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_receivers');
    }
}
