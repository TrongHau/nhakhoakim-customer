<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class InsuranceRequestHistory extends Model
{
    protected $connection = 'mysql';
    protected $table      = 'InsuranceRequestHistories';
    protected $primaryKey = 'Id';
    public $timestamps    = false;

    protected $fillable = [
        'InsuranceRequestId',
        'UnifiedStatus',
        'ChangedBy',
        'Note',
        'ChangedAt',
    ];

    public function request()
    {
        return $this->belongsTo(InsuranceRequest::class, 'InsuranceRequestId', 'InsuranceRequestsId');
    }
}
