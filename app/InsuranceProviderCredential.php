<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int    $Id
 * @property int    $CompanyId
 * @property string $ProviderCode
 * @property string $BaseUrl
 * @property string $Username
 * @property string $Password          AES-256-CBC encrypted
 * @property string $AccessToken
 * @property string $RefreshToken
 * @property string $TokenExpiresAt
 * @property string $RefreshTokenExpiresAt
 * @property string $WebhookSecret
 * @property string $PgpPublicKey      Public key Bảo Minh (để encrypt payload gửi đi)
 * @property string $PgpPrivateKey     Private key NKK (để decrypt payload nhận về)
 * @property string $PgpPassphrase     Passphrase cho NKK private key
 */
class InsuranceProviderCredential extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceProviderCredentials';
    protected $primaryKey = 'Id';
    public $timestamps    = false;

    protected $fillable = [
        'CompanyId',
        'ProviderCode',
        'BaseUrl',
        'Username',
        'Password',
        'AccessToken',
        'RefreshToken',
        'TokenExpiresAt',
        'RefreshTokenExpiresAt',
        'WebhookSecret',
        'PgpPublicKey',
        'PgpPrivateKey',
        'PgpPassphrase',
        'IsActive',
    ];

    protected $hidden = ['Password', 'PgpPrivateKey', 'PgpPassphrase'];

    public function insuranceCompany()
    {
        return $this->belongsTo(InsuranceCompany::class, 'CompanyId', 'Id');
    }

    public function branches()
    {
        return $this->hasMany(InsuranceProviderBranch::class, 'InsuranceProviderCredentialId', 'Id');
    }
}
