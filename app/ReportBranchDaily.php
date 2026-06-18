<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ReportBranchDaily extends Model
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
    protected $table = 'ReportBranchDaily';

    /**
     * @var string
     */
    protected $primaryKey = 'ReportBranchDailyId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ReportBranchDailyId',
        'DateReport',
        'BranchId',
        'TotalCash',
        'TotalRevenue',
        'TotalTraffic',
        'ConsultingAppointmentTotal',
        'Content',
        'ReportedByStaffId',
        'DoctorAssistantContent',
        'DoctorAssistantStaffId',
        'ReportedDoctorAssistantTime',
        'ReportedTime',
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

    public function reportedByStaff()
    {
        return $this->belongsTo(Staff::class, 'ReportedByStaffId', 'StaffId');
    }

    public function doctorAssistantStaff()
    {
        return $this->belongsTo(Staff::class, 'DoctorAssistantStaffId', 'StaffId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(Staff::class, 'UpdatedBy', 'StaffId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');    
    }

    public function comments()
    {
        return $this->hasMany(ReportBranchDailyComment::class, 'ReportBranchDailyId', 'ReportBranchDailyId');
    }
}
