<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DepartmentDemandLog extends Model
{
    protected $connection = 'mysql_inventory';
    //Define table name
    protected $table = 'DepartmentDemandLog';
    //Define primaryKey
    protected $primaryKey = 'DepartmentDemandLogId';

    //Define filter columns in table
    protected $fillable = [
        'DepartmentDemandLogId',
        'DepartmentDemandId',
        'DepartmentId',
        'ProductId',
        'UnitId',
        'ChangeType',
        'ChangeQty',
        'QtyBefore',
        'QtyAfter',
        'RefType',
        'RefId',
        'Note',
        'CreatedDate',
        'CreatedBy'
    ];

    public $timestamps = false;

}
