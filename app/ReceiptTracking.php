<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptTracking extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'ReceiptTracking';

    //Define primaryKey
    protected $primaryKey = 'ReceiptTrackingId';

    //Define filter columns in table
    protected $fillable = [
        'ReceiptTrackingId',
        'ReceiptId',
        'ActionId',
        'OldData',
        'NewData',
        'CreatedBy',
        'CreatedDate'
    ];

    public $timestamps = false;
}
