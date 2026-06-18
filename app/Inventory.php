<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $InventoryId
 * @property int         $ProductId
 * @property int         $WarehouseId
 * @property int         $Quantity
 * @property int|null    $MinStock
 * @property float|null  $TotalValue
 * @property int         $Status
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class Inventory extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'Inventory';

    protected $primaryKey = 'InventoryId';

    public $timestamps = false;

    protected $fillable = [
        'InventoryId',
        'ProductId',
        'WarehouseId',
        'Quantity',
        'MinStock',
        'TotalValue',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductId', 'ProductId');
    }

    public function warehouse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'WarehouseId', 'WarehouseId');
    }

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'InventoryId', 'InventoryId');
    }
}
