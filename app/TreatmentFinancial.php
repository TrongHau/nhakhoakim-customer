<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentFinancial extends Model
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'TreatmentFinancial';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = null;
    public $incrementing = false;

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TreatmentId',
        'CustomerId',
        'OrderDetailAmount',
        'InvoiceAmount',
        'ReceiptAmount',
        'InsuranceAmount',
        'TransferAmount',
        'ExpenditureAmount',
        'PaymentAmount',
        'TotalAmount',
        'AvailableAmount',
        'DueAmount',
        'Note',
        'CreatedStaffId',
        'CreatedDate',
        'UpdatedStaffId',
        'UpdatedDate'
    ];

    public $timestamps = false;
}
