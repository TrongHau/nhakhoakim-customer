<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BranchDailyRevenue extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';
    
    //Table name
    protected $table = 'BranchDailyRevenue';

    //Primary key
    protected $primaryKey = 'BranchId';

    //Filter column
    protected $fillable = [
        'Date',
        'BranchId',
        'Visitor',
        'Traffic',
        'DoctorCount',
        'AssistantDoctorCount',
        'ConsultantCount',
        'CashCollection',
        'Revenue'
    ];
    public $timestamps = false;
}
