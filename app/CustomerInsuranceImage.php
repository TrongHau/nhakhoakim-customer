<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerInsuranceImage extends Model
{
    //Define table name
    protected $table = 'CustomerInsuranceImage';

    //Define primary key
    protected $primaryKey= 'Id';

    //Define filter columns in tables
    protected $fillable = [
        'Id',
        'ImageOrder',
        'CustomerInsuranceId',
        'File',
        'CreatedDate',
        'CreatedBy',
        'EditedDate',
        'EditedBy'
    ];

    public $timestamps = false;

}
