<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int      $SupplierId
 * @property string   $SupplierCode
 * @property string   $Name
 * @property string   $TaxCode
 * @property string   $Phone
 * @property string   $Email
 * @property string   $Address
 * @property string   $Note
 * @property int      $Status
 * @property int      $CreatedBy
 * @property DateTime $CreatedDate
 * @property int      $UpdatedBy
 * @property DateTime $UpdatedDate
 */
class Supplier extends Model
{
    /**
     * The connection name for the model.
     *
     * @var string
     */
    protected $connection = 'mysql_inventory';

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'Supplier';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'SupplierId';

    /**
     * Attributes that should be mass-assignable.
     *
     * @var array
     */
    protected $fillable = [
        'SupplierId',
        'SupplierCode',
        'Name',
        'TaxCode',
        'Phone',
        'Email',
        'Address',
        'Note',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'SupplierId', 'SupplierId');
    }

    public function purchaseOrders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'SupplierId', 'SupplierId');
    }
}
