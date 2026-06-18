<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductTracking extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ProductTracking';

    /**
     * The primary key of table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'ProductTrackingId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ProductTrackingId',
        'TrackingId',
        'ProductId',
        'UnitId',
        'CreatedBy',
        'CreatedDate',
        'Name',
        'OldValue',
        'NewValue',
        'Action'
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
