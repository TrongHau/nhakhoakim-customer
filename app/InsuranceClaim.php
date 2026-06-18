<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $Id
 * @property int    $InsuranceRequestId
 * @property int    $BranchId
 * @property string $ProviderCode
 * @property string $ProviderClaimId
 * @property string $UnifiedStatus
 * @property string $SendType           ONLINE | ONLINE
 * @property string $SendDate
 * @property string $SentAt
 * @property string $ReceivedAt
 * @property float  $PaymentAmount
 * @property string $PaymentDate
 * @property string $PaymentNo
 * @property array  $Payload
 */
class InsuranceClaim extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceClaims';
    protected $primaryKey = 'Id';

    const CREATED_AT = 'CreatedAt';
    const UPDATED_AT = 'UpdatedAt';

    protected $fillable = [
        'InsuranceRequestId',
        'BranchId',
        'ProviderCode',
        'ProviderClaimId',
        'UnifiedStatus',
        'SendType',
        'SendDate',
        'SentAt',
        'ReceivedAt',
        'PaymentAmount',
        'PaymentDate',
        'PaymentNo',
        'Payload',
    ];

    protected $casts = [
        'Payload'       => 'array',
        'PaymentAmount' => 'float',
    ];

    public function request()
    {
        return $this->belongsTo(InsuranceRequest::class, 'InsuranceRequestId', 'Id');
    }

    public function histories()
    {
        return $this->hasMany(InsuranceClaimHistory::class, 'InsuranceClaimId', 'Id')
                    ->orderByDesc('ChangedAt');
    }
}
