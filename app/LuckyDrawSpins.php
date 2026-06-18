<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LuckyDrawSpins extends Model
{
    //Define table name
    protected $table = 'LuckyDrawSpins';

    //Define primary key
    protected  $primaryKey = 'Id';

    //Define filter column
    protected $fillable = [
        'Id',
        'CustomerId',
        'FullName',
        'PhoneNumber',
        'LuckyDrawGiftTypeId',
        'LuckyDrawCampaignId',
        'BranchId',
        'CreatedBy',
        'CreatedDate'
    ];
    public $timestamps = false;

    public function customerPrize()
    {
        return $this->belongsTo('App\LuckyDrawGiftType', 'LuckyDrawGiftTypeId', 'LuckyDrawGiftTypeId');
    }

    public function createByStaff()
    {
        return $this->belongsTo('App\Staff', 'CreatedBy', 'StaffId');
    }

    public function createByBranch()
    {
        return $this->belongsTo('App\Branch', 'BranchId', 'BranchId');
    }

    public function luckyDrawGiftType()
    {
        return $this->belongsTo(LuckyDrawGiftType::class, 'LuckyDrawGiftTypeId', 'LuckyDrawGiftTypeId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function staff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }
}
