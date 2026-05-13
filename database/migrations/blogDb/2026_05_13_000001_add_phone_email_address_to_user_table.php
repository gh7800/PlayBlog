<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPhoneEmailAddressToUserTable extends Migration
{
    public function up()
    {
        Schema::table('user', function (Blueprint $table) {
            if (!Schema::hasColumn('user', 'email')) {
                $table->string('email')->nullable()->after('status');
            }
            if (!Schema::hasColumn('user', 'phone')) {
                $table->string('phone')->after('email');
            }
            if (!Schema::hasColumn('user', 'address')) {
                $table->string('address')->after('phone');
            }
        });
    }

    public function down()
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['email', 'phone', 'address']);
        });
    }
}
