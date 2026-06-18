<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerSMSQueue extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_schedule';
    
    //Table name
    protected $table = 'CustomerSMSQueue';

    //Primary key
    protected $primaryKey = 'CustomerSMSQueueId';

    //Filter column
    protected $fillable = [
        'CustomerSMSQueueId',
        'PhoneNumber',
        'PromotionCode',
        'VoucherCode',
        'Message',
        'JsonData',
        'Status',
        'SentDate',
        'Sender',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
    ];
    public $timestamps = false;
}
