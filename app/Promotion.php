<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
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
    protected $table = 'promotions';

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
        'Code',
        'CreateBy',
        'UpdateBy',
        'Name',
        'Type',
        'Startdate',
        'Enddate',
        'DiscountType',
        'DiscountValue',
        'MaxDiscountValue',
        'UseType',
        'CreateAt',
        'UpdateAt',
        'IsDelete',
        'System',
        'MinValue',
        'AllowAppend',
        'IsClinic',
        'IsRemoveService',
        'SpecialCode',
        'MaxUsingTimes',
        'ForStaff',
        'AutoApply',
        'FromDepositDate',
        'ToDepositDate',
        'DepositAmount',
        'IsAllowedTransfer',
        'AmountExpirationDate',
        'IsCheckOwner',
        'IsGift',
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
