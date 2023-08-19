<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * M-user
 */
class BlogUser extends Model
{
    protected $table = 'user';

    protected $fillable = [
      'username','password'
    ];

    //维护时间戳
    public $timestamps = false;

}
