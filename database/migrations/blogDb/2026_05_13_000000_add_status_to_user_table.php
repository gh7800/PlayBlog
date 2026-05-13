<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusToUserTable extends Migration
{
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->tinyInteger('status')->default(1)->after('role_uuid')->comment('1启用 0禁用');
        });
    }

    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
}
