<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'Receipt';

    //Define primaryKey
    protected $primaryKey = 'ReceiptId';

    //Define filter columns in table
    protected $fillable = [
        'ReceiptId',
        'ReceiptCode',
        'RefReceiptId',
        'DepositId',
        'TotalAmount',
        'Note',
        'AddedAt',
        'AddedBy',
        'UpdatedAt',
        'UpdatedBy',
        'BranchId',
        'LockedAt',
        'LockedBy',
        'State',
        'KimPaymentId',
        'TypeMigrate',
        'BranchIdBackup',
        'AppointmentId',
        'ReceiptStatusId',
        'ReceipStatusDate',
        'InsuranceRequestedDate',
        'LatestUpdated',
        'IsNotified',
        'InsuranceAmount',
        'InsuranceSendBy',
        'InsuranceSendDate',
        'InsuranceDueDate',
        'InsurancePaidAmount',
        'InsuranceUpdatedBy',
        'InsuranceUpdatedDate',
        'ReceiptType',
        'InsuranceUnPaidAmount',
        'ReceiptInsuranceStatusId',
        'TreatmentId'
    ];

    /**
     * Get deposit from Receipt
     */
    public function deposit()
    {
        return $this->belongsTo('App\Deposit','DepositId','DepositId');
    }

    //Define created and updated
    public const CREATED_AT = 'CreateAt';
    public const UPDATED_AT = 'UpdatedAt';
}
