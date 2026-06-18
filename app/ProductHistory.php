<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductHistory extends Model  
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
    protected $table = 'ProductHistory';

    /**
     * The primary key used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'ProductHistoryId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ProductHistoryId', 
        'BranchId', 
        'ProductId',
        'BoardId', 
        'BoardName', 
        'TotalBill', 
        'IsSuccess', 
        'Link', 
        'CreatedBy', 
        'CreatedDate', 
        'UpdatedBy', 
        'UpdatedDate',
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
