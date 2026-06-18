<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReceiptPending extends Model
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
    protected $table = 'ReceiptPending';

    /**
     * Primary Key
     * @var string
     */
    protected $primaryKey = 'ReceiptPendingId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ReceiptPendingId',
        'ReceiptPendingCode',
        'State',
        'CustomerId',
        'TreatmentId',
        'DepositId',
        'BranchId',
        'AppointmentId',
        'ReceiptId',
        'Receipt',
        'ReceiptDetail',
        'ReceiptType',
        'TotalAmount',
        'OrderDetail',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
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
    protected $casts = [
        'Receipt' => 'array',
        'ReceiptDetail' => 'array',
        'OrderDetail' => 'array'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;
}
