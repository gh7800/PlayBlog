<?php

namespace Module\Car\Models;

use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Ramsey\Uuid\Uuid;

class CarApprovalNode extends Model
{
    protected $table = 'car_approval_nodes';

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = Uuid::uuid4()->toString();
            }
        });
    }

    protected $fillable = ['uuid', 'chain_uuid', 'step', 'name', 'approver_type', 'approver_value'];

    protected $casts = [
        'step' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    use SoftDeletes;
    protected $dates = ['deleted_at'];
    public $timestamps = true;

    public function chain()
    {
        return $this->belongsTo(CarApprovalChain::class, 'chain_uuid', 'uuid');
    }

    public function getApproverUuids($applicant = null): array
    {
        switch ($this->approver_type) {
            case 'permission_group':
                $group = PermissionService::getGroupByCode($this->approver_value);
                if (!$group) {
                    return [];
                }
                return $group->users()->withTrashed()->get()->pluck('user_uuid')->toArray();

            case 'user':
                return [$this->approver_value];

            case 'dept_head':
                return [];

            default:
                return [];
        }
    }
}
