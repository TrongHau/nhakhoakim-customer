<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Doctor extends Model  
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
    protected $table = 'Doctor';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['DoctorId', 'StaffId', 'WorkProfileId', 'DoctorLevelId', 'DoctorLevelReachedDate', 'State', 'SpecializationCode', 'OrthodonticLevel', 'OrthodonticAdvisorStaffId', 'ImplantLevel', 'ImplantAdvisorStaffId', 'ProstheticLevel', 'ProstheticAdvisorStaffId', 'GeneralityLevel', 'GeneralityAdvisorStaffId', 'CreatedDate', 'UpdatedDate'];

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

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffId', 'StaffId');
    }
}
