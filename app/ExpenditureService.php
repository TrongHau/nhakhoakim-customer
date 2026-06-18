<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ExpenditureService extends Model
{
    protected $connection = 'mysql';
    // Define table name
    protected $table = 'ExpenditureService';

    //Define primary key
    protected $primaryKey = 'ExpenditureServiceId';

    //Define filter coulumns in table
    protected $fillable = [
        'ExpenditureServiceId',
        'ExpenditureId',
        'TreatmentId',
        'OrderDetailId',
        'ServiceId',
        'Amount',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    public $timestamps = false;
}
