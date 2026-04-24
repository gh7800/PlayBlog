<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CarTaskLog extends Model
{
    protected $table = 'document_task_logs';

    protected $fillable = ['taskLog_id', 'taskLog_type', 'user_uuid', 'user_name', 'status', 'status_title'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public $timestamps = true;
    use SoftDeletes;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function taskLog(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'taskLog_type', 'taskLog_id');
    }
}
