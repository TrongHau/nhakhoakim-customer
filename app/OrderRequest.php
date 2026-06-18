<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $OrderRequestId
 * @property string      $OrderRequestCode
 * @property int|null    $DepartmentType
 * @property int|null    $DepartmentId
 * @property \DateTime   $RequestDate
 * @property int         $RequestType    1: Manual, 2: Auto
 * @property int         $Status         1: Chờ duyệt, 2: Đã duyệt, 3: Đang soạn hàng, 4: Đang vận chuyển, 5: Đã hoàn tất
 * @property int         $RequestedBy
 * @property string|null $Note
 * @property int|null    $MaterialGroupId
 * @property int|null    $ProcessType
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class OrderRequest extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'OrderRequest';

    protected $primaryKey = 'OrderRequestId';

    public $timestamps = false;

    protected $fillable = [
        'OrderRequestId',
        'OrderRequestCode',
        'DepartmentType',
        'DepartmentId',
        'RequestDate',
        'RequestType',
        'ExpectedDeliveryDate',
        'Status',
        'RequestedBy',
        'Note',
        'MaterialGroupId',
        'ProcessType',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OrderRequestDetail::class, 'OrderRequestId', 'OrderRequestId');
    }

    public function outboutRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OutboutRequest::class, 'RelatedId', 'OrderRequestId');
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Branch::class, 'DepartmentId', 'BranchId');
    }
}
