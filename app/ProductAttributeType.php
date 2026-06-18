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
class ProductAttributeType extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductAttributeType';

    protected $primaryKey = 'ProductAttributeTypeId';

    public $timestamps = false;

    protected $fillable = [
        'ProductAttributeTypeId',
        'Code',
        'Name',
        'DataType',
        'Priority',
        'Status',
        'CreatedDate'
    ];

    protected $hidden = [];
}
