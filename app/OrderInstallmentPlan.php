<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderInstallmentPlan extends Model
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
    protected $table = 'OrderInstallmentPlan';


    protected $primaryKey = 'OrderDetailId';

    public $incrementing = false;

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'OrderDetailId',
        'OrderInstallmentPlanStatus',
        'OrderInstallmentPlanStatusTime',
        'ServiceInstallmentConfigId',
        'DownPaymentRequired',
        'MonthlyAmount',
        'TotalPeriods',
        'RemainingPeriods',
        'PaidAmount',
        'OutstandingAmount',
        'StartInstallmentDate',
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
