<?php

namespace Module\Car\Models;

use App\Models\Next;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApplication extends Model
{
    protected $table = 'car_applications';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = [
        'uuid', 'user_uuid', 'user_name', 'car_type', 'reason',
        'passenger_count', 'use_time', 'remark', 'status',
        'status_title', 'step', 'approved_plate_id', 'approved_plate_number',
        'reject_reason', 'start_km', 'end_km'
    ];

    protected $casts = [
        'step' => 'integer',
        'passenger_count' => 'integer',
        'start_km' => 'decimal:2',
        'end_km' => 'decimal:2',
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

    public function logs(): MorphMany
    {
        return $this->morphMany(CarLog::class, 'approvalLog');
    }

    public function taskLogs(): MorphMany
    {
        return $this->morphMany(CarTaskLog::class, 'taskLog');
    }

    public function next(): MorphMany
    {
        return $this->morphMany(Next::class, 'nextTable');
    }

    public function plate(): BelongsTo
    {
        return $this->belongsTo(CarPlate::class, 'approved_plate_id');
    }
}
