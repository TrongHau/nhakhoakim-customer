<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerInvisalign extends Model
{
    //Define table name
    protected $table = 'CustomerInvisalign';

    //Define primary key
    protected $primaryKey= 'CustomerInvisalignId';

    //Define filter columns in tables
    protected $fillable = [
        'CustomerInvisalignId',
        'CustomerId',
        'InvisalignId',
        'InvisalignNote',
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
}
