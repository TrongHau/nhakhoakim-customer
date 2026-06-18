<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $ProductId
 * @property string      $SKU
 * @property string      $Name
 * @property string|null $NameUnsign
 * @property string|null $Description
 * @property int|null    $Priority
 * @property int         $ProductCategoryId
 * @property int|null    $SupplierId
 * @property int         $UnitId
 * @property int|null    $IsTrackingSerial
 * @property float|null  $Price
 * @property int|null    $PackageType
 * @property int|null    $IsExpiryDate
 * @property string|null $Specification
 * @property string|null $Barcode
 * @property string|null $Note
 * @property int         $Status
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class Product extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'Product';

    protected $primaryKey = 'ProductId';

    public $timestamps = false;

    protected $fillable = [
        'ProductId',
        'ProductBaseId',
        'ProductCategoryId',
        'ProductBrandId',
        'SKU',
        'SkuCode',
        'Barcode',
        'Name',
        'BaseName',
        'NameUnsign',
        'Description',
        'AttributeKey',
        'UnitId',
        'BaseUnitId',
        'PurchaseUnitId',
        'ConversionFactor',
        'Price',
        'InternalPrice',
        'MinOrderQty',
        'SupplierId',
        'Priority',
        'PackageType',
        'IsTrackingSerial',
        'IsExpiryDate',
        'Specification',
        'Note',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function productCategory(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'ProductCategoryId', 'ProductCategoryId');
    }

    public function brand(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductBrand::class, 'ProductBrandId', 'ProductBrandId');
    }

    public function supplier(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'SupplierId', 'SupplierId');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnitInventory::class, 'UnitId', 'UnitId');
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Inventory::class, 'ProductId', 'ProductId');
    }

    public function createdBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Staff::class, 'CreatedBy', 'StaffId');
    }
}
