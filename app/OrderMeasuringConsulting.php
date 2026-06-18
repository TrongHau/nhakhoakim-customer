<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderMeasuringConsulting extends Model
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
    protected $table = 'OrderMeasuringConsulting';


    protected $primaryKey = 'Id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'CustomerId',
        'ConsultingDate',
        'BranchId',
        'DoctorStaffId',
        'GeneralityLevel',
        'ProstheticLevel',
        'ImplantLevel',
        'OrthodonticLevel',
        'Status',
        'ConfirmedBy',
        'ConfirmedDate',
        'Note',
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(Staff::class, 'UpdatedBy', 'StaffId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }

    public function confirmedByStaff()
    {
        return $this->belongsTo(Staff::class, 'ConfirmedBy', 'StaffId');
    }

    public function doctor()
    {
        return $this->belongsTo(Staff::class, 'DoctorStaffId', 'StaffId');
    }

    public function doctorSpecializationCode()
    {
        return $this->belongsTo(Doctor::class, 'DoctorStaffId', 'StaffId');
    }

    public function doctors()
    {
        return $this->belongsToMany(Doctor::class, 'OrderMeasuringConsultingDoctor', 'OrderMeasuringConsultingId', 'DoctorStaffId', 'Id', 'StaffId');
    }

    public function offersByReport()
    {
        return $this->belongsToMany(TreatmentMedicalProcedureOffer::class, 'OrderMeasuringConsultingDetail', 'OrderMeasuringConsultingId', 'TreatmentMedicalProcedureOfferId')
            ->where('TreatmentMedicalProcedureOffer.AnatomyBodyPartItemId', '>', 0);
    }

    public function offersByTreatment()
    {
        return $this->hasManyThrough(TreatmentMedicalProcedureOffer::class, Treatment::class, 'PersonId', 'TreatmentId', 'CustomerId', 'TreatmentId')
            ->where('TreatmentMedicalProcedureOffer.AnatomyBodyPartItemId', '>', 0);
    }
}
