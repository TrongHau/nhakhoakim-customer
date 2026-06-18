<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RatingSummaryByMonth extends Model
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
    protected $table = 'RatingSummaryByMonth';

    /**
     * Primary key
     * @var string
     */
    protected $primaryKey = 'RatingSummaryByMonthId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'RatingSummaryByMonthId',
        'RatingDate',
        'BranchId',
        'RatingValue',
        'RatingNumber',
        'Url',
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
}
