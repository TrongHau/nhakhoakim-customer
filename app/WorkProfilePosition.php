<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class WorkProfilePosition extends Model  
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
    protected $table = 'WorkProfilePosition';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'WorkProfilePositionId',
        'Name',
        'Code',
        'Ordering',
        'State',
        'GroupCode',
        'IsAllowAccessOutside'
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

}
