<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerBank extends Model
{
    //Define table name
    protected $table = 'CustomerBank';

    //Define primary key
    protected  $primaryKey = 'CustomerBankId';

    //Define filter column
    protected $fillable = [
        'CustomerBankId',
        'CustomerId',
        'BankId',
        'BankAccNumber',
        'BankAccName',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate',
        'Status'
    ];
    public $timestamps = false;

}
