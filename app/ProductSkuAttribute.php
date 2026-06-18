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
class ProductSkuAttribute extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductSkuAttribute';

    protected $primaryKey = NULL;

    public $timestamps = false;

    protected $fillable = [
        'ProductId',
        'ProductAttributeValueId'
    ];

    protected $hidden = [];
}
