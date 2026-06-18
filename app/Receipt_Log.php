<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Receipt_Log extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'Receipt_Log';

    //Define primaryKey
    protected $primaryKey = 'id';

    //Define filter columns in table
    protected $fillable = [
        'id',
        'ReceiptId',
        'UpdatedAt',
        'UpdatedBy',
        'TotalAmount',
        'Type'
    ];

    public $timestamps = false;
}
