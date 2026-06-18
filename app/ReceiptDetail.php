<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptDetail extends Model
{
    protected $connection = 'mysql';
    //Define table name
    protected $table = 'ReceiptDetail';

    //Define primaryKey
    protected $primaryKey = 'ReceiptDetailId';

    //Define filter columns in table
    protected $fillable = [
        'ReceiptId',
        'BankId',
        'Amount',
        'PartnerCompanyId',
        'PaymentMethodId',
        'OrderDetailId',
        'PrepayCardId',
        'ReceiptDetailId',
        'GatewaySourceId',
        'InstallmentPaymentPartnerId',
        'MCC',
        'ForControlCode',
        'LatestUpdated',
        'CreatedDate',
        'CreatedStaffId',
        'UpdatedDate',
        'UpdatedStaffId'
    ];

    public $timestamps = false;
}
