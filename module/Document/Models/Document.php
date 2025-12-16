<?php
namespace Module\Document\Models;

use App\Models\Next;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
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

    protected $fillable = ['title','content','code','uuid','step','status','status_title','user_name','user_uuid','description'];

    protected $casts = [
        'step' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    //软删除
    use SoftDeletes;
    protected $dates = ['deleted_at'];

    //维护时间戳
    public $timestamps = true;

    //设置格式，默认为 'Y-m-d H:i:s'格式
    protected $dateFormat = 'Y-m-d H:i:s';

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    //protected $with = ['next','logs','taskLogs'];

    public function files(): MorphMany
    {
        return $this->morphMany(DocumentFiles::class, 'file');
    }

    public function next(): MorphMany
    {
        return $this->morphMany(Next::class, 'nextTable');
    }

    public function logs(): MorphMany
    {
        return $this->morphMany(ApprovalLog::class,'approvalLog');
    }

    public function taskLogs(): MorphMany
    {
        return $this->morphMany(DocumentTaskLog::class,'taskLog');
    }
}
