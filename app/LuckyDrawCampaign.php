<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawCampaign extends Model
{
    //Define table name
    protected $table = 'LuckyDrawCampaign';

    //Define primary key
    protected  $primaryKey = 'LuckyDrawCampaignId';

    //Define filter column
    protected $fillable = [
        'LuckyDrawCampaignId',
        'Name',
        'Description',
        'StartDate',
        'EndDate',
        'State',
        'CreatedDate',
        'TotalSegments',
        'TypeId',
    ];
    public $timestamps = false;

}
