<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticesTable extends Migration
{
    public function up()
    {
        Schema::create('notice_notices', function (Blueprint $table) {
            $table->id();
            $table->char('uuid', 36)->unique();
            $table->string('title', 255);
            $table->text('content');
            $table->char('sender_uuid', 36);
            $table->string('sender_name', 100);
            $table->tinyInteger('is_deleted')->default(0);
            $table->timestamps();

            $table->index('sender_uuid');
            $table->index('is_deleted');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_notices');
    }
}
