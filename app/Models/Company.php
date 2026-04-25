<?php

namespace App\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Company extends Model
{
    use SoftDeletes;

    protected $table = 'companies';
    protected $dates = ['deleted_at'];
    public $timestamps = true;
    protected $dateFormat = 'Y-m-d H:i:s';

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
        'uuid',
        'name',
        'parent_id',
        'logo',
        'status',
        'sort',
    ];

    protected $casts = [
        'status' => 'integer',
        'sort' => 'integer',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'parent_id', 'uuid');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Company::class, 'parent_id', 'uuid');
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'company_uuid', 'uuid');
    }
}