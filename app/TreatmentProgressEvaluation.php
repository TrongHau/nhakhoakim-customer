<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TreatmentProgressEvaluation extends Model
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
    protected $table = 'TreatmentProgressEvaluation';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'TreatmentProgressEvaluationId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TreatmentProgressEvaluationId',
        'TreatmentMedicalProcedureId',
        'EstimatedEvaluationDate',
        'ActualEvaluationDate',
        'CustomerId',
        'DoctorStaffId',
        'SelfEvaluation',
        'ProcessState',
        'CreatedBy',
        'CreatedDate'
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

    public function doctorStaff()
    {
        return $this->belongsTo(Staff::class, 'DoctorStaffId', 'StaffId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function service()
    {
        return $this->hasOneThrough(
            Service::class,                    // model đích
            TreatmentMedicalProcedure::class, // model trung gian
            'TreatmentMedicalProcedureId',   // FK của intermediate (TMP) → Evaluation
            'ServiceId',                     // FK của Service → TMP (via service_id)
            'TreatmentMedicalProcedureId', // FK trong Evaluation
            'ServiceId'                      // FK trong TMP
        );
    }
}
