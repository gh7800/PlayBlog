<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRoleUuidToUserTable extends Migration
{
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('role_uuid')->nullable()->after('department_uuid');
        });
    }

    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn('role_uuid');
        });
    }
}
