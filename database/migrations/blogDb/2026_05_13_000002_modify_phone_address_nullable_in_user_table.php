<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyPhoneAddressNullableInUserTable extends Migration
{
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('phone')->nullable()->change();
            $table->string('address')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->string('phone')->nullable(false)->change();
            $table->string('address')->nullable(false)->change();
        });
    }
}
