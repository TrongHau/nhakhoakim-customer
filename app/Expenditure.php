<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Expenditure extends Model
{
    // Define table name
    protected $table = 'Expenditure';

    //Define primary key
    protected $primaryKey = 'ExpenditureId';

    public $incrementing = true;

    //Define filter coulumns in table
    protected $fillable = [
        'ExpenditureId',
        'ExpenditureCode',
        'Amount',
        'ReceiverName',
        'Note',
        'CreatedAt',
        'CreatedBy',
        'BranchId',
        'BankId',
        'EditedAt',
        'EditedBy',
        'ExpenditureStatusId',
        'ExpenditureCategoryId',
        'ExpenditureTypeId',
        'PaymentMethodId',
        'RefId',
        'LatestUpdated',
        'TreatmentId'
    ];

    /**
     * Get Customer by ExpenditureId
     */
    public function customer()
    {
        return $this->belongsTo('App\Customer','RefId','CustomerId');
    }
    public $timestamps = false;
}
