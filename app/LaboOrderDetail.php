<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LaboOrderDetail extends Model
{
    //Define table name
    protected $table = 'LaboOrderDetail';

    //Define primary key
    protected  $primaryKey = 'LaboOrderDetailId';

    //Define filter column
    protected $fillable = [
        'LaboOrderDetailId',
        'LaboOrderId',
        'Line',
        'AnatomyBodyPartItemId',
        'Qty',
        'CreatedAt',
        'CreatedBy',
        'EditedAt',
        'EditedBy',
        'OrderDetailId',
        'LaboOrderStatusFinal',
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
        'RefferOrderCode',
        'ProcessBranchId',
        'VienTrongTrenCode',
        'VienTrongTren',
        'VienTrongDuoiCode',
        'VienTrongDuoi',
        'VienNgoaiTrenCode',
        'VienNgoaiTren',
        'VienNgoaiDuoiCode',
        'VienNgoaiDuoi',
        'DapLem',
        'KhayHamTren',
        'KhayHamDuoi',
        'CungLuoiHamTrenCode',
        'CungLuoiHamDuoiCode',
        'SoLuongHamTren',
        'SoLuongHamDuoi',
        'LaboServiceStandardId'
    ];
    public $timestamps = false;
}