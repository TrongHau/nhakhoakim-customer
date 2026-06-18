<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerInvisalignTracking extends Model
{
    //Define table name
    protected $table = 'CustomerInvisalignTracking';

    //Define primary key
    protected $primaryKey= 'CustomerInvisalignTrackingId';

    //Define filter columns in tables
    protected $fillable = [
        'CustomerInvisalignTrackingId',
        'CustomerInvisalignId',
        'OldCustomerId',
        'OldInvisalignId',
        'OldInvisalignNote',
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
