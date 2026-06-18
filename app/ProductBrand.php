<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $ProductAttributeTypeId
 * @property string|null $Code
 * @property string|null $Name
 * @property string|null $DataType
 * @property int|null    $Priority
 * @property int|null    $Status
 * @property \DateTime|null $CreatedDate
 */
class ProductBrand extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductBrand';

    protected $primaryKey = 'ProductBaseAttributeId';

    public $timestamps = false;

    protected $fillable = [
        'ProductBrandId',
        'Code',
        'Name',
        'Country',
        'Priority',
        'Status',
        'CreatedDate'
    ];

    protected $hidden = [];
}
