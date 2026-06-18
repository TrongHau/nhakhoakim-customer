<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $OutboutRequestId
 * @property int|null    $PurchaseOrderId
 * @property int|null    $RelatedType
 * @property int|null    $RelatedId
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property \DateTime|null $ActualArrivalDate
 * @property int|null    $TotalSKU
 * @property string|null $IRCode
 * @property int         $Status   1: Đang giao, 2: Đã xác nhận
 */
class OutboutRequest extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'OutboutRequest';

    protected $primaryKey = 'OutboutRequestId';

    public $timestamps = false;

    protected $fillable = [
        'OutboutRequestId',
        'OutboutCode',
        'PurchaseOrderId',
        'DepartmentType',
        'DepartmentId',
        'RelatedType',
        'RelatedId',
        'ActualArrivalDate',
        'TotalSKU',
        'IRCode',
        'Status',
        'ExpectedReceiptDate',
        'DeliveryStaff',
        'DeliveryDate',
        'Note',
        'BranchNote',
        'CreatedBy',
        'CreatedDate'
    ];

    protected $hidden = [];

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(OutboutRequestDetail::class, 'OutboutRequestId', 'OutboutRequestId');
    }

    public function orderRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'RelatedId', 'OrderRequestId');
    }
}
