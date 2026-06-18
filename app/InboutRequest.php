<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $InboutRequestId
 * @property int|null    $PurchaseOrderId
 * @property int|null    $RelatedType
 * @property int|null    $RelatedId
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property \DateTime|null $ActualArrivalDate
 * @property int|null    $TotalSKU
 * @property string|null $IRCode
 * @property int         $Status   1: Đang kiểm tra, 2: Đã nhập
 * @property int|null    $RefOutboutRequestId
 */
class InboutRequest extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'InboutRequest';

    protected $primaryKey = 'InboutRequestId';

    public $timestamps = false;

    protected $fillable = [
        'PurchaseOrderId',
        'RelatedType',
        'RelatedId',
        'Note',
        'ActualArrivalDate',
        'IRCode',
        'Status',
        'RefOutboutRequestId',
    ];

    protected $hidden = [];

    public function purchaseOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderId', 'PurchaseOrderId');
    }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InboutRequestDetail::class, 'InboutRequestId', 'InboutRequestId');
    }
}
