<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantMapping extends Model
{
    //Define table name
    protected $table = 'ImplantMapping';

    //Define primary key
    protected  $primaryKey = 'ImplantMappingId';

    //Define filter column
    protected $fillable = [
        'ImplantMappingId',
        'ImplantSuppliesId',
        'ImplantSupplierId'
    ];
    public $timestamps = false;

}