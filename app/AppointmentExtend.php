<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppointmentExtend extends Model
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
    protected $table = 'AppointmentExtend';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AppointmentExtendId',
        'AppointmentId',
        'Source',
        'CustomerId',
        'FullName',
        'Phone',
        'StartDate',
        'StartHour',
        'AtBranchId',
        'IsAdvise',
        'Note',
        'IsDuplicatedPhone',
        'ExtraData',
        'ClientIP',
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
    protected $casts = [
        'ExtraData' => 'array'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;

    public function createdByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'CreatedBy',
            'StaffId'
        );
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'UpdatedBy',
            'StaffId'
        );
    }
}
