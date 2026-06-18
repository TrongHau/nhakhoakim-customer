<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantOrderDetail extends Model
{
    //Define table name
    protected $table = 'ImplantOrderDetail';

    //Define primary key
    protected  $primaryKey = 'ImplantOrderDetailId';

    //Define filter column
    protected $fillable = [
        'ImplantOrderDetailId',
        'ImplantOrderId',
        'TechnicalSpecificationId',
        'Quantity',
        'Amount',
        'Unit',
        'Note',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
    ];
    public $timestamps = false;

}