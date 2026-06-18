<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PersonDiagnosis extends Model  
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
    protected $table = 'PersonDiagnosis';

    /**
     * The database table primary key used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'PersonDiagnosisId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'PersonDiagnosisId',
        'PersonId',
        'MedicalDomainId',
        'IsAdult',
        'GeneralCheckUp',
        'SpecialtyDiseases',
        'SummaryDiseases',
        'Diagnosis',
        'HandledByDownline',
        'HandledByReferral',
        'HeartBeat',
        'Temperature',
        'BloodPressure',
        'BreathingRate',
        'Height',
        'Weight',
        'CreatedAt',
        'CreatedBy',
        'Note',
        'UpdatedAt',
        'UpdatedBy',
        'CustomerCode'
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
