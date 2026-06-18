<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AllocatedRevenueTracking extends Model
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
    protected $table = 'AllocatedRevenueTracking';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'AllocatedRevenueTrackingId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AllocatedRevenueTrackingId',
        'AllocatedRevenueId',
        'CustomerId',
        'OrderDetailId',
        'TreatmentMedicalProcedureId',
        'ProcedureProgressId',
        'DeltaCompletedPercent',
        'TotalTreatmentCompletedPercent',
        'TrackingType',
        'ServiceAmount',
        'DeltaRevenueCompletedPercent',
        'TotalRevenueCompletedPercent',
        'KIMRevenueAmount',
        'ClinicRevenueAmount',
        'CoLRevenueAmount',
        'TreatmentCompletedDate',
        'RevenueAllocatedDate',
        'Note',
        'ServiceId',
        'BranchId',
        'TreatmentDoctorId',
        'PromotionCode',
        'DiscountAmount',
        'TaxPercent',
        'Quantity',
        'ServicePrice',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'IsAllocated',
        'IsSyncedORC',
        'CheckInTime',
        'TransferredToDoctorTime'
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
