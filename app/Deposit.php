<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Deposit extends Model
{
    //Define table name
    protected $table = 'Deposit';
    //Define primaryKey
    protected $primaryKey = 'DepositId';

    //Define filter columns in table
    protected $fillable = [
        'DepositId',
        'CustomerId',
        'TotalAmount',
        'PaidAmount',
        'CurrentBalance',
        'PaidDebitVoucher',
        'CurrentDebitVoucher',
        'ExpiredDebitVoucher',
        'ExpriedDebitVoucherDate',
        'State',
        'LockedAmount',
        'Note',
        'CreatedDate',
        'LatestUpdated',
        'PaidDebtReductionAmount',
        'InvoiceDiscountAmount',
        'DebtReduction',
        'InsuranceUnPaidAmount'
    ];

    public $timestamps = false;

    //Get Customer by Deposit
    public function customer()
    {
        $this->belongsTo('App\Customer','CustomerId');
    }

    /**
     * Get list receipt from table Receipt
     */
    public function receipts()
    {
        return $this->hasMany('App\Receipt','DepositId','DepositId');
    }
}
