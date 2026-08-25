<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 文件内容索引：与 files 一对一，存上传/补录时提取出的文本。
 * 供 AI 问答做 FULLTEXT 检索，避免每次问答读全部文件。
 */
class FileContent extends Model
{
    public $table = 'file_contents';

    protected $fillable = ['file_id', 'title', 'text', 'status', 'parse_error', 'char_count'];

    public $timestamps = true;
}
