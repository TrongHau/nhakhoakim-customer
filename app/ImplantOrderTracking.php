<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantOrderTracking extends Model
{
    //Define table name
    protected $table = 'ImplantOrderTracking';

    //Define primary key
    protected  $primaryKey = 'ImplantOrderTrackingId';

    //Define filter column
    protected $fillable = [
        'ImplantOrderTrackingId',
        'ImplantOrderId',
        'Type',
        'Content',
        'CreatedDate',
        'CreatedBy'
    ];
    public $timestamps = false;

}