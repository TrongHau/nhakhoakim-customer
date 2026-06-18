<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppointmentRevenue extends Model
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
    protected $table = 'AppointmentRevenue';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = NULL;

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AppointmentId',
        'PaymentId',
        'ReceiptId',
        'PrepayCardId',
        'CustomerId',
        'Amount',
        'Date',
        'BranchId',
        'UpdatedAt',
        'PushedAt'
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

    //Disable create_at and update_at automactic add query
    public $timestamps = false;
}
