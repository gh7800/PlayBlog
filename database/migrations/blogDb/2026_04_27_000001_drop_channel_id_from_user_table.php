<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DropChannelIdFromUserTable extends Migration
{
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            if (Schema::hasColumn('user', 'channel_id')) {
                $table->dropColumn('channel_id');
            }
        });
    }

    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('channel_id')->nullable()->comment('设备渠道Id');
        });
    }
}
