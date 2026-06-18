<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InfectionControlScoring extends Model
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
    protected $table = 'InfectionControlScoring';

    /**
     * Primary key
     */
    protected $primaryKey = 'Id';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'Id',
        'CreatedDate',
        'CreatedBy',
        'Type',
        'BranchId',
        'TotalScore',
        'TotalTargetAchieved',
        'CheckedStatus'
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

    public function details()
    {
        return $this->hasMany(InfectionControlScoringDetail::class, 'InfectionControlScoringId', 'Id');
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
