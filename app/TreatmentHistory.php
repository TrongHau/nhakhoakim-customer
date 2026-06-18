<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentHistory extends Model
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
    protected $table = 'TreatmentHistory';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'TreatmentHistoryId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TreatmentHistoryId',
        'TreatmentId',
        'TreatmentCode',
        'PersonId',
        'MedicalDomainId',
        'AdmittedTime',
        'PageNumber',
        'Room',
        'DentalChairId',
        'PrimaryNurseName',
        'PostoperativeDiagnosis',
        'SurgicalMethod',
        'OperatedDoctor',
        'SurgicalDiagramImage',
        'SutureRemovalDate',
        'SurgicalNote',
        'SurgicalSequence',
        'PrescriptionCode',
        'PrescriptionDiagnosis',
        'PrescriptionMedicine',
        'PrescriptionDoctor',
        'PrescriptionDoctorNote',
        'PrescriptionGuardian',
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
        'PersonDiagnosisId',
        'AfterSurgeryDiagnosis',
        'MethodSurgeryDiagnosis',
        'SurgeryByDoctor',
        'SurgeryImages',
        'DateOfSutureRemoval',
        'SurgeryNote',
        'ProcessSurgeryDiagnosis',
        'ClosedAt',
        'ClosedBy',
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
}
