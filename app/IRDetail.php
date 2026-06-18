<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IRDetail extends Model
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
    protected $table = 'IRDetail';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'IRDetailId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'IRDetailId',
        'IRId',
        'ProductId',
        'UnitId',
        'PartnerSKU',
        'ExpectQty',
        'RefQty',
        'ActualQty',
        'ExceptionQty',
        'Price',
        'PutawayQty',
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
