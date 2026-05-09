<?php

namespace Module\Car\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApprovalChain extends Model
{
    protected $table = 'car_approval_chains';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    public function nodes(): HasMany
    {
        return $this->hasMany(CarApprovalNode::class, 'chain_uuid', 'uuid')
                    ->orderBy('step', 'asc');
    }

    public static function getActiveChain(): ?self
    {
        return self::where('is_active', 1)->first();
    }
}
