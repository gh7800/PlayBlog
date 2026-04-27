<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNoticeFilesTable extends Migration
{
    public function up()
    {
        Schema::create('notice_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('notice_id');
            $table->string('file_url', 500);
            $table->string('file_name', 255);
            $table->bigInteger('file_size')->default(0);
            $table->timestamps();

            $table->foreign('notice_id')
                ->references('id')
                ->on('notice_notices')
                ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('notice_files');
    }
}
