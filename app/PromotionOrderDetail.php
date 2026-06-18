<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromotionOrderDetail extends Model
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
    protected $table = 'PromotionOrderDetail';

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
        'OrderDetailId',
        'ServiceId',
        'PromotionId',
        'VoucherId',
        'DiscountPercent',
        'DiscountAmount',
        'AddedAt',
        'AddedBy',
        'State',
        'CustomerId',
        'PromotionType',
        'OrderChangingId',
        'BranchId',
        'WanIp',
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

    public $timestamps = false;
}
