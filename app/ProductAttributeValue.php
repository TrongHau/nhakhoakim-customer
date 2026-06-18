<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $ProductAttributeValueId
 * @property int|null    $ProductAttributeTypeId
 * @property int|null    $Value
 * @property string|null $DisplayLabel
 * @property int|null    $Priority
 * @property int|null    $Status
 * @property \DateTime|null $CreatedDate
 */
class ProductAttributeValue extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductAttributeValue';

    protected $primaryKey = 'ProductAttributeValueId';

    public $timestamps = false;

    protected $fillable = [
        'ProductAttributeValueId',
        'ProductAttributeTypeId',
        'Value',
        'DisplayLabel',
        'Priority',
        'Status',
        'CreatedDate'
    ];

    protected $hidden = [];
}
