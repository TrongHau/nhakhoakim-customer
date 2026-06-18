<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawGiftType extends Model
{
    //Define table name
    protected $table = 'LuckyDrawGiftType';

    //Define primary key
    protected  $primaryKey = 'LuckyDrawGiftTypeId';

    //Define filter column
    protected $fillable = [
        'LuckyDrawGiftTypeId',
        'Name',
        'IsSpecial',
        'Priority',
        'Description',
        'CreatedDate'
    ];
    public $timestamps = false;

}
