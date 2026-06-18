<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';

    //Define table name
    protected $table = 'Bank';

    //Define primary key
    protected  $primaryKey = 'BankId';

    //Define filter column
    protected $fillable = [
        'BankId',
        'NameVi',
        'NameEn',
        'State',
        'Address',
        'Priority',
        'Type'
    ];
    public $timestamps = false;

}
