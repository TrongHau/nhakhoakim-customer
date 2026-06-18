<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkProfile extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'WorkProfile';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['WorkProfileId', 'StaffId', 'FromDate', 'ToDate', 'IsCurrentProfile', 'WorkPositionId', 'CompanyId', 'DepartmentId', 'TeamId', 'WorkContractId', 'StaffLevelId', 'BranchId', 'DegreeId', 'UpdatedAt', 'UpdatedBy', 'CreatedBy', 'CreatedAt', 'BaseSalary', 'ExtraMonthlyIncome', 'BankId', 'BankBranchId', 'BankAccountName', 'BankAccountId', 'IsFullTime', 'WorkPositionNote', 'WorkPositionNote2', 'Migrate', 'WorkProfilePositionId', 'IncomeTypeId', 'IncomeTypeLevelId', 'IsAvailable', 'CurrentWorkContractAnnexId', 'CurrentWorkPlaceChangingId', 'CurrentWorkLocationId', 'CurrentCompanyId', 'CurrentBranchId', 'CurrentDepartmentId', 'CurrentTeamId', 'CurrentWorkProfilePositionId', 'CurrentStaffLevelId', 'CurrentLockLevel', 'NumOfHourWorkingPerWeek'];

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
    protected $dates = ['FromDate', 'ToDate'];

}
