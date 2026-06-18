<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerEmail extends Model
{
    //Define table name
    protected $table = 'CustomerEmail';

    //Define primary key
    protected $primaryKey= NULL;

    //Define filter columns in tables
    protected $fillable = [
        'CustomerId',
        'Email',
        'AddedAt',
        'IsPrimary'
    ];

    public $timestamps = false;

}
