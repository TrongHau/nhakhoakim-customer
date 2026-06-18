<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaboOrderRating extends Model
{
    //Define table name
    protected $table = 'LaboOrderRating';

    //Define primary key
    protected  $primaryKey = 'RatingId';

    //Define filter column
    protected $fillable = [
        'RatingId',
        'Value',
        'OrderNote',
        'BranchCode',
        'CustomerId',
        'DoctorNote',
        'ImageURL',
        'Status',
        'CreatedDate',
        'CreatedBy',
        'LaboOrderId',
        'ReadResponsibleStaffId',
        'ReadResponsibleDate'
    ];
    public $timestamps = false;

}