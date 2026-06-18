<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $PurchaseOrderDetailId
 * @property int         $PurchaseOrderId
 * @property int         $ProductId
 * @property int|null    $UnitId
 * @property string|null $Specification
 * @property int         $Quantity
 * @property float       $UnitPrice
 * @property float       $Amount
 * @property int         $ReceivedQuantity
 * @property string|null $Note
 * @property int         $Status   1: active, 0: xóa mềm
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class PurchaseOrderDetail extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'PurchaseOrderDetail';

    protected $primaryKey = 'PurchaseOrderDetailId';

    public $timestamps = false;

    protected $fillable = [
        'PurchaseOrderDetailId',
        'PurchaseOrderId',
        'ProductId',
        'UnitId',
        'Specification',
        'Quantity',
        'UnitPrice',
        'Amount',
        'ReceivedQuantity',
        'Note',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function purchaseOrder(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'PurchaseOrderId', 'PurchaseOrderId');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductId', 'ProductId');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnitInventory::class, 'UnitId', 'UnitId');
    }
}
