<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerLevel extends Model
{
    //Define table name
    protected $table = 'CustomerLevel';

    //Define primary key
    protected $primaryKey= 'CustomerLevelId';

    //Define filter columns in tables
    protected $fillable = [
        'CustomerLevelId',
        'Code',
        'Name',
        'Amount'
    ];
}
