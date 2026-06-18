<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvProduct extends Model  
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
    protected $table = 'InvProduct';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'InvProduct';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'InvProduct',
        'FreezeQty',
        'BranchId',
        'ProductId',
        'SKU',
        'UnitId',
        'ConditionTypeId',
        'AvailableQty',
        'PendingInQty',
        'PendingOutQty',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate',
        'AVGPrice'
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

    public function productCategory()
    {
        return $this->belongsTo(ProductCategory::class, 'ProductId', 'ProductId');
    }
}
