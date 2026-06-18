<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantTechnicalSpecification extends Model
{
    //Define table name
    protected $table = 'ImplantTechnicalSpecification';

    //Define primary key
    protected  $primaryKey = 'ImplantTechnicalSpecificationId';

    //Define filter column
    protected $fillable = [
        'ImplantTechnicalSpecificationId',
        'ImplantTechnicalSpecificationCode',
        'Name',
        'ImplantSuppliesId',
        'ImplantSupplierId',
        'Length',
        'Width',
        'Height',
        'Radius',
        'Unit',
        'CreatedBy',
        'CreatedDate'
    ];
    public $timestamps = false;

}