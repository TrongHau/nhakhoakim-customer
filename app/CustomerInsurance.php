<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerInsurance extends Model
{
    //Define table name
    protected $table = 'CustomerInsurance';

    //Define primary key
    protected $primaryKey= 'id';

    //Define filter columns in tables
    protected $fillable = [
        'id',
        'CustomerId',
        'CompanyId',
        'InsuranceCode',
        'InsuranceType',
        'FromDate',
        'ToDate',
        'CreatedDate',
        'CreatedBy',
        'EditedDate',
        'EditedBy',
        'Status',
        'Priority'
    ];

    public $timestamps = false;

}
