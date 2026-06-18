<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TimeKeeper extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';
    
    //Table name
    protected $table = 'TimeKeeper';

    //Primary key
    protected $primaryKey = 'TimeKeeperId';

    //Filter column
    protected $fillable = [
        'TimeKeeperId',
        'StaffId',
        'WorkProfileId',
        'Day',
        'CheckInAt',
        'CheckOutAt',
        'FromIp',
        'RegistedMobileDeviceId',
        'MobileAppId',
        'CheckInLocationId',
        'CheckOutLocationId',
        'WorkShiftId',
        'WorkShiftStartTime',
        'WorkShiftEndTime',
        'EditedAt',
        'EditedBy',
        'WeekDayTotalBreakTimeInMunite',
        'WorkShiftTotalBreakTimeInMinute',
        'WorkScheduleId',
        'Status',
        'ExpectedCheckInAt',
        'ExpectedCheckOutAt',
        'ReasonNote',
        'UpdatedStatusDate',
        'UpdatedStatusById',
        'IsDeleted',
        'ConfirmCheckIn',
        'ConfirmCheckOut',
        'IsEdited',
        'ApprovedAt',
        'ApprovedBy',
        'TotalHour',
        'TotalPaidHour',
        'ShiftStart',
        'ShiftEnd',
        'TotalBreakTimeInMinute',
        'StaffConfirmNote',
        'Order',
        'LastCheckOut',
        'TimeKeepingRequestId'
    ];
    public $timestamps = false;
}
