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
class ProductBaseAttribute extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductBaseAttribute';

    protected $primaryKey = 'ProductBaseAttributeId';

    public $timestamps = false;

    protected $fillable = [
        'ProductBaseAttributeId',
        'ProductBaseId',
        'ProductAttributeTypeId',
        'IsRequired',
        'Priority',
        'CreatedDate'
    ];

    protected $hidden = [];
}
