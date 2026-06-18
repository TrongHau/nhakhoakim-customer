<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InsuranceClaimHistory extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceClaimHistories';
    protected $primaryKey = 'Id';
    public $timestamps    = false;

    protected $fillable = [
        'InsuranceClaimId',
        'UnifiedStatus',
        'ChangedBy',
        'Note',
        'ChangedAt',
    ];

    public function claim()
    {
        return $this->belongsTo(InsuranceClaim::class, 'InsuranceClaimId', 'Id');
    }
}
