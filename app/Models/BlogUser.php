<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M-user
 */
class BlogUser extends Model
{

    protected $table = 'user';

    protected $fillable = [
      'username','password'
    ];

    //软删除
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

//    const UPDATED_AT = 'updated_at';
//    const CREATED_AT = 'created_at';

    //设置格式，默认为 'Y-m-d H:i:s'格式
    protected $dateFormat = 'Y-m-d H:i:s';




}
