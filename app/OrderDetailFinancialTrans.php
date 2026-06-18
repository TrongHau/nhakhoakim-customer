<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderDetailFinancialTrans extends Model
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
    protected $table = 'OrderDetailFinancialTrans';

    protected $primaryKey = 'OrderDetailFinancialTransId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'OrderDetailFinancialTransId',
        'TreatmentId',
        'CustomerId',
        'OrderDetailId',
        'OrderChangingId',
        'ServiceId',
        'ObjectType',
        'ObjectId',
        'ObjectDetailType',
        'ObjectDetailId',
        'OrderDetailAmount',
        'InvoiceAmount',
        'ReceiptAmount',
        'TransferAmount',
        'ExpenditureAmount',
        'PaymentAmount',
        'InsuranceAmount',
        'ExistingProgressedAmount',
        'ExistingReceiptAmount',
        'ExistingTransferAmount',
        'ExistingExpenditureAmount',
        'ExistingPaymentAmount',
        'ExistingInsuranceAmount',
        'Note',
        'CreatedStaffId',
        'CreatedDate',
        'ConsultingStaffId',
        'ConsultedBranchId'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;
}
