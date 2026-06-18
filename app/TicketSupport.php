<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TicketSupport extends Model  
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_in';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'TicketSupport';

    /**
     * The database primary key used by the model.
     *
     * @var string
     */
    protected $primaryKey = 'TicketSupportId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'TicketSupportId',
        'ReceivingOrgId',
        'TicketCategoryId',
        'Content',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'ResultBy',
        'ResultDate',
        'ResultContent',
        'RatingContent',
        'RatingDate',
        'CancelDate',
        'ReceiveBy',
        'ReceiveDate',
        'ReceiveContent',
        'RejectedBy',
        'RejectedDate',
        'RejectedContent',
        'ActionBy',
        'SendingOrgId',
        'JsonContent',
        'IsPublic',
        'CustomerId',
        'RelatedStaffId',
        'SpecificationText',
        'TicketSupportSpecificationDetailId',
        'MethodSupport',
        'MethodSupportNote',
        'IsSOS',
        'TypeConsultation'
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

}
