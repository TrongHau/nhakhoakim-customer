<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $InventoryTransactionId
 * @property int         $InventoryId
 * @property int         $TransactionType   1: Nhập kho (+), 2: Xuất kho (-)
 * @property int         $ProductId
 * @property int|null    $UnitId
 * @property int         $Quantity
 * @property string|null $ReferenceType
 * @property int|null    $ReferenceId
 * @property string|null $Note
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class InventoryTransaction extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'InventoryTransaction';

    protected $primaryKey = 'InventoryTransactionId';

    public $timestamps = false;

    protected $fillable = [
        'InventoryTransactionId',
        'InventoryId',
        'TransactionType',
        'ProductId',
        'UnitId',
        'Quantity',
        'ReferenceType',
        'ReferenceId',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function inventory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Inventory::class, 'InventoryId', 'InventoryId');
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
