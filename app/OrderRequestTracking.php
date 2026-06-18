<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $OrderRequestTrackingId
 * @property int         $OrderRequestId
 * @property string      $ActionType   create|update|update_status|update_expected_delivery_date
 * @property int|null    $OldStatus
 * @property int|null    $NewStatus
 * @property string|null $OldData      JSON snapshot trước khi thay đổi
 * @property string|null $NewData      JSON snapshot sau khi thay đổi
 * @property string|null $Note
 * @property int|null    $CreatedBy
 * @property \DateTime   $CreatedDate
 */
class OrderRequestTracking extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'OrderRequestTracking';

    protected $primaryKey = 'OrderRequestTrackingId';

    public $timestamps = false;

    protected $fillable = [
        'OrderRequestId',
        'ActionType',
        'OldStatus',
        'NewStatus',
        'OldData',
        'NewData',
        'Note',
        'CreatedBy',
        'CreatedDate',
    ];

    protected $hidden = [];

    public function orderRequest(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'OrderRequestId', 'OrderRequestId');
    }
}
