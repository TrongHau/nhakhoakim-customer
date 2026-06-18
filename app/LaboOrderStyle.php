<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaboOrderStyle extends Model
{
    //Define table name
    protected $table = 'LaboOrderStyle';

    //Define primary key
    protected  $primaryKey = 'LaboOrderStyleId';

    //Define filter column
    protected $fillable = [
        'LaboOrderStyleId',
        'LaboOrderStyleName',
        'State',
        'Priority',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy'
    ];
    public $timestamps = false;

}