<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameIdTypeDocumentTaskLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('document_task_logs', function (Blueprint $table) {
            $table->renameColumn('task_log_id', 'taskLog_id');
            $table->renameColumn('task_log_type', 'taskLog_type');
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
            $table->renameColumn('taskLog_id', 'taskLog_id');
            $table->renameColumn('taskLog_type', 'taskLog_type');
        });
    }
}
