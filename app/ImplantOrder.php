<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ImplantOrder extends Model
{
    //Define table name
    protected $table = 'ImplantOrder';

    //Define primary key
    protected  $primaryKey = 'ImplantOrderId';

    //Define filter column
    protected $fillable = [
        'ImplantOrderId',
        'CustomerId',
        'ImplantOrderCode',
        'UsingDate',
        'OrderType',
        'Status',
        'ExpectedDeliveryDate',
        'ActualDeliveryDate',
        'StatusNote',
        'BranchId',
        'Note',
        'CreatedDate',
        'CreatedBy',
        'UpdatedDate',
        'UpdatedBy',
        'ResponsibleStaffId',
        'LatestComment',
        'LatestCommentBy',
        'LatestCommentDate'
    ];
    public $timestamps = false;

    public function createdByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'CreatedBy',
            'StaffId'
        );
    }

    public function commentByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'LatestCommentBy',
            'StaffId'
        );
    }

    public function updatedByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'UpdatedBy',
            'StaffId'
        );
    }

    public function customerSearch()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function orderByCustomer()
    {
        return $this->belongsTo(Customer::class, 'CustomerId', 'CustomerId');
    }

    public function byBranch()
    {
        return $this->belongsTo(
            Branch::class,
            'BranchId',
            'BranchId'
        );
    }

}