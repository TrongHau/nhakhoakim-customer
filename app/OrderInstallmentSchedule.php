<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderInstallmentSchedule extends Model
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
    protected $table = 'OrderInstallmentSchedule';


    protected $primaryKey = 'OrderInstallmentScheduleId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'OrderInstallmentScheduleId',
        'OrderDetailId',
        'PeriodNumber',
        'Amount',
        'OrderInstallmentScheduleStatus',
        'OrderInstallmentScheduleStatusTime',
        'DueDate',
        'DueAmount',
        'PaidAmount',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
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
