<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    //Define table name
    protected $table = 'Customer';

    //Define primary key
    protected  $primaryKey = 'CustomerId';

    //Define filter column
    protected $fillable = [
        'CustomerId',
        'CustomerCode',
        'CustomerIdNumber',
        'CustomerStatusId',
        'FullName',
        'Gender',
        'Photo',
        'PhotoResize',
        'CustomerLevelId',
        'CustomerPattern',
        'CreatedAt',
        'UpdatedAt',
        'UrgentContactName',
        'UrgentContactPhone',
        'UrgentContactAddress',
        'UrgentRelationshipId',
        'UrgentIsAdult',
        'ConsultingStaffId'
    ];
    public $timestamps = false;

    /**
     * Get relationships customer in table Customer - CustomerRelationship - Customer
     */

    public function relationships()
    {
        return $this->belongsToMany('App\Customer','CustomerRelationship','CustomerId','RelatedTo');
    }

    /**
     * Get list phone number in table Customer - CustomerPhoneNumber 
     */
    public function phones()
    {
        return $this->hasMany('App\CustomerPhoneNumber','CustomerId','CustomerId');
    }

    /**
     * Get list Deposit in table Customer - Deposit
     */
    public function deposits()
    {
        return $this->hasMany('App\Deposit','CustomerId','CustomerId');
    }

    /**
     * Get list Expenditure with relationship 1-n: Customer - Expenditure
     */
    public function expenditures()
    {
        return $this->hasMany('App\Expenditure','CustomerId','RefId');
    }
    
    /**
     * Get  Appointments of Customer while query sql
     */
    public function appointments()
    {
        return $this->hasMany('App\Appointment','CustomerId','CustomerId');
    }

    public function urgentRelationship()
    {
        return $this->belongsTo(AIHRelationship::class,'UrgentRelationshipId','Id');
    }

}
