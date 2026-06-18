<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PayTransactionEvent extends Model
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
    protected $table = 'PayTransactionEvent';

    /**
     * Primary key
     */
    protected $primaryKey = 'TransactionEventId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TransactionEventId',
        'PayTransactionId',
        'EventType',
        'ProviderStatus',
        'InternalStatus',
        'Message',
        'Payload',
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
