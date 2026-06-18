<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Treatment extends Model
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
    protected $table = 'Treatment';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'TreatmentId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TreatmentId',
        'TreatmentCode',
        'TreatmentNumber',
        'PersonId',
        'ChiefComplaint',
        'PathologicalProcess',
        'MedicalHistory',
        'DiseaseProgression',
        'TestResults',
        'DischargeDiagnosis',
        'DischargeStatus',
        'TreatmentRegimen',
        'SurgicalSequence',
        'NextTreatment',
        'StatictisFiles',
        'PersonSendDocumentName',
        'PersonReceiverDocumentName',
        'PersonTreatmentName',
        'PersonResponsibilityName',
        'MedicalDomainId',
        'StartDate',
        'CreatedAt',
        'CreatedBy',
        'UpdatedAt',
        'UpdatedBy',
        'ProgressChangedAt',
        'ProgressChangedBy',
        'TotalDaysToComplete',
        'Note',
        'DiseaseProgressionNote',
        'NextTimeTreatmentNote',
        'ClosedBy',
        'ClosedAt',
        'PersonDiagnosisId',
        'CustomerCode',
        'Migrate',
        'IsNew',
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

    public function treatmentHistories()
    {
        return $this->hasMany(TreatmentHistory::class, 'TreatmentId', 'TreatmentId');   
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'PersonId', 'CustomerId');
    }
}
