<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int         $ProductCategoryId
 * @property string      $Name
 * @property int         $Status
 * @property int|null    $Priority
 * @property int|null    $Type
 * @property int|null    $ParentId
 * @property int         $CreatedBy
 * @property \DateTime   $CreatedDate
 * @property int|null    $UpdatedBy
 * @property \DateTime|null $UpdatedDate
 */
class ProductCategory extends Model
{
    protected $connection = 'mysql_inventory';

    protected $table = 'ProductCategory';

    protected $primaryKey = 'ProductCategoryId';

    public $timestamps = false;

    protected $fillable = [
        'ProductCategoryId',
        'Name',
        'Status',
        'Priority',
        'Type',
        'ParentId',
        'CreatedBy',
        'CreatedDate',
        'UpdatedBy',
        'UpdatedDate'
    ];

    protected $hidden = [];

    public function products(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Product::class, 'ProductCategoryId', 'ProductCategoryId');
    }

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'ParentId', 'ProductCategoryId');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductCategory::class, 'ParentId', 'ProductCategoryId');
    }
}
