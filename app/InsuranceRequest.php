<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $Id
 * @property int    $BranchId
 * @property int    $CompanyId
 * @property string $ProviderCode
 * @property string $ProviderRequestId
 * @property int    $CustomerId
 * @property string $MemberCode
 * @property string $MemberName
 * @property string $PolicyNo
 * @property string $TreatmentCode
 * @property string $TreatmentType
 * @property string $UnifiedStatus
 * @property float  $EstimatedAmount
 * @property float  $ClaimAmount
 * @property array  $Payload
 * @property int    $CreatedBy
 * @property string $CreatedDate
 * @property int    $EditedBy
 * @property string $EditedDate
 * @property array  $Treatments
 */
class InsuranceRequest extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceRequests';
    protected $primaryKey = 'InsuranceRequestsId';

    const CREATED_AT = 'CreatedDate';
    const UPDATED_AT = 'EditedDate';

    protected $fillable = [
        'BranchId',
        'CompanyId',
        'ProviderCode',
        'ProviderRequestId',
        'CustomerId',
        'MemberCode',
        'MemberName',
        'PolicyNo',
        'TreatmentCode',
        'TreatmentType',
        'UnifiedStatus',
        'EstimatedAmount',
        'ClaimAmount',
        'Payload',
        'CustomerCode',
        'FromDate',
        'ToDate',
        'CreatedBy',
        'EditedBy',
        'Treatments',
        'Note',
    ];

    protected $casts = [
        'Payload'         => 'array',
        'Treatments'      => 'array',
        'EstimatedAmount' => 'float',
        'ClaimAmount'     => 'float',
        'FromDate'        => 'datetime',
        'ToDate'          => 'datetime',
    ];

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'CompanyId', 'Id');
    }

    public function partnerCompany()
    {
        return $this->belongsTo(PartnerCompany::class, 'CompanyId', 'PartnerCompanyId');
    }

    public function histories()
    {
        return $this->hasMany(InsuranceRequestHistory::class, 'InsuranceRequestId', 'InsuranceRequestsId')
                    ->orderByDesc('ChangedAt');
    }


}
