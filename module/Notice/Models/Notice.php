<?php

namespace Module\Notice\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Notice extends Model
{
    protected $table = 'notice_notices';

    protected $fillable = [
        'uuid',
        'title',
        'content',
        'sender_uuid',
        'sender_name',
        'is_deleted',
    ];

    protected $casts = [
        'is_deleted' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function receivers(): HasMany
    {
        return $this->hasMany(NoticeReceiver::class, 'notice_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(NoticeFile::class, 'notice_id');
    }
}
