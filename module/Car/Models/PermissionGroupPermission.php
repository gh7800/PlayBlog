<?php

namespace Module\Car\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class PermissionGroupPermission extends Model
{
    protected $table = 'permission_group_permissions';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'group_uuid', 'permission_code'];

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
}
