<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $PurchaseOrderId
 * @property string      $PurchaseOrderCode
 * @property int         $SupplierId
 * @property string      $OrderDate
 * @property string|null $ExpectedDeliveryDate
 * @property float       $TotalAmount
 * @property int         $Status   1: Đang tạo, 2: Đã gửi NCC, 3: Đã nhận một phần, 4: Đã nhận đủ
 * @property string|null $Note
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class PurchaseOrder extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'PurchaseOrder';

    protected $primaryKey = 'PurchaseOrderId';

    public $timestamps = false;

    protected $fillable = [
        'PurchaseOrderId',
        'PurchaseOrderCode',
        'SupplierId',
        'OrderDate',
        'ExpectedDeliveryDate',
        'TotalAmount',
        'Status',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'SupplierId', 'SupplierId');
    }

    public function details(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrderDetail::class, 'PurchaseOrderId', 'PurchaseOrderId')
            ->where('Status', 1);
    }

    public function inboutRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InboutRequest::class, 'PurchaseOrderId', 'PurchaseOrderId');
    }
}
