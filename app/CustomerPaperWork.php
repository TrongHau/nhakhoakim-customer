<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerPaperWork extends Model
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
    protected $table = 'CustomerPaperWork';

    /**
     * The primary key used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'CustomerPaperWorkId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'CustomerPaperWorkId',
        'CustomerId',
        'CustomerPaperTypeId',
        'PaperName',
        'PaperContent',
        'PaperURL',
        'State',
        'Provider',
        'Landscape',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
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

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function customerPaperType()
    {
        return $this->belongsTo(CustomerPaperType::class, 'CustomerPaperTypeId', 'CustomerPaperTypeId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'CreatedBy',
            'StaffId'
        );
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'UpdatedBy',
            'StaffId'
        );
    }
}
