<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductOR extends Model
{

    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_sale';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'OR';


    protected $primaryKey = 'ORId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ORId',
        'BranchId',
        'ORCode',
        'ORType',
        'ORStatus',
        'SourceType',
        'CustomerId',
        'CustomerName',
        'CustomerPhoneNumber',
        'ExpectedDeliveryTime',
        'ActualDeliveryTime',
        'CancelledDate',
        'ConfirmedDate',
        'Recipient',
        'RecipientPhone',
        'ShippingFullAddress',
        'ShippingAddressNo',
        'ShippingProvinceId',
        'ShippingProvinceName',
        'ShippingDistrictId',
        'ShippingDistrictName',
        'ShippingWardId',
        'ShippingWardName',
        'TotalAmount',
        'DiscountAmount',
        'PaymentAmount',
        'CODAmount',
        'TotalRevenue',
        'TotalItem',
        'TotalSKU',
        'AffiliateAccountId',
        'AffiliateNote',
        'Note',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate',
        'ReceiptId',
        'PaidAmount',
        'PaymentStatus',
        'ShipmentId',
        'IsDelivery'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    public $timestamps = false;

    public function orderDetail()
    {
        return $this->hasMany(ORDetail::class,'ORId','ORId');
    }

    public function createdByStaff()
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }

    public function assignByDoctor()
    {
        return $this->belongsTo(Staff::class, 'ConsultingStaffId', 'StaffId');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class, 'BranchId', 'BranchId');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'ShipmentId', 'ShipmentId');
    }
}
