<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNullableToApprovalLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            $table->string('reply')->nullable()->change();
            $table->string('status')->nullable()->change();
            $table->string('status_title')->nullable()->change();
            $table->integer('result')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('approval_logs', function (Blueprint $table) {
            //
        });
    }
}
