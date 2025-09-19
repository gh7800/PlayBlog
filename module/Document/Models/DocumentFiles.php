<?php

namespace Module\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DocumentFiles extends Model
{
    protected $table = 'document_files';
    /* 让每条记录都带上 file_url */
    protected $appends = ['file_url'];

    protected $fillable = ['file_id','file_type','file_name','file_path','title','file_url'];

    public $timestamps = true;

    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function file(): MorphTo
    {
        return $this->morphTo(Document::class,'file_type','file_id');
    }

    /* 访问器：$file->file_url */
    public function getFileUrlAttribute(): ?string
    {
        if($this->file_path == null){
            return null;
        }
        // 如果库里有 file_path 字段就直接用
        return config('app.url') . Storage::url($this->file_path);
    }
}
