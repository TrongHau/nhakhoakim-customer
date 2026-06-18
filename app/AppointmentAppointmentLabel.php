<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AppointmentAppointmentLabel extends Model
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
    protected $table = 'AppointmentAppointmentLabel';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'AppointmentLabelId',
        'AppointmentId',
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

    //Disable create_at and update_at automactic add query
    public $timestamps = false;
}