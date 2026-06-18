<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantSupplier extends Model
{
    //Define table name
    protected $table = 'ImplantSupplier';

    //Define primary key
    protected  $primaryKey = 'ImplantSupplierId';

    //Define filter column
    protected $fillable = [
        'ImplantSupplierId',
        'Name',
        'Priority',
        'Status',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy'
    ];
    public $timestamps = false;

}