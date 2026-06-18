<?php
namespace App;

use Illuminate\Database\Eloquent\Model;

class CRMTargetKPI extends Model
{
    //Define table name
    protected $table = 'CRMTargetKPI';

    //Define primary key
    protected  $primaryKey = 'TargetKPIId';

    //Define filter column
    protected $fillable = [
        'TargetKPIId',
        'MonthNumber',
        'TargetType',
        'TargetValue',
        'CreatedDate',
        'BranchId',
        'GeneralityAmount',
        'ProstheticAmount',
        'ImplantAmount',
        'OrthodonticAmount'
    ];
    public $timestamps = false;
}
