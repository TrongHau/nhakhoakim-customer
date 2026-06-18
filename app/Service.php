<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
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
    protected $table = 'Service';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ServiceId',
        'LanguageId',
        'ServiceCode',
        'Name',
        'Description',
        'GeneralityLevel',
        'ProstheticLevel',
        'ImplantLevel',
        'OrthodonticLevel',
        'IndependentTreatmentUnitQuantity',
        'UnitQuantityLevel',
        'ServiceDomainId',
        'BasePrice',
        'SalePrice',
        'GeneralPhaseCount',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy',
        'ServiceGroupId',
        'TaxAmount',
        'State',
        'NewServiceCode',
        'ServiceType',
        'IsApproved',
        'ORCCategoryCode',
        'ORCServiceCode',
        'AppliedDate',
        'CreatedDate',
        'WarrantyType',
        'InputWarrantyStep',
        'WarrantyDuration',
        'StartWarrantyStep',
        'LatestUpdated',
        'ServiceDomainType',
        'ServiceDomainLevel',
        'IsReserved',
        'DoctorRemuneration',
        'DoctorRemunerationType',
        'CounselorsRevenue100Step',
        'ProgressEvaluationInterval'
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
}
