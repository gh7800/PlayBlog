<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * M-user
 */
class BlogUser extends Model
{
    //软删除
    use SoftDeletes;

    protected $table = 'user';

    protected $fillable = [
      'username','password'
    ];

    //维护时间戳
    public $timestamps = false;

}
