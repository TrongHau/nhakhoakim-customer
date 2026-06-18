<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PayTransaction extends Model
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
    protected $table = 'PayTransaction';

    /**
     * Primary key
     */
    protected $primaryKey = 'UUID';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'UUID',
        'ProviderId',
        'ReceiptId',
        'OrderId',
        'BranchId',
        'PosTerminalId',
        'Amount',
        'Currency',
        'PayType',
        'Description',
        'InternalStatus',
        'InternalStatusDate',
        'ProviderStatus',
        'ProviderStatusDate',
        'ProviderTxnId',
        'ProviderRefId',
        'RequestPayload',
        'ResponsePayload',
        'CreatedDate',
        'UpdatedDate'
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
