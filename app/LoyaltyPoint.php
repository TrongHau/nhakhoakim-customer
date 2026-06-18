<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LoyaltyPoint extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_loyalty';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'LoyaltyPoint';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'LoyaltyPointId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'LoyaltyPointId',
        'CustomerId',
        'TotalPoints',
        'UsedPoints',
        'ExpiredPoints',
        'AvailablePoints',
        'Tier',
        'CreatedStaffId',
        'CreatedDate',
        'UpdatedStaffId',
        'UpdatedDate'
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
