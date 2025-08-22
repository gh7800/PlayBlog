<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFieldsToTaskLogTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('document_task_logs', function (Blueprint $table) {
            $table->string('task_log_type');
            $table->string('task_log_id');
            $table->string('status');
            $table->string('status_title');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('document_task_logs', function (Blueprint $table) {
            $table->dropColumn('task_log_type');
            $table->dropColumn('task_log_id');
            $table->dropColumn('status');
            $table->dropColumn('status_title');
        });
    }
}
