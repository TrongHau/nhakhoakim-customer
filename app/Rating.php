<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Rating extends Model
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
    protected $table = 'Rating';

    /**
     * @var string
     */
    protected $primaryKey = 'RatingId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'RatingId',
        'Value',
        'Note',
        'CreatedDate',
        'VisitedDate',
        'BranchId',
        'AppointmentId',
        'CustomerId',
        'SourceType',
        'Link',
        'Status',
        'CreatedBy',
        'IsPushNotification'
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

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'AppointmentId', 'AppointmentId');
    }

    public function ratingDetails()
    {
        return $this->hasMany(RatingDetail::class, 'RatingId', 'RatingId');
    }

    public function customerCareRatingRecommends()
    {
        return $this->hasMany(CustomerCareRatingRecommend::class, 'RatingId', 'RatingId');
    }

}
