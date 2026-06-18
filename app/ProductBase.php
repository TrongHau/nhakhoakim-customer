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
class ProductBase extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductBase';

    protected $primaryKey = NULL;

    public $timestamps = false;

    protected $fillable = [
        'ProductBaseId',
        'ProductCategoryId',
        'ProductBrandId',
        'Code',
        'Name',
        'NameUnsign',
        'Priority',
        'Description',
        'BaseUnitId',
        'PurchaseUnitId',
        'ConversionFactor',
        'Note',
        'Status',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];
}
