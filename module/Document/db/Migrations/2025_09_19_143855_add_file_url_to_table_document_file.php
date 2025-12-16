<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddFileUrlToTableDocumentFile extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->string('file_url')->nullable()->after('file_path');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('document_files', function (Blueprint $table) {
            $table->dropColumn('file_url');
        });
    }
}
