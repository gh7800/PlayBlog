<?php

namespace Module\Car\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarModel extends Model
{
    protected $table = 'car';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['title','uuid'];

    use SoftDeletes;

    public $timestamps = true;
}
