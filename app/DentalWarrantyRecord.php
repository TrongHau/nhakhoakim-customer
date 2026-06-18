<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DentalWarrantyRecord extends Model
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
    protected $table = 'DentalWarrantyRecords';

    protected $primaryKey = 'DentalWarrantyRecordsId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'DentalWarrantyRecordsId',
        'CustomerId',
        'OrderDetailId',
        'SpecializationCode',
        'WarrantyInfo',
        'StartDate',
        'EndDate',
        'CreatedBy',
        'CreatedDate',
        'IsDeleted',
        'EditedDate'
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

    public function orderDetail()
    {
        return $this->belongsTo(OrderDetail::class, 'OrderDetailId', 'OrderDetailId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }
}
