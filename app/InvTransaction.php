<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvTransaction extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'InvTransaction';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'InvTransactionId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'InvTransactionId',
        'ProductId',
        'UnitId',
        'ConditionTypeId',
        'AvailableQty',
        'PendingOutQty',
        'PendingInQty',
        'FreezeQty',
        'TransactionType',
        'RefId',
        'Note',
        'CreatedBy',
        'CreatedDate'
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
