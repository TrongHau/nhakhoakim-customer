<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DepositTransaction extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'DepositTransaction';
    //Define primaryKey
    protected $primaryKey = 'DepositTransactionId';

    //Define filter columns in table
    protected $fillable = [
        'DepositTransactionId',
        'DepositId',
        'CustomerId',
        'BranchId',
        'Type',
        'Amount',
        'ObjectType',
        'ObjectId',
        'RefObjectId',
        'BalanceType',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'LatestUpdated'
    ];

    public $timestamps = false;
}
