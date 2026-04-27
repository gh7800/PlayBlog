<?php

namespace Module\Notice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoticeReceiver extends Model
{
    protected $table = 'notice_receivers';

    protected $fillable = [
        'notice_id',
        'user_uuid',
    ];

    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class, 'notice_id');
    }
}
