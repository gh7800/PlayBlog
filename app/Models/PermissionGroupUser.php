<?php

namespace App\Models;

use App\Models\BlogUser;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class PermissionGroupUser extends Model
{
    protected $table = 'permission_group_users';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'group_uuid', 'user_uuid'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PermissionGroup::class, 'group_uuid');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(BlogUser::class, 'user_uuid', 'uuid');
    }
}
