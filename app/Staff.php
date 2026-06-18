<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';
    
    //Table name
    protected $table = 'Staff';

    //Primary key
    protected $primaryKey = 'StaffId';

    //Filter column
    protected $fillable = [
        'StaffId',
        'StaffCode',
        'State',
        'FullName',
        'UserId'
    ];
    public $timestamps = false;
}
