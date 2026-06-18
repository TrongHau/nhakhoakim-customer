<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerCareAppointment extends Model
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
    protected $table = 'CustomerCareAppointment';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'Id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'CustomerId',
        'CustomerCareTypeId',
        'OrgId',
        'Note',
        'DeclineNote',
        'CommunicationContent',
        'ConsultingDoctorId',
        'TotalAmount',
        'AppointmentDate',
        'AppointmentTime',
        'ActualActionDateTime',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'RefCustomerCareAppointmentId',
        'UpdatedDate',
        'UpdatedBy',
        'RefObjectType',
        'RefObjectId',
        'ResultId',
        'Result',
        'Explanation',
        'ExplanationBy',
        'ExplanationDate',
        'HandleBy',
        'HandleDate'
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

    public function consultingServiceGroups()
    {
        return $this->belongsToMany(ConsultingServiceGroup::class, 'CustomerCareAppointmentConsultingServiceGroup', 'CustomerCareAppointmentId', 'ConsultingServiceGroupId');   
    }

    public function declineReasons()
    {
        return $this->belongsToMany(DeclineReasonConfig::class, 'DeclineReasonCustomerCareAppointment', 'CustomerCareAppointmentId', 'DeclineReasonConfigId');
    }
}
