<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SystemAccessTracking extends Model
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
    protected $table = 'SystemAccessTracking';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'StaffId',
        'WorkProfilePositionId',
        'WanIP',
        'InfoAccess',
        'FeatureAccess',
        'RefCustomerId',
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

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffId', 'StaffId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'RefCustomerId', 'CustomerId');
    }

    public function workProfilePosition()
    {
        return $this->belongsTo(WorkProfilePosition::class, 'WorkProfilePositionId', 'WorkProfilePositionId');
    }

    public function workProfilePositionGroup()
    {
        return $this->belongsTo(WorkProfilePositionGroup::class, 'WorkProfilePositionGroupId', 'Id');
    }
}
