<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptService extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'ReceiptService';

    //Define primaryKey
    protected $primaryKey = 'ReceiptServiceId';

    //Define filter columns in table
    protected $fillable = [
        'ReceiptServiceId',
        'ReceiptId',
        'TreatmentId',
        'OrderDetailId',
        'ServiceId',
        'Amount',
        'CreatedStaffId',
        'CreatedDate',
        'UpdatedStaffId',
        'UpdatedDate'
    ];

    public $timestamps = false;
}
