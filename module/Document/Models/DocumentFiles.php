<?php

namespace Module\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentFiles extends Model
{
    protected $table = 'document_files';

    protected $fillable = ['file_id','file_type','file_name','file_path','title'];

    public $timestamps = true;

    use SoftDeletes;
    protected $dates = ['deleted_at'];

    public function file(): MorphTo
    {
        return $this->morphTo(Document::class,'file_type','file_id');
    }
}
