<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderDetailTracking extends Model
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
    protected $table = 'OrderDetailTracking';


    protected $primaryKey = 'TrackingId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TrackingId',
        'OrderDetailIds',
        'OrderChangingId',
        'ServiceId',
        'StatusId',
        'AnatomyBodyPartItemIds',
        'CustomerId',
        'StaffId',
        'PromotionId',
        'ActionId',
        'Action',
        'ActionTimestamp',
        'CreatedBy',
        'CreatedDate',
        'Note',
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

    public function actionByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'StaffId',
            'StaffId'
        );
    }

    public function createdOrderDetailByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'CreatedBy',
            'StaffId'
        );
    }

    public function infoPromotion()
    {
        return $this->belongsTo(
            Promotion::class,
            'PromotionId',
            'ID'
        );
    }

    public function infoOrder()
    {
        return $this->belongsTo(
            OrderDetail::class,
            'OrderDetailId',
            'OrderDetailId'
        );
    }
}
