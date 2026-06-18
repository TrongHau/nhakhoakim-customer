<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $OrderRequestDetailId
 * @property int         $OrderRequestId
 * @property int         $ProductId
 * @property int|null    $UnitId
 * @property string|null $Specification
 * @property int         $RequestQuantity
 * @property int         $ReceivedQuantity
 * @property string|null $Note
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class OrderRequestDetail extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'OrderRequestDetail';

    protected $primaryKey = 'OrderRequestDetailId';

    public $timestamps = false;

    protected $fillable = [
        'OrderRequestDetailId',
        'OrderRequestId',
        'ProductId',
        'UnitId',
        'Specification',
        'RequestQuantity',
        'ReceivedQuantity',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function orderRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'OrderRequestId', 'OrderRequestId');
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
