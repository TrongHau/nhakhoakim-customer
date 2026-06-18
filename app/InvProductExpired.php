<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InvProductExpired extends Model  
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
    protected $table = 'InvProductExpired';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'InvProductExpiredId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'InvProductExpiredId', 
        'BranchId', 
        'ProductId', 
        'UnitId', 
        'ExpiredDate', 
        'ManufactureDate', 
        'AvailableQty', 
        'PendingInQty', 
        'PendingOutQty', 
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
}
