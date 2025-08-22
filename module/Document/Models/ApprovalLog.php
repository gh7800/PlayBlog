<?php

namespace Module\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalLog extends Model
{
    //
    protected $table = 'approval_logs';
    protected $fillable = ['approvalLog_id','approvalLog_type','user_name','user_uuid','reply','status','status_title','result','step'];

    protected $casts = [
        'result' => 'integer',
        'step' => 'integer'
    ];

    public $timestamps = true;
    use softDeletes;

   public function approvalLog(): MorphTo
   {
       return $this->morphTo(__FUNCTION__,'approvalLog_type','approvalLog_id');
   }

}
