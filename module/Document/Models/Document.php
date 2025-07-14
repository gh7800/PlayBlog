<?php

namespace Module\Document\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class Document extends Model
{
    protected $table = 'documents';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['title','content'];

    //软删除
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

    //设置格式，默认为 'Y-m-d H:i:s'格式
    protected $dateFormat = 'Y-m-d H:i:s';
}
