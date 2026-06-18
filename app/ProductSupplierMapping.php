<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $ProductSupplierMappingId
 * @property int         $ProductId
 * @property int         $SupplierId
 * @property int|null    $Status
 * @property int|null    $Priority
 * @property date        $CreatedDate
 */
class ProductSupplierMapping extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductSupplierMapping';

    protected $primaryKey = 'ProductSupplierMappingId';

    public $timestamps = false;

    protected $fillable = [
        'ProductSupplierMappingId',
        'ProductId',
        'SupplierId',
        'Status',
        'Priority',
        'CreatedDate'
    ];

    protected $hidden = [];

}
