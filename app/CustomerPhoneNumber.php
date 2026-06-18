<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerPhoneNumber extends Model
{
    //Define table
    protected $table = 'CustomerPhoneNumber';

    //Define fillter columns in table
    protected $fillable = [
        'PhoneNumber',
        'CustomerId'
    ];

    public $timestamps = false;

    //Define relationship 1:N Customer - CustomerPhone
    public function customer()
    {
        return $this->belongsTo('App\Customer','CustomerId','CustomerId');
    } 
}
