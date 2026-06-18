<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ORDetail extends Model
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
    protected $table = 'ORDetail';


    protected $primaryKey = 'ORDetailId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ORDetailId',
        'ORId',
        'ProductId',
        'SKU',
        'UnitId',
        'ConditionTypeId',
        'OrderQty',
        'RefPrice',
        'AffiliatePrice',
        'SalePrice',
        'DiscountType',
        'DiscountValue',
        'DiscountAmount',
        'TotalAmount',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
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

    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductId', 'ProductId');
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'UnitId', 'UnitId');
    }
}
