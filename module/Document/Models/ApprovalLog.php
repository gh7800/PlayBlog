<?php

namespace Module\Document\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalLog extends Model
{
    protected $table = 'approval_logs';
    protected $fillable = ['approvalLog_id','approvalLog_type','user_name','user_uuid','reply','status','status_title','result','step'];

    protected $casts = [
        'result' => 'integer',
        'step' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    public $timestamps = true;
    use softDeletes;

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

   public function approvalLog(): MorphTo
   {
       return $this->morphTo(__FUNCTION__,'approvalLog_type','approvalLog_id');
   }

}
