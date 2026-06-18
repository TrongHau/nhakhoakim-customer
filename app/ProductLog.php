<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductLog extends Model  
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
    protected $table = 'ProductLog';

    /**
     * The primary key used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'ProductLogId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ProductLogId', 
        'ProductId', 
        'Title', 
        'SKU', 
        'Barcode', 
        'ProductName', 
        'Description', 
        'IsTrackingSerial', 
        'IsExpiryDate', 
        'RefPrice', 
        'AvatarURL', 
        'NameUnsign', 
        'IsActive', 
        'SalePrice', 
        'Price', 
        'CreatedBy', 
        'CreatedDate', 
        'UpdatedBy', 
        'UpdatedDate', 
        'BrandId', 
        'Type'
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

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }
}
