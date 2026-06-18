<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentProcedureProgress extends Model
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
    protected $table = 'TreatmentProcedureProgress';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'TreatmentMedicalProcedureId',
        'ProgressName',
        'ProcedureProgressId',
        'DaysToComplete',
        'CompletedPrecentage',
        'TreatmentId',
        'MedicalProcedureId',
        'AnatomyBodyPartItemId',
        'ServiceId',
        'MinimunDaysToNextStep',
        'IsApprovedNeeded',
        'TreatmentDate',
        'CompletedDate',
        'IsAllocated',
        'AllocatedDate',
        'CompletedAt',
        'CompletedBy',
        'BranchId',
        'WanIp',
        'Migrate',
        'Delta',
        'IsNew',
        'OldJCompleted',
        'InvoiceTrackingTime',
        'HasRowInAllocatedRevenue',
        'TreatmentHistoryId',
        'LatestUpdated',
        'TreatmentSessionGroupId',
        'TreatmentSessionId'
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

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }
}
