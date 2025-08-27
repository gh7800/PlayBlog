<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FileModel extends Model
{
    protected $connection = 'mysql_file';

    public $table = 'files';

    protected $fillable = ['title','file_name','file_size','file_path','file_url'];

    protected $visible = ['id','title','file_name','file_size','file_path','file_url'];

    public $timestamps = true;

    use SoftDeletes;
    protected $dates = ['deleted_at'];
}
