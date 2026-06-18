<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductUnit extends Model
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
    protected $table = 'ProductUnit';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'ProductId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ProductId',
        'UnitId',
        'QtyPerCase',
        'Volume',
        'Length',
        'Width',
        'Height',
        'ActualWeight',
        'CostPrice',
        'SalePrice',
        'IsBaseUnit',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate',
        'LatestTrackingId',
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

    public function unit()
    {
        return $this->belongsTo(Unit::class, 'UnitId', 'UnitId');
    }
}
