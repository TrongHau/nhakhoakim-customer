<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConditionType extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    
    protected $table = 'ConditionType';

    //Define primary key
    protected  $primaryKey = 'ConditionTypeId';

    //Define filter column
    protected $fillable = [
        'ConditionTypeId',
        'Code',
        'Name',
        'Priority',
        'IsDeleted',
        'CreatedBy',
        'CreatedDate'
    ];
    public $timestamps = false;

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }
}
