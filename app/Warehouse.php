<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $WarehouseId
 * @property string      $Name
 * @property int         $WarehouseType  1: Công ty, 5: Kho tạm, 10: Phòng khám
 * @property int|null    $BranchId
 * @property int|null    $Type
 * @property string|null $Address
 * @property int         $Status
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class Warehouse extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'Warehouse';

    protected $primaryKey = 'WarehouseId';

    public $timestamps = false;

    protected $fillable = [
        'Name',
        'WarehouseType',
        'BranchId',
        'Type',
        'Address',
        'Status',
    ];

    protected $hidden = [];

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'WarehouseId', 'WarehouseId');
    }
}
