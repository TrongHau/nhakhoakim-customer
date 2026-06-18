<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DashboardStaffEffectiveDaily extends Model
{
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'DashboardStaffEffectiveDaily';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = NULL;

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'SummaryDate',
        'BranchId',
        'ServiceCategoryId',
        'StaffId',
        'ConsultCount',
        'SuccessCount',
        'Revenue',
        'CreatedDate',
        'UpdatedDate'
    ];

    /**
     * Disable create_at and update_at automatic add query
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get branch relationship
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }
}
