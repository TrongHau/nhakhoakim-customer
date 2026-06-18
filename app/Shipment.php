<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
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
    protected $table = 'Shipment';


    protected $primaryKey = 'ShipmentId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'ShipmentId',
        'TrackingCode',
        'ProviderId',
        'Status',
        'ShipperName',
        'ShipperPhone',
        'ShippedDate',
        'DeliveredDate',
        'CreatedDate'
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

    public function shipmentProvider()
    {
        return $this->belongsTo(ShipmentProvider::class, 'ProviderId', 'ProviderId');
    }

}
