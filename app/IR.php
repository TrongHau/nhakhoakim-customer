<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class IR extends Model
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
    protected $table = 'IR';

    /**
     * The primary key table used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'IRId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'IRId',
        'WarehouseId',
        'ClientGroupPartnerId',
        'IRCode',
        'IRStatus',
        'IRType',
        'EstimateArrivalDate',
        'ArrivalDeadline',
        'ActualArrivalDate',
        'FinishedDate',
        'ContactName',
        'ContactPhone',
        'FromSource',
        'ConditionTypeId',
        'SupplierName',
        'FromWarehouseId',
        'Note',
        'LastCheckInDate',
        'RefCode',
        'TotalSKU',
        'TotalExpectQty',
        'TotalActualQty',
        'TotalValue',
        'BranchId',
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

    public function productDetailIR()
    {
        return $this->hasMany(IRDetail::class, 'IRId', 'IRId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }
}
