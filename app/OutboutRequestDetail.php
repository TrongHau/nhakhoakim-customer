<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $OutboutRequestDetailId
 * @property int         $OutboutRequestId
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
class OutboutRequestDetail extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'OutboutRequestDetail';

    protected $primaryKey = 'OutboutRequestDetailId';

    public $timestamps = false;

    protected $fillable = [
        'OutboutRequestDetailId',
        'OutboutRequestId',
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

    public function outboutRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OutboutRequest::class, 'OutboutRequestId', 'OutboutRequestId');
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
