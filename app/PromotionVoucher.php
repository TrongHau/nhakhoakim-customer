<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PromotionVoucher extends Model
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
    protected $table = 'promotion_vouchers';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'ID';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ID',
        'PromotionId',
        'SerialNumber',
        'Code',
        'DiscountType',
        'DiscountValue',
        'MaxDiscountValue',
        'MinToothQuantity',
        'MaxToothQuantity',
        'Group',
        'PhoneNumber',
        'ApplyDate',
        'CustomerCode'
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
}
