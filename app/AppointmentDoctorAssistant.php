<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppointmentDoctorAssistant extends Model
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
    protected $table = 'AppointmentDoctorAssistant';

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
        'AppointmentId',
        'DoctorAssistantId',
        'State',
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

    public function doctorAssistant()
    {
        return $this->belongsTo(DoctorAssistant::class, 'DoctorAssistantId', 'DoctorAssistantId');
    }
}
