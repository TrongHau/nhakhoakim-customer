<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerInvoiceConfiguration extends Model
{
    //Define table name
    protected $table = 'CustomerInvoiceConfiguration';

    //Define primary key
    protected $primaryKey= 'CustomerInvoiceContigurationId';

    //Define filter columns in tables
    protected $fillable = [
        'CustomerInvoiceContigurationId',
        'CustomerId',
        'CompanyName',
        'CompanyAddress',
        'TaxNumber',
        'State',
        'UpdatedDate',
        'UpdatedBy'
    ];

    public $timestamps = false;

}
