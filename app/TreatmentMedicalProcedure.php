<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentMedicalProcedure extends Model
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
    protected $table = 'TreatmentMedicalProcedure';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TreatmentMedicalProcedureId',
        'TreatmentId',
        'MedicalProcedureId',
        'AnatomyBodyPartItemId',
        'ServiceId',
        'BasePrice',
        'SalePrice',
        'Ordering',
        'TotalDaysToComplete',
        'AddedAt',
        'ResponsibleId',
        'TreatmentMedicalProcedureStatusId',
        'EditedAt',
        'EditedBy',
        'Note',
        'Quantity',
        'TabID',
        'ProcessState',
        'OrderChangingId',
        'IsNew',
        'IsApprovingLock',
        'LatestUpdated'
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
