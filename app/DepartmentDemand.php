<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DepartmentDemand extends Model
{
    protected $connection = 'mysql_inventory';
    //Define table name
    protected $table = 'DepartmentDemand';
    //Define primaryKey
    protected $primaryKey = 'DepartmentDemandId';

    //Define filter columns in table
    protected $fillable = [
        'DepartmentDemandId',
        'DepartmentId',
        'DepartmentType',
        'ProductId',
        'UnitId',
        'PendingQty',
        'TotalRequestedQty',
        'TotalDeliveredQty',
        'DeliveryDate',
        'ExpectedDeliveryDate',
        'ExpectedReceiptDate',
        'UpdatedDate'
    ];

    public $timestamps = false;

}
