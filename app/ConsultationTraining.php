<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConsultationTraining extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_ai';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'ConsultationTraining';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = ['Id', 'CustomerId', 'CustomerName', 'CustomerCode', 'BranchCode', 'Age', 'Gender', 'AppointmentDate', 'DiscussionPoint', 'XraySummary', 'DiagnosisSummary', 'Service', 'State', 'Input', 'Output', 'AgentName', 'SessionKey', 'RunId', 'CreatedDate', 'CreatedBy', 'UpdatedDate', 'UpdateBy'];

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
    protected $dates = ['AppointmentDate', 'CreatedDate', 'UpdatedDate'];

}
