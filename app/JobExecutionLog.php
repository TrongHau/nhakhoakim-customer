<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class JobExecutionLog extends Model
{
    //Define table name
    protected $table = 'JobExecutionLog';

    //Define primary key
    protected  $primaryKey = 'ExecutionId';

    //Define filter column
    protected $fillable = [
        'ExecutionId',
        'JobCode',
        'JobName',
        'JobType',
        'RunDate',
        'StartTime',
        'EndTime',
        'Status',
        'TxnStartId',
        'TxnEndId',
        'RecordsProcessed',
        'RecordsFailed',
        'RetryCount',
        'TriggeredBy',
        'ServerId',
        'ErrorMessage',
        'ExtraData',
        'CreatedDate'
    ];
    public $timestamps = false;

}