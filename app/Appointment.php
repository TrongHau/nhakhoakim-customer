<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
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
    protected $table = 'Appointment';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'AppointmentId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AppointmentId',
        'Note',
        'FromCustomerChannel',
        'AppointmentStatusId',
        'AppointmentStatusNote',
        'CustomerId',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy',
        'StartAt',
        'EndAt',
        'AppointedTo',
        'RelatedTo',
        'AtBranchId',
        'AppIdKIM',
        'BranchIdBackup',
        'NoteBackup',
        'ConsultantId',
        'CampaignTypeCode',
        'CampaignCode',
        'SourceId',
        'MasterSourceId',
        'AppChannelId',
        'ChannelPlatformId',
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

    //Disable create_at and update_at automactic add query
    public $timestamps = false;

    //Get customer of Appointment while query
    public function customer()
    {
        return $this->belongsTo('App\Customer','CustomerId','CustomerId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class,'AtBranchId','BranchId');
    }

    public function rating()
    {
        return $this->hasOne(Rating::class,'AppointmentId','AppointmentId');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class, 'AppointedTo', 'DoctorId');
    }

    public function appointmentDoctorAssistant()
    {
        return $this->hasMany(AppointmentDoctorAssistant::class, 'AppointmentId', 'AppointmentId')
            ->where('State', 1);
    }
}
