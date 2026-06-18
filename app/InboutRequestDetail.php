<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $InboutRequestDetailId
 * @property int         $InboutRequestId
 * @property int         $ProductId
 * @property int|null    $UnitId
 * @property string|null $PartnerSKU
 * @property int|null    $ExpectedQty
 * @property int|null    $RefQty
 * @property int|null    $ActualQty
 * @property int|null    $ExceptionQty
 * @property float|null  $Price
 * @property \DateTime|null $ExpirationDate
 * @property \DateTime|null $ManufactureDate
 * @property string|null $LOT
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 */
class InboutRequestDetail extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'InboutRequestDetail';

    protected $primaryKey = 'InboutRequestDetailId';

    public $timestamps = false;

    protected $fillable = [
        'InboutRequestDetailId',
        'InboutRequestId',
        'ProductId',
        'UnitId',
        'PartnerSKU',
        'ExpectedQty',
        'RefQty',
        'ActualQty',
        'ExceptionQty',
        'Price',
        'ExpirationDate',
        'ManufactureDate',
        'LOT',
        'CreatedBy',
        'CreatedDate'
    ];

    protected $hidden = [];

    public function inboutRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InboutRequest::class, 'InboutRequestId', 'InboutRequestId');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'ProductId', 'ProductId');
    }

    public function unit(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(UnitInventory::class, 'UnitId', 'UnitId');
    }
}
