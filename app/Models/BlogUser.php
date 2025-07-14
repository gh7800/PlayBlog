<?php

namespace App\Models;

use Eloquent;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Ramsey\Uuid\Uuid;

/**
 * M-user
 *
 * @property int $id
 * @property string|null $username
 * @property string|null $password
 * @property string|null $real_name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser newQuery()
 * @method static \Illuminate\Database\Query\Builder|BlogUser onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereRealName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|BlogUser whereUsername($value)
 * @method static \Illuminate\Database\Query\Builder|BlogUser withTrashed()
 * @method static \Illuminate\Database\Query\Builder|BlogUser withoutTrashed()
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
      'username','password','real_name','token','phone','version','email','address'
    ];

    //软删除
    //use SoftDeletes;
    //protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

    //protected $dateFormat = 'Y-m-d H:i:s';




}
