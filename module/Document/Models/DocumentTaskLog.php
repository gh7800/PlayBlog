<?php

namespace Module\Document\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTaskLog extends Model
{
    protected $table = 'document_task_logs';
    protected $fillable = ['taskLog_id','taskLog_type','user_name','user_uuid','status','status_title'];

    public $timestamps = true;
    use softDeletes;

    protected $visible = ['user_name','user_uuid','status','status_title'];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function taskLog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__,'taskLog_type','taskLog_id');
    }
}
