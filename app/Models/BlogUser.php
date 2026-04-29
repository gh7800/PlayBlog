<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Ramsey\Uuid\Uuid;

/**
 * 用户表
 * @mixin Eloquent
 */
class BlogUser extends Authenticatable
{
    use HasApiTokens,Notifiable,SoftDeletes;

    protected $table = 'user';

    protected static function booted()
    {
        static::creating(function ($model) {
           if (empty($model->uuid)) {
               $model->uuid = Uuid::uuid4()->toString();
           }
        });
    }

    protected $fillable = [
      'username','password','real_name','token','phone','version','email','address','push_id','company_uuid','department_uuid'
    ];

    //软删除
    //use SoftDeletes;
    //protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

    //protected $dateFormat = 'Y-m-d H:i:s';

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_uuid', 'uuid');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_uuid', 'uuid');
    }
}
