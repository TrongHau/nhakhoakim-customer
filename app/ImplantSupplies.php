<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantSupplies extends Model
{
    //Define table name
    protected $table = 'ImplantSupplies';

    //Define primary key
    protected  $primaryKey = 'ImplantSuppliesId';

    //Define filter column
    protected $fillable = [
        'ImplantSuppliesId',
        'Name',
        'Status',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
    ];
    public $timestamps = false;

}