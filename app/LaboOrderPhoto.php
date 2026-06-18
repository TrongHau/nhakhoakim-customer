<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaboOrderPhoto extends Model
{
    //Define table name
    protected $table = 'LaboOrderPhoto';

    //Define primary key
    protected  $primaryKey = 'LaboOrderPhotoId';

    //Define filter column
    protected $fillable = [
        'LaboOrderPhotoId',
        'LaboOrderId',
        'PhotoTypeCode',
        'Url',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy'
    ];
    public $timestamps = false;
}