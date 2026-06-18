<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaboOrder extends Model
{
    //Define table name
    protected $table = 'LaboOrder';

    //Define primary key
    protected  $primaryKey = 'Id';

    //Define filter column
    protected $fillable = [
        'Id',
        'OrderCode',
        'SendDate',
        'ExpectReceiveDate',
        'BranchId',
        'DoctorId',
        'CustomerId',
        'LaboOrderStatusFinal',
        'OrderStatusCode',
        'Note',
        'ProductCode',
        'KhayLayDauCode',
        'VatLieuCode',
        'DauCanCode',
        'HinhTheNhipCauCode',
        'MauRang13CoCode',
        'MauRang13GiuaCode',
        'MauRang13CanCode',
        'MauRangCode',
        'MauCuiRangCode',
        'StainMauCode',
        'TiepXucCode',
        'KhopCanCode',
        'VungLemCode',
        'GiaKhopCode',
        'HamKhungCode',
        'MauHamCode',
        'KyThuatDapSuCode',
        'FormRangCode',
        'PriorityCode',
        'TypeCode',
        'RefferOrderCode',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy',
        'FactoryCode',
        'IsRush',
        'ReceiveBranchId',
        'LaboConfirmDate',
        'LaboConfirmStaff',
        'LaboExpectDeliveryDate',
        'DeliveryDate',
        'ShippingAddress',
        'DeliveryUnit',
        'CancelReason',
        'LaboAnalyzeDate',
        'LaboAnalyzeStaff',
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

    public function updatedByStaff()
    {
        return $this->belongsTo(
            Staff::class,
            'EditedBy',
            'StaffId'
        );
    }

}