<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentProcedureProgressTracking extends Model
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
    protected $table = 'TreatmentProcedureProgressTracking';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'TreatmentId',
        'TreatmentHistoryId',
        'TreatmentMedicalProcedureId',
        'MedicalProcedureId',
        'AnatomyBodyPartItemId',
        'ServiceId',
        'FromStep',
        'ToStep',
        'FromPercentage',
        'ToPercentage',
        'TreatmentDate',
        'CompletedDate',
        'CompletedAt',
        'CompletedBy',
        'CreatedDate',
        'BranchId',
        'WanIp',
        'LastestProcessName'
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

    public function chiefComplaint()
    {
        return $this->belongsTo(
            Treatment::class,
            'TreatmentId',
            'TreatmentId'
        );
    }
}
