<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DoctorAssistant extends Model
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'DoctorAssistant';

    /**
     * The primary key for the model.
     *
     * @var string
     */

    protected $primaryKey = 'DoctorAssistantId';
    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'DoctorAssistantId',
        'StaffId',
        'WorkProfileId',
        'DoctorAssistantLevel',
        'State',
        'CreatedDate'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'StaffId', 'StaffId');
    }
}
