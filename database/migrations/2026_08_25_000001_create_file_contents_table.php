<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 文件内容索引表：上传时提取文本写入，问答时 FULLTEXT 检索（两阶段架构第一阶段）。
 * 注意：
 *  1) FULLTEXT 索引带 ngram 解析器，支持中文分词（MySQL 5.7.6+ / InnoDB）。
 *  2) 该表跑在默认连接（app = app_db）上，与 files 表同库。
 */
class CreateFileContentsTable extends Migration
{
    public function up()
    {
        Schema::create('file_contents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('file_id')->unique()->comment('关联 files.id');
            $table->string('title')->comment('文件名（冗余，供全文检索命中）');
            $table->longText('text')->comment('提取出的全文文本');
            $table->string('status')->default('pending')->comment('parsed=已解析 unparseable=不支持格式 failed=解析失败 pending=待处理');
            $table->text('parse_error')->nullable()->comment('解析失败原因');
            $table->unsignedInteger('char_count')->default(0)->comment('提取文本字符数');
            $table->timestamps();
        });

        // Laravel Blueprint 不支持 WITH PARSER ngram，需原生 SQL 建 FULLTEXT 索引
        DB::statement(
            'ALTER TABLE `file_contents` ADD FULLTEXT INDEX `ft_file_contents_title_text` (`title`, `text`) WITH PARSER ngram'
        );
    }

    public function down()
    {
        Schema::dropIfExists('file_contents');
    }
}
