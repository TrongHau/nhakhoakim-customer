<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $Id
 * @property int    $InsuranceProviderCredentialId
 * @property int    $BranchId
 * @property bool   $IsActive
 * @property string $ProviderBranchCode  Mã chi nhánh phía provider (VD BaoMinh: 'P200001700')
 */
class InsuranceProviderBranch extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceProviderBranches';
    protected $primaryKey = 'Id';
    public $timestamps    = false;

    protected $fillable = [
        'InsuranceProviderCredentialId',
        'BranchId',
        'IsActive',
        'ProviderBranchCode',
    ];

    protected $casts = ['IsActive' => 'boolean'];

    public function credential()
    {
        return $this->belongsTo(InsuranceProviderCredential::class, 'InsuranceProviderCredentialId', 'Id');
    }
}
