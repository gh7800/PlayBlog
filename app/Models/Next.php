<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Next extends Model
{
    //
    protected $table = 'next';

    protected $primaryKey = 'id';

    protected $fillable = ['text','step','nextTable_id','nextTable_type'];

    //软删除
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

    //设置格式，默认为 'Y-m-d H:i:s'格式
    protected $dateFormat = 'Y-m-d H:i:s';

    protected $visible = ['text','step'];

    public function nextTable(): MorphTo
    {
        return $this->morphTo(__FUNCTION__,'nextTable_type','nextTable_id');
    }
}
